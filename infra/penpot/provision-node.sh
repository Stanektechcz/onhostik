#!/usr/bin/env bash
# ONhost Penpot node: idempotent provisioning of a dedicated Debian/Ubuntu server (I-R7, TASK-0141).
#
# Does steps 1-5 of docs/runbooks/penpot.md "Server prerequisites" on the node itself: Docker Engine + compose plugin, Caddy,
# the platform's non-root deploy user (SSH key only), its directories and sudo rules, the quota helper, the firewall and the
# SSH host-key pinning aid. Steps 6-10 (DNS, SMTP, registering the node, the queue lane, the smoke test) are the operator's in
# the platform; the runbook gives the order.
#
# NO SECRETS: the script takes a PUBLIC key only and refuses anything that looks like a private key. The platform's private
# key never touches this server's disk through this script; the passwords and keys of the platform live in the vault
# (`php artisan onhost:integrations:secret`, `onhost:secrets:set`), never here, never in an argument.
#
# Re-running is safe: every step looks at the state first and changes only what differs. Run as root ON THE NODE:
#
#   sudo ./provision-node.sh --ssh-pubkey-file /root/onhost-deploy.pub --ssh-allow 203.0.113.10 [--dry-run]
#
# Usage: provision-node.sh --ssh-pubkey-file FILE --ssh-allow CIDR [--ssh-allow CIDR ...] [options]
#   --ssh-pubkey-file FILE     public key of the platform's SSH user (one line, ssh-ed25519 ... / ecdsa / rsa)      (required)
#   --ssh-allow CIDR           address or network allowed to reach SSH (the control plane; add your own); repeatable (required,
#                              or --ssh-open to accept SSH from anywhere, which the runbook does not recommend)
#   --ssh-open                 do not restrict SSH by source address (firewall + key `from=`)
#   --ssh-port N               port sshd listens on (default 22); the firewall rule follows it, sshd is NOT moved by this script
#   --deploy-user NAME         the platform's user on the node (default onhost; provider option ssh_user)
#   --docker-major N           Docker Engine major to install and pin (default 29; the adapter is written for 27-29)
#   --caddy-source official|distro   Caddy from the Caddy project's apt repository (default) or the distribution's package
#   --keep-host-key-algorithms leave sshd's HostKeyAlgorithms alone (default: ssh-ed25519 only, so ONE fingerprint exists to pin)
#   --no-firewall              do not touch ufw (use when the provider's network firewall does it; verify-node.sh then warns)
#   --require-quota            fail if /var/lib/docker is not an XFS filesystem mounted with prjquota
#   --dry-run                  print what would change, change nothing (works without root)
#   --force-os                 continue on an OS the script was not written for
set -euo pipefail

DEPLOY_USER="onhost"
SSH_PORT="22"
PUBKEY_FILE=""
SSH_ALLOW=()
SSH_OPEN=0
DOCKER_MAJOR="29"
CADDY_SOURCE="official"
KEEP_HOSTKEY_ALGS=0
FIREWALL=1
REQUIRE_QUOTA=0
DRY_RUN=0
FORCE_OS=0

# the paths the platform's adapter uses (config/penpot.php root, backup_root, proxy_sites)
STACK_ROOT="/srv/onhost-penpot"
BACKUP_ROOT="/var/backups/onhost-penpot"
PROXY_SITES="/etc/caddy/onhost-penpot"
QUOTA_HELPER="/usr/local/sbin/onhost-penpot-quota"
# Docker's published apt signing key (https://docs.docker.com/engine/install/): the script compares the downloaded key with it
DOCKER_KEY_FPR="9DC858229FC7DD38854AE2D88D81803C0EBFCD88"

