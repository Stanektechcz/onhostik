#!/usr/bin/env bash
# ONhost — install the gated deployer (TASK-0032) root-owned outside the site tree, from a verified SHA:
#   /usr/local/sbin/onhost-deploy                 ← infra/aapanel/deploy.sh at $SHA
#   /usr/local/lib/onhost-deploy/deploy-gate.php  ← infra/aapanel/deploy-gate.php at $SHA
#   /usr/local/lib/onhost-deploy/source-sha       ← $SHA (the deployer refuses a target that carries a different one)
#
# The files come out of the root-owned .git with `git show`, never from the www-writable working tree. The deployer
# only moves forward: a SHA that does not descend from the installed one is refused unless FIRST=1 (first install).
#
#   SHA=<40-hex> [FIRST=1] bash install-deployer.sh
set -euo pipefail

SITE="${SITE:-staging.onhost.cz}"
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"
SHA="${SHA:-}"
FIRST="${FIRST:-0}"
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/${SITE}}"
DEPLOYER_BIN="${DEPLOYER_BIN:-/usr/local/sbin/onhost-deploy}"
DEPLOYER_LIB_DIR="${DEPLOYER_LIB_DIR:-/usr/local/lib/onhost-deploy}"
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"

die() { printf '✖ %s\n' "$*" >&2; exit 2; }
g() { GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL="${DEPLOY_STATE_DIR}/gitconfig" git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }

[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || die "SHA must be the full 40-character commit to install the deployer from"
[ -d "$APP_DIR/.git" ] || die "$APP_DIR is not a checkout"
[ -z "$(find "$APP_DIR/.git" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ] \
  || die ".git must belong to uid $DEPLOY_OWNER_UID and be writable by nobody else (chown -R root:root .git && chmod -R go-w .git)"
(umask 077; mkdir -p "$DEPLOY_STATE_DIR") && chmod 700 "$DEPLOY_STATE_DIR"
[ -f "$DEPLOY_STATE_DIR/gitconfig" ] || (umask 077; printf '[safe]\n\tdirectory = %s\n' "$APP_DIR" > "$DEPLOY_STATE_DIR/gitconfig")

g cat-file -e "${SHA}^{commit}" 2>/dev/null || die "$SHA is not in $APP_DIR (git fetch --tags origin first)"
installed="$(cat "$DEPLOYER_LIB_DIR/source-sha" 2>/dev/null || true)"
if [ -n "$installed" ] && [ "$FIRST" != 1 ]; then
  g merge-base --is-ancestor "$installed" "$SHA" 2>/dev/null || die "$SHA does not descend from the installed deployer $installed (no downgrades; FIRST=1 only for a first install)"
elif [ -z "$installed" ] && [ "$FIRST" != 1 ]; then
  die "no deployer is installed yet: FIRST=1 SHA=$SHA bash $0"
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
g show "${SHA}:infra/aapanel/deploy.sh" > "$work/deploy.sh" || die "infra/aapanel/deploy.sh is not in $SHA"
g show "${SHA}:infra/aapanel/deploy-gate.php" > "$work/deploy-gate.php" || die "infra/aapanel/deploy-gate.php is not in $SHA"
bash -n "$work/deploy.sh" || die "deploy.sh at $SHA does not parse"

mkdir -p "$(dirname "$DEPLOYER_BIN")" "$DEPLOYER_LIB_DIR"
install -m 0755 "$work/deploy.sh" "$DEPLOYER_BIN.new" && mv -f "$DEPLOYER_BIN.new" "$DEPLOYER_BIN"
install -m 0644 "$work/deploy-gate.php" "$DEPLOYER_LIB_DIR/deploy-gate.php"
printf '%s\n' "$SHA" > "$DEPLOYER_LIB_DIR/source-sha.new" && mv -f "$DEPLOYER_LIB_DIR/source-sha.new" "$DEPLOYER_LIB_DIR/source-sha"
echo "deployer installed from $SHA: $DEPLOYER_BIN, $DEPLOYER_LIB_DIR/{deploy-gate.php,source-sha}"
