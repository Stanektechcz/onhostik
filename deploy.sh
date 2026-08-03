#!/bin/bash

################################################################################
# OnHost.cz — Deployment Script
#
# Bezpečný deploy s logováním, rollback možností a ověřením
# Použití: ./deploy.sh [--branch development] [--no-migrate] [--force]
#
# Autor: Claude Code · Datum: 2026-08-03
################################################################################

set -o pipefail

# Konfigurace
REPO_PATH="/var/www/clients/client10/web19/web"
BRANCH="${1:---branch}"
BRANCH_NAME="${2:-development}"
FORCE_MODE=false
SKIP_MIGRATE=false
LOG_FILE="${REPO_PATH}/storage/logs/deploy-$(date +%Y%m%d-%H%M%S).log"
BACKUP_DIR="${REPO_PATH}/backups/deploy-$(date +%Y%m%d-%H%M%S)"

# Barvy pro výstup
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

################################################################################
# Funkce
################################################################################

log() {
    local level=$1
    shift
    local msg="$@"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $msg" | tee -a "$LOG_FILE"
}

log_info() { log "INFO" "$@"; }
log_warn() { log "WARN" "$@"; }
log_error() { log "ERROR" "$@"; }
log_success() { log "SUCCESS" "$@"; }

die() {
    log_error "$@"
    exit 1
}

check_command() {
    if ! command -v "$1" &> /dev/null; then
        die "Příkaz '$1' nenalezen. Nainstalujte ho prosím."
    fi
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --branch) BRANCH_NAME="$2"; shift 2 ;;
            --no-migrate) SKIP_MIGRATE=true; shift ;;
            --force) FORCE_MODE=true; shift ;;
            -h|--help) show_help; exit 0 ;;
            *) log_warn "Neznámý argument: $1"; shift ;;
        esac
    done
}

show_help() {
    cat <<EOF
Užití: ./deploy.sh [OPTIONS]

Opce:
  --branch NAME       Branch k deployi (výchozí: development)
  --no-migrate        Vynechat databázové migrace
  --force             Vynechat potvrzení (jen pro CI/CD)
  -h, --help         Zobrazit tuto nápovědu

Příklady:
  ./deploy.sh --branch main
  ./deploy.sh --branch development --no-migrate
  ./deploy.sh --force

EOF
}

confirm() {
    if [ "$FORCE_MODE" = true ]; then
        return 0
    fi

    local prompt="$1"
    read -p "$prompt (ano/ne): " -r
    [[ $REPLY =~ ^[Yy]$ ]] || return 1
}

################################################################################
# Kontroly před deployem
################################################################################

preflight_checks() {
    log_info "=== Spouštění preflight kontrol ==="

    # Ověřit cestu
    if [ ! -d "$REPO_PATH" ]; then
        die "Repo cesta neexistuje: $REPO_PATH"
    fi

    # Ověřit příkazy
    check_command git
    check_command php
    check_command composer

    # Ověřit, že je to git repo
    if ! git -C "$REPO_PATH" rev-parse --git-dir > /dev/null 2>&1; then
        die "Není to git repo: $REPO_PATH"
    fi

    # Ověřit, že není dirty (lokální změny)
    cd "$REPO_PATH"
    if ! git diff-index --quiet HEAD --; then
        log_warn "Místní změny v repo:"
        git status --short
        if ! confirm "Chcete je odstranit (stash)?"; then
            die "Deploy zrušen. Commit nebo stash vaše změny."
        fi
        git stash
    fi

    log_success "Preflight kontroly OK"
}

################################################################################
# Fáze 1: Pull + Dependence
################################################################################

phase_pull() {
    log_info "=== Fáze 1: Pull + Composer ==="

    cd "$REPO_PATH"

    # Zjistit aktuální commit pro možný rollback
    local PREVIOUS_COMMIT=$(git rev-parse HEAD)
    echo "$PREVIOUS_COMMIT" > "$REPO_PATH/.previous-deploy"

    # Fetch nejnovějšího
    log_info "Fetching z remote..."
    git fetch origin || die "Git fetch selhalo"

    # Checkout na správný branch
    log_info "Checkout na branch: $BRANCH_NAME"
    if ! git rev-parse --verify "origin/$BRANCH_NAME" &>/dev/null; then
        die "Branch neexistuje: origin/$BRANCH_NAME"
    fi
    git checkout "$BRANCH_NAME" || die "Checkout selhalo"
    git pull origin "$BRANCH_NAME" || die "Git pull selhalo"

    local CURRENT_COMMIT=$(git rev-parse HEAD)
    log_info "Aktuální commit: $CURRENT_COMMIT"

    # Composer install
    log_info "Composer install..."
    if ! /usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1 | tee -a "$LOG_FILE"; then
        die "Composer install selhalo"
    fi

    log_success "Pull + Composer OK"
}