CHANGED=0
LAST_WRITE_CHANGED=0
log() { printf '%s\n' "$*"; }
step() { printf '\n== %s\n' "$*"; }
warn() { printf 'WARN: %s\n' "$*" >&2; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
changed() { CHANGED=$((CHANGED + 1)); log "  changed: $*"; }

usage() { sed -n '2,/^set -euo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'; }

while [ "$#" -gt 0 ]; do
  case "$1" in
    --ssh-pubkey-file) PUBKEY_FILE="${2:?}"; shift 2 ;;
    --ssh-allow) SSH_ALLOW+=("${2:?}"); shift 2 ;;
    --ssh-open) SSH_OPEN=1; shift ;;
    --ssh-port) SSH_PORT="${2:?}"; shift 2 ;;
    --deploy-user) DEPLOY_USER="${2:?}"; shift 2 ;;
    --docker-major) DOCKER_MAJOR="${2:?}"; shift 2 ;;
    --caddy-source) CADDY_SOURCE="${2:?}"; shift 2 ;;
    --keep-host-key-algorithms) KEEP_HOSTKEY_ALGS=1; shift ;;
    --no-firewall) FIREWALL=0; shift ;;
    --require-quota) REQUIRE_QUOTA=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    --force-os) FORCE_OS=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) die "unknown option: $1 (see --help)" ;;
  esac
done

# ---- input validation (fail before anything changes) -------------------------------------------------------------------
[[ "$DEPLOY_USER" =~ ^[a-z][a-z0-9_-]{1,31}$ ]] || die "--deploy-user must be a plain lower-case account name"
[ "$DEPLOY_USER" != "root" ] || die "the platform's user must not be root"
[[ "$SSH_PORT" =~ ^[0-9]{1,5}$ ]] && [ "$SSH_PORT" -ge 1 ] && [ "$SSH_PORT" -le 65535 ] || die "--ssh-port must be 1-65535"
[[ "$DOCKER_MAJOR" =~ ^[0-9]{2}$ ]] || die "--docker-major must be a two-digit major (27, 28, 29)"
case "$CADDY_SOURCE" in official|distro) ;; *) die "--caddy-source must be official or distro" ;; esac
[ -n "$PUBKEY_FILE" ] || die "--ssh-pubkey-file is required (the PUBLIC key of the platform's SSH user)"
if [ "$SSH_OPEN" -eq 0 ] && [ "${#SSH_ALLOW[@]}" -eq 0 ]; then
  die "give --ssh-allow <control plane address> (repeatable), or --ssh-open to accept SSH from anywhere"
fi
for cidr in "${SSH_ALLOW[@]}"; do
  [[ "$cidr" =~ ^[0-9a-fA-F:.]+(/[0-9]{1,3})?$ ]] || die "--ssh-allow is an address or CIDR, got: $cidr"
done
[ -r "$PUBKEY_FILE" ] || die "cannot read $PUBKEY_FILE"
if grep -q -E 'PRIVATE KEY|BEGIN OPENSSH' "$PUBKEY_FILE"; then
  die "$PUBKEY_FILE contains a PRIVATE key. Give the public key (.pub) only; never copy a private key to this server."
fi
PUBKEY="$(grep -v -E '^[[:space:]]*(#|$)' "$PUBKEY_FILE" | head -n 1)"
[[ "$PUBKEY" =~ ^(ssh-ed25519|ssh-rsa|ecdsa-sha2-nistp(256|384|521))\ [A-Za-z0-9+/=]+(\ .*)?$ ]] \
  || die "$PUBKEY_FILE does not hold a public key line (ssh-ed25519 AAAA... comment)"
if command -v ssh-keygen >/dev/null 2>&1; then
  printf '%s\n' "$PUBKEY" | ssh-keygen -l -f /dev/stdin >/dev/null 2>&1 || die "ssh-keygen does not accept the public key in $PUBKEY_FILE"
fi

# ---- helpers: everything that changes the machine goes through these ---------------------------------------------------
run() { # run a command, or only show it
  if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] $*"; return 0; fi
  "$@"
}

# write_file PATH MODE OWNER:GROUP  (content on stdin): writes only when the content or the metadata differ
write_file() {
  local path="$1" mode="$2" owner="$3" tmp
  LAST_WRITE_CHANGED=0
  tmp="$(mktemp)"
  cat >"$tmp"
  if [ -f "$path" ] && cmp -s "$tmp" "$path" && [ "$(stat -c '%a' "$path")" = "$mode" ] && [ "$(stat -c '%U:%G' "$path")" = "$owner" ]; then
    rm -f "$tmp"; return 0
  fi
  LAST_WRITE_CHANGED=1
  if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] write $path ($mode $owner)"; rm -f "$tmp"; CHANGED=$((CHANGED + 1)); return 0; fi
  install -D -m "$mode" -o "${owner%%:*}" -g "${owner##*:}" "$tmp" "$path"
  rm -f "$tmp"
  changed "$path"
}

ensure_dir() { # ensure_dir PATH MODE OWNER:GROUP
  local path="$1" mode="$2" owner="$3"
  if [ -d "$path" ] && [ "$(stat -c '%a' "$path")" = "$mode" ] && [ "$(stat -c '%U:%G' "$path")" = "$owner" ]; then return 0; fi
  if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] directory $path ($mode $owner)"; CHANGED=$((CHANGED + 1)); return 0; fi
  install -d -m "$mode" -o "${owner%%:*}" -g "${owner##*:}" "$path"
  changed "directory $path"
}

ip_in_allow_list() { # is this address covered by one of the --ssh-allow entries? (python3 when there, exact match otherwise)
  local ip="$1" cidr
  for cidr in "${SSH_ALLOW[@]}"; do
    if command -v python3 >/dev/null 2>&1; then
      python3 - "$ip" "$cidr" <<'PY' && return 0
import ipaddress, sys
try:
    sys.exit(0 if ipaddress.ip_address(sys.argv[1]) in ipaddress.ip_network(sys.argv[2], strict=False) else 1)
except ValueError:
    sys.exit(1)
PY
    elif [ "$ip" = "${cidr%%/*}" ]; then return 0; fi
  done
  return 1
}

apt_install() { # apt_install PKG... : installs what is missing
  local missing=() p
  for p in "$@"; do dpkg -s "$p" >/dev/null 2>&1 || missing+=("$p"); done
  [ "${#missing[@]}" -eq 0 ] && return 0
  run env DEBIAN_FRONTEND=noninteractive apt-get install -y -q --no-install-recommends "${missing[@]}"
  CHANGED=$((CHANGED + 1)); log "  installed: ${missing[*]}"
}

# ---- 0. preflight -----------------------------------------------------------------------------------------------------
step "0. Preflight"
if [ "$DRY_RUN" -eq 0 ] && [ "$(id -u)" -ne 0 ]; then die "run as root (sudo), or use --dry-run"; fi
OS_ID=""; OS_VERSION=""
if [ -r /etc/os-release ]; then
  # shellcheck disable=SC1091
  OS_ID="$(. /etc/os-release && printf '%s' "${ID:-}")"
  # shellcheck disable=SC1091
  OS_VERSION="$(. /etc/os-release && printf '%s' "${VERSION_ID:-}")"
  OS_CODENAME="$(. /etc/os-release && printf '%s' "${VERSION_CODENAME:-}")"
else
  OS_CODENAME=""
fi
case "$OS_ID:$OS_VERSION" in
  debian:12|debian:13|ubuntu:24.04) log "  OS: $OS_ID $OS_VERSION ($OS_CODENAME)" ;;
  *)
    if [ "$FORCE_OS" -eq 1 ] || { [ "$DRY_RUN" -eq 1 ] && [ -z "$OS_ID" ]; }; then warn "OS '$OS_ID $OS_VERSION' is not Debian 12/13 or Ubuntu 24.04"
    else die "OS '$OS_ID $OS_VERSION' is not Debian 12/13 or Ubuntu 24.04 (--force-os to try anyway)"; fi ;;