################################################################################
# Fáze 2: Migrace
################################################################################

phase_migrate() {
    if [ "$SKIP_MIGRATE" = true ]; then
        log_warn "Migrace přeskočeny (--no-migrate)"
        return 0
    fi

    log_info "=== Fáze 2: Databázové migrace ==="

    cd "$REPO_PATH"

    # Ověřit status migrací
    log_info "Kontrola migračního statusu..."
    php artisan migrate:status 2>&1 | head -20 | tee -a "$LOG_FILE"

    # Spustit migrace
    log_info "Spouštění migrací..."
    if ! php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"; then
        die "Migrace selhaly"
    fi

    log_success "Migrace OK"
}

################################################################################
# Fáze 3: Cache Clear
################################################################################

phase_cache() {
    log_info "=== Fáze 3: Cache Clear ==="

    cd "$REPO_PATH"

    log_info "Vynulování view cache..."
    php artisan view:clear 2>&1 | tee -a "$LOG_FILE"

    log_info "Vynulování config cache..."
    php artisan config:clear 2>&1 | tee -a "$LOG_FILE"

    log_info "Vynulování route cache..."
    php artisan route:clear 2>&1 | tee -a "$LOG_FILE"

    log_info "Vynulování cache..."
    php artisan cache:clear 2>&1 | tee -a "$LOG_FILE"

    log_success "Cache Clear OK"
}

################################################################################
# Fáze 4: Build Assets
################################################################################

phase_assets() {
    log_info "=== Fáze 4: Build Assets ==="

    cd "$REPO_PATH"

    if [ ! -f "package.json" ]; then
        log_warn "package.json nenalezen, přeskakuji build"
        return 0
    fi

    if command -v npm &> /dev/null; then
        log_info "npm build..."
        if npm run build 2>&1 | tail -20 | tee -a "$LOG_FILE"; then
            log_success "Assets build OK"
        else
            log_warn "Asset build selhal, pokračuji (build assets nemusí být povinné)"
        fi
    else
        log_warn "npm nenalezen, přeskakuji build"
    fi
}

################################################################################
# Fáze 5: Scheduler Check
################################################################################

phase_scheduler() {
    log_info "=== Fáze 5: Scheduler Check ==="

    cd "$REPO_PATH"

    log_info "Kontrola plánovaných úloh..."
    local TASK_COUNT=$(php artisan schedule:list 2>&1 | grep -c "Next Due" || echo "0")

    if [ "$TASK_COUNT" -lt 40 ]; then
        log_warn "Málo plánovaných úloh: $TASK_COUNT (očekáváno 48)"
        php artisan schedule:list 2>&1 | head -50 | tee -a "$LOG_FILE"
    else
        log_success "Počet plánovaných úloh: $TASK_COUNT"
    fi

    # Ověřit konkrétní kritické úlohy
    local CRITICAL_TASKS=(
        "billing:expire-credit"
        "billing:process-auto-topups"
        "announcements:publish-scheduled"
        "drip:process"
        "monitoring:evaluate-alerts"
    )

    for task in "${CRITICAL_TASKS[@]}"; do
        if php artisan schedule:list 2>&1 | grep -q "$task"; then
            log_info "✓ $task je naplánován"
        else
            log_warn "✗ $task NENÍ naplánován!"
        fi
    done
}

################################################################################
# Fáze 6: Restart Services
################################################################################

phase_restart() {
    log_info "=== Fáze 6: Restart Services ==="

    # Restart queue workers (via Supervisor)
    if command -v supervisorctl &> /dev/null; then
        log_info "Restarting Supervisor workers..."
        if sudo supervisorctl restart all 2>&1 | tee -a "$LOG_FILE"; then
            log_success "Supervisor restart OK"
        else
            log_warn "Supervisor restart selhal, pokračuji"
        fi
    else
        log_warn "Supervisor nenalezen, přeskakuji"
    fi

    # Restart PHP-FPM
    if systemctl is-active --quiet php8.4-fpm; then
        log_info "Restarting PHP-FPM..."
        if sudo systemctl restart php8.4-fpm 2>&1 | tee -a "$LOG_FILE"; then
            log_success "PHP-FPM restart OK"
        else
            log_warn "PHP-FPM restart selhal"
        fi
    fi

    # Nginx/Apache check
    if systemctl is-active --quiet nginx; then
        log_info "Testing nginx..."
        if sudo nginx -t 2>&1 | tee -a "$LOG_FILE"; then
            sudo systemctl reload nginx
            log_success "nginx reload OK"
        fi
    elif systemctl is-active --quiet apache2; then
        log_info "Testing apache2..."
        sudo systemctl reload apache2
        log_success "apache2 reload OK"
    fi
}