esac
ARCH="$(dpkg --print-architecture 2>/dev/null || echo amd64)"
log "  architecture: $ARCH, deploy user: $DEPLOY_USER, SSH port: $SSH_PORT, dry run: $DRY_RUN"
if [ "$FIREWALL" -eq 1 ] && [ "$SSH_OPEN" -eq 0 ] && [ -n "${SSH_CLIENT:-}" ]; then
  client_ip="${SSH_CLIENT%% *}"
  if ! ip_in_allow_list "$client_ip"; then
    die "you are connected from $client_ip, which --ssh-allow does not cover: enabling the firewall would cut this session. Add --ssh-allow $client_ip (or --no-firewall)."
  fi
fi

# ---- 1. packages ------------------------------------------------------------------------------------------------------
step "1. Base packages"
run apt-get update -q
apt_install ca-certificates curl gnupg util-linux openssh-server sudo xfsprogs
if [ "$FIREWALL" -eq 1 ]; then apt_install ufw; fi

# ---- 2. Docker Engine + compose plugin --------------------------------------------------------------------------------
step "2. Docker Engine $DOCKER_MAJOR with the compose plugin"
DOCKER_LIST="/etc/apt/sources.list.d/docker.list"
DOCKER_KEYRING="/etc/apt/keyrings/docker.asc"
if [ ! -s "$DOCKER_KEYRING" ] || ! gpg --show-keys --with-colons "$DOCKER_KEYRING" 2>/dev/null | grep -q "^fpr:::::::::${DOCKER_KEY_FPR}:"; then
  if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] fetch Docker's apt key and compare it with $DOCKER_KEY_FPR"
  else
    tmpkey="$(mktemp)"
    curl -fsSL "https://download.docker.com/linux/${OS_ID}/gpg" -o "$tmpkey"
    gpg --show-keys --with-colons "$tmpkey" | grep -q "^fpr:::::::::${DOCKER_KEY_FPR}:" || { rm -f "$tmpkey"; die "Docker's apt key does not have the published fingerprint $DOCKER_KEY_FPR; nothing installed"; }
    install -D -m 0644 "$tmpkey" "$DOCKER_KEYRING"; rm -f "$tmpkey"; changed "$DOCKER_KEYRING"
  fi
fi
write_file "$DOCKER_LIST" 0644 root:root <<EOF
deb [arch=${ARCH} signed-by=${DOCKER_KEYRING}] https://download.docker.com/linux/${OS_ID} ${OS_CODENAME} stable
EOF
# the adapter is written for Docker 27-29 and the panel version gate holds an unverified major: stay on the chosen major
write_file /etc/apt/preferences.d/onhost-docker 0644 root:root <<EOF
Package: docker-ce docker-ce-cli docker-ce-rootless-extras
Pin: version 5:${DOCKER_MAJOR}.*
Pin-Priority: 1001
EOF
if [ "$DRY_RUN" -eq 0 ]; then apt-get update -q; fi
apt_install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
# log rotation + live-restore, only when nobody has written a daemon.json yet (an existing one is the operator's)
if [ ! -s /etc/docker/daemon.json ]; then
  write_file /etc/docker/daemon.json 0644 root:root <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "3" },
  "live-restore": true
}
EOF
  if [ "$DRY_RUN" -eq 0 ]; then systemctl restart docker; fi
else
  log "  /etc/docker/daemon.json exists: left alone (verify-node.sh checks the log rotation)"
fi
run systemctl enable --now docker