################################################################################
# Fáze 7: Ověření
################################################################################

phase_verify() {
    log_info "=== Fáze 7: Ověření ==="

    cd "$REPO_PATH"

    # Check PHP syntax
    log_info "Kontrola PHP syntaxe..."
    if find . -name "*.php" -path "./app/*" -exec php -l {} \; 2>&1 | grep -i "parse error" | head -5; then
        log_warn "PHP parse errors found!"
    else
        log_success "PHP syntax OK"
    fi

    # Check Laravel health
    log_info "Laravel health check..."
    if php artisan tinker --execute="echo 'Laravel OK';" 2>&1 | grep -q "OK"; then
        log_success "Laravel check OK"
    fi

    # Queue status
    if command -v php &> /dev/null; then
        log_info "Queue monitoring..."
        php artisan queue:monitor --max=100 2>&1 | head -5 | tee -a "$LOG_FILE" || true
    fi

    # Log check
    log_info "Kontrola posledních error logů..."
    if [ -f "$REPO_PATH/storage/logs/laravel.log" ]; then
        local ERROR_COUNT=$(tail -100 "$REPO_PATH/storage/logs/laravel.log" | grep -iE "error|exception|failed" | wc -l)
        if [ "$ERROR_COUNT" -gt 5 ]; then
            log_warn "Nalezeno $ERROR_COUNT chyb v posledních 100 řádcích logu:"
            tail -20 "$REPO_PATH/storage/logs/laravel.log" | grep -iE "error|exception|failed" | tee -a "$LOG_FILE"
        else
            log_success "Log OK ($ERROR_COUNT errors)"
        fi
    fi
}

################################################################################
# Rollback
################################################################################

rollback() {
    log_warn "=== ROLLBACK ==="

    cd "$REPO_PATH"

    if [ -f ".previous-deploy" ]; then
        local PREV_COMMIT=$(cat .previous-deploy)
        log_info "Rolování zpět na: $PREV_COMMIT"

        if git reset --hard "$PREV_COMMIT" 2>&1 | tee -a "$LOG_FILE"; then
            /usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1 | tail -5 | tee -a "$LOG_FILE"
            php artisan migrate --force 2>&1 | tail -5 | tee -a "$LOG_FILE"
            php artisan cache:clear 2>&1 | tail -3 | tee -a "$LOG_FILE"

            sudo supervisorctl restart all 2>&1 | tail -3 | tee -a "$LOG_FILE"

            log_error "Deploy selhalo a byl proveden rollback na $PREV_COMMIT"
            exit 1
        else
            die "Rollback také selhal! Vyžaduje se ruční zásah."
        fi
    else
        die "Nelze najít .previous-deploy, rollback není možný"
    fi
}

################################################################################
# Main
################################################################################

main() {
    parse_args "$@"

    # Příprava
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$BACKUP_DIR"

    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_info "║          OnHost.cz Deploy — Spuštění                           ║"
    log_info "║━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━╝"
    log_info "Repo:          $REPO_PATH"
    log_info "Branch:        $BRANCH_NAME"
    log_info "Skip migrate:  $SKIP_MIGRATE"
    log_info "Force mode:    $FORCE_MODE"
    log_info "Log:           $LOG_FILE"
    log_info "Backup:        $BACKUP_DIR"
    log_info ""

    if ! confirm "Pokračovat s deployem?"; then
        log_warn "Deploy zrušen uživatelem"
        exit 0
    fi

    # Spuštění fází
    preflight_checks || die "Preflight checks selhaly"

    # Trap pro error handling
    trap rollback ERR

    phase_pull || die "Pull selhalo"
    phase_migrate || die "Migrace selhaly"
    phase_cache || die "Cache clear selhalo"
    phase_assets || log_warn "Assets build selhalo (necritical)"
    phase_scheduler || log_warn "Scheduler check selhalo (necritical)"
    phase_restart || log_warn "Restart selhalo (necritical)"
    phase_verify || log_warn "Ověření selhalo (necritical)"

    # Úspěch
    local DURATION=$SECONDS
    local HOURS=$((DURATION / 3600))
    local MINUTES=$(( (DURATION % 3600) / 60 ))
    local SECONDS=$((DURATION % 60))

    log_info ""
    log_info "╔════════════════════════════════════════════════════════════════╗"
    log_success "║          Deploy ÚSPĚŠNÝ ✓                                      ║"
    log_info "║━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━╝"
    log_success "Doba trvání: ${HOURS}h ${MINUTES}m ${SECONDS}s"
    log_info "Log uložen: $LOG_FILE"
    log_info ""
}

# Spuštění
main "$@"