# ---- 3. Caddy ---------------------------------------------------------------------------------------------------------
step "3. Caddy ($CADDY_SOURCE) as the reverse proxy on 80/443"
if [ "$CADDY_SOURCE" = "official" ]; then
  CADDY_KEYRING="/usr/share/keyrings/caddy-stable-archive-keyring.gpg"
  if [ ! -s "$CADDY_KEYRING" ]; then
    if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] fetch the Caddy project's apt key"
    else
      curl -fsSL https://dl.cloudsmith.io/public/caddy/stable/gpg.key | gpg --dearmor -o "$CADDY_KEYRING"
      chmod 0644 "$CADDY_KEYRING"; changed "$CADDY_KEYRING"
    fi
  fi
  write_file /etc/apt/sources.list.d/caddy-stable.list 0644 root:root <<EOF
deb [signed-by=${CADDY_KEYRING}] https://dl.cloudsmith.io/public/caddy/stable/deb/debian any-version main
EOF
  if [ "$DRY_RUN" -eq 0 ]; then apt-get update -q; fi
fi
apt_install caddy
ensure_dir "$PROXY_SITES" 0755 "$DEPLOY_USER:$DEPLOY_USER"
# an import of a glob that matches nothing must not break Caddy before the first site exists
write_file "$PROXY_SITES/00-onhost-placeholder.caddy" 0644 "$DEPLOY_USER:$DEPLOY_USER" <<'EOF'
# ONhost Penpot sites are written next to this file by the platform (one <label>.caddy per stack). Do not edit by hand.
EOF
CADDYFILE="/etc/caddy/Caddyfile"
IMPORT_LINE="import ${PROXY_SITES}/*.caddy"
if [ "$DRY_RUN" -eq 1 ] && [ ! -f "$CADDYFILE" ]; then
  log "  [dry-run] add '$IMPORT_LINE' to $CADDYFILE"
elif ! grep -Fxq "$IMPORT_LINE" "$CADDYFILE"; then
  if [ "$DRY_RUN" -eq 1 ]; then log "  [dry-run] append '$IMPORT_LINE' to $CADDYFILE"
  else
    [ -f "${CADDYFILE}.pre-onhost" ] || cp -p "$CADDYFILE" "${CADDYFILE}.pre-onhost"
    printf '\n# ONhost Penpot stacks (provision-node.sh)\n%s\n' "$IMPORT_LINE" >>"$CADDYFILE"
    changed "$CADDYFILE (import line; the original is ${CADDYFILE}.pre-onhost)"
  fi
fi
if [ "$DRY_RUN" -eq 0 ]; then
  caddy validate --config "$CADDYFILE" --adapter caddyfile >/dev/null 2>&1 || die "Caddy refuses its configuration; see: caddy validate --config $CADDYFILE"
  systemctl enable --now caddy
  systemctl reload caddy
fi

# ---- 4. the platform's user -------------------------------------------------------------------------------------------
step "4. Deploy user '$DEPLOY_USER' (SSH key only, no password, member of docker)"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  run useradd --create-home --shell /bin/bash --comment "ONhost platform (Penpot node)" "$DEPLOY_USER"
  CHANGED=$((CHANGED + 1)); log "  created user $DEPLOY_USER"
fi
if [ "$DRY_RUN" -eq 0 ]; then
  [ "$(id -u "$DEPLOY_USER")" -ne 0 ] || die "$DEPLOY_USER has uid 0"
  passwd -S "$DEPLOY_USER" 2>/dev/null | awk '{print $2}' | grep -q '^L' || { passwd -l "$DEPLOY_USER" >/dev/null; changed "password of $DEPLOY_USER locked"; }
  id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -qx docker || { usermod -aG docker "$DEPLOY_USER"; changed "$DEPLOY_USER added to docker"; }
fi
HOME_DIR="$(getent passwd "$DEPLOY_USER" | cut -d: -f6 || true)"; HOME_DIR="${HOME_DIR:-/home/$DEPLOY_USER}"
ensure_dir "$HOME_DIR/.ssh" 0700 "$DEPLOY_USER:$DEPLOY_USER"
KEY_OPTIONS="no-port-forwarding,no-agent-forwarding,no-X11-forwarding"
if [ "$SSH_OPEN" -eq 0 ]; then
  FROM_LIST=""; for cidr in "${SSH_ALLOW[@]}"; do FROM_LIST="${FROM_LIST:+$FROM_LIST,}$cidr"; done
  KEY_OPTIONS="from=\"${FROM_LIST}\",${KEY_OPTIONS}"
fi
# the file holds exactly this one key (a managed file): a changed key or list replaces it, nothing else is kept
write_file "$HOME_DIR/.ssh/authorized_keys" 0600 "$DEPLOY_USER:$DEPLOY_USER" <<EOF
${KEY_OPTIONS} ${PUBKEY}
EOF

# sshd: one host key algorithm, so there is exactly ONE fingerprint to pin as the provider option ssh_fingerprint.
# (No Match block: the deploy user's password is locked and its key carries no-port-forwarding; a Match in a drop-in
# could swallow the rest of the main file.)
SSHD_DROPIN="/etc/ssh/sshd_config.d/50-onhost-penpot.conf"
if [ "$KEEP_HOSTKEY_ALGS" -eq 1 ]; then
  log "  --keep-host-key-algorithms: sshd's host key algorithms left alone"
elif [ -d /etc/ssh/sshd_config.d ] && grep -Eq '^[[:space:]]*Include[[:space:]]+/etc/ssh/sshd_config\.d/' /etc/ssh/sshd_config 2>/dev/null; then
  write_file "$SSHD_DROPIN" 0644 root:root <<'EOF'
# ONhost Penpot node (provision-node.sh): one host key type, one fingerprint to pin
HostKeyAlgorithms ssh-ed25519
EOF
  if [ "$DRY_RUN" -eq 0 ] && [ "$LAST_WRITE_CHANGED" -eq 1 ]; then
    if sshd -t 2>/dev/null; then
      systemctl reload ssh 2>/dev/null || systemctl reload sshd 2>/dev/null || warn "could not reload sshd; do it by hand"
    else
      rm -f "$SSHD_DROPIN"; die "sshd refuses the drop-in; it was removed again"
    fi
  fi
else
  warn "sshd_config has no Include of sshd_config.d: the drop-in was NOT written; set 'HostKeyAlgorithms ssh-ed25519' in sshd_config by hand"
fi

# ---- 5. directories, sudo rules, quota helper -------------------------------------------------------------------------
step "5. Directories, sudo rules and the storage quota helper"
ensure_dir "$STACK_ROOT" 0750 "$DEPLOY_USER:$DEPLOY_USER"
ensure_dir "$BACKUP_ROOT" 0750 "$DEPLOY_USER:$DEPLOY_USER"
write_file "$QUOTA_HELPER" 0755 root:root <<'EOF'
#!/bin/sh
# onhost-penpot-quota <stack> <GB>: one XFS project per Penpot stack over its two volumes (docs/runbooks/penpot.md)
set -eu
stack="$1"; gb="$2"
case "$stack" in penpot-[a-z0-9]*) ;; *) echo "invalid stack" >&2; exit 2 ;; esac
case "$gb" in ''|*[!0-9]*) echo "invalid size" >&2; exit 2 ;; esac
mnt=$(df --output=target /var/lib/docker | tail -n 1)
id=$(( $(printf '%s' "$stack" | cksum | cut -d' ' -f1) % 2000000000 + 1000 ))
for v in "${stack}_penpot_assets" "${stack}_penpot_postgres_v15"; do
  dir=$(docker volume inspect -f '{{.Mountpoint}}' "$v")
  xfs_quota -x -c "project -s -p $dir $id" "$mnt"
done
xfs_quota -x -c "limit -p bhard=${gb}g $id" "$mnt"
EOF
SUDOERS="/etc/sudoers.d/onhost-penpot"
SUDO_TMP="$(mktemp)"
printf '%s ALL=(root) NOPASSWD: /usr/bin/systemctl reload caddy, %s\n' "$DEPLOY_USER" "$QUOTA_HELPER" >"$SUDO_TMP"
if [ "$DRY_RUN" -eq 0 ] && command -v visudo >/dev/null 2>&1; then
  if ! visudo -cf "$SUDO_TMP" >/dev/null; then rm -f "$SUDO_TMP"; die "the generated sudoers rule is invalid"; fi
fi
write_file "$SUDOERS" 0440 root:root <"$SUDO_TMP"
rm -f "$SUDO_TMP"
if [ "$DRY_RUN" -eq 0 ]; then
  docker_fs="$(findmnt -n -o FSTYPE,OPTIONS --target /var/lib/docker 2>/dev/null || true)"
  if printf '%s' "$docker_fs" | grep -q '^xfs' && printf '%s' "$docker_fs" | grep -Eq 'prjquota|pquota'; then
    log "  /var/lib/docker is XFS with project quota: the quota helper will work"
  else
    msg="/var/lib/docker is not an XFS filesystem with prjquota ($docker_fs): the plan's storage is only measured, not limited (docs/runbooks/penpot.md 'Storage quota')"
    if [ "$REQUIRE_QUOTA" -eq 1 ]; then die "$msg"; else warn "$msg"; fi
  fi
fi

# ---- 6. firewall ------------------------------------------------------------------------------------------------------
step "6. Firewall (ufw): 80/443 open, SSH from the allowed addresses only"
if [ "$FIREWALL" -eq 0 ]; then
  log "  skipped (--no-firewall): make sure the provider's firewall allows 80/tcp, 443/tcp+udp and SSH from the control plane only"
elif [ "$DRY_RUN" -eq 1 ]; then
  log "  [dry-run] ufw default deny incoming / allow outgoing; allow 80/tcp 443/tcp 443/udp; ssh ${SSH_PORT}/tcp from: ${SSH_ALLOW[*]:-anywhere}; enable"
else
  ufw default deny incoming >/dev/null
  ufw default allow outgoing >/dev/null
  if [ "$SSH_OPEN" -eq 1 ]; then
    ufw allow "${SSH_PORT}/tcp" comment 'ssh' >/dev/null
  else
    for cidr in "${SSH_ALLOW[@]}"; do ufw allow from "$cidr" to any port "$SSH_PORT" proto tcp comment 'ssh (control plane)' >/dev/null; done
  fi
  ufw allow 80/tcp comment 'caddy http (ACME HTTP-01)' >/dev/null
  ufw allow 443/tcp comment 'caddy https' >/dev/null
  ufw allow 443/udp comment 'caddy http/3' >/dev/null
  ufw status | grep -q '^Status: active' || { ufw --force enable >/dev/null; changed "ufw enabled"; }
  log "  note: the stacks publish on 127.0.0.1 only; a container port published on 0.0.0.0 would bypass ufw (verify-node.sh checks)"
fi

# ---- 7. what the operator needs next ----------------------------------------------------------------------------------
step "7. Done ($CHANGED change(s); a second run must report 0)"
if [ "$DRY_RUN" -eq 0 ]; then
  log "SSH host key fingerprints of this node (provider option ssh_fingerprint; compare with ssh-keyscan from a trusted machine):"
  for k in /etc/ssh/ssh_host_ed25519_key.pub /etc/ssh/ssh_host_ecdsa_key.pub /etc/ssh/ssh_host_rsa_key.pub; do
    if [ -r "$k" ]; then ssh-keygen -l -E sha256 -f "$k" | awk '{print "  " $2 "  " $4}'; fi
  done
  log "Provider option proxy_reload for this node: sudo -n systemctl reload caddy"
  log "Provider option quota_command (only with XFS prjquota): sudo -n $QUOTA_HELPER"
  log "Next: ./verify-node.sh --deploy-user $DEPLOY_USER --ssh-port $SSH_PORT --instances N, then docs/runbooks/penpot.md 'Building the node' step 4."
fi
