#!/usr/bin/env bash
# ONhost Penpot node: READ-ONLY verification after provision-node.sh (I-R7, TASK-0141).
#
# Changes nothing, reads no secret, sends nothing to the platform. Prints PASS / WARN / FAIL per check and exits 1 when any
# check FAILs (WARN does not fail). Run as root ON THE NODE (a few checks need root; without it they say SKIP):
#
#   sudo ./verify-node.sh --instances 5 --dns-name penpot.onhost.cz --public-ip 203.0.113.20
#
# Usage: verify-node.sh [options]
#   --instances N        how many Penpot instances this node must carry: checks RAM / CPU / disk against the sizing formula
#                        of docs/runbooks/penpot.md (default 1)
#   --deploy-user NAME   the platform's user on the node (default onhost)
#   --ssh-port N         the SSH port the platform will use (default 22)
#   --dns-name NAME      a name that must resolve to this node (e.g. penpot.onhost.cz or a stack label under it)
#   --public-ip ADDR     the address --dns-name must resolve to (default: only that it resolves)
#   --http-port N        Caddy's HTTP port (default 80), as given to provision-node.sh
#   --https-port N       Caddy's HTTPS port (default 443)
#   --caddy-bind ADDR    the address Caddy listens on, as given to provision-node.sh (127.0.0.1: no web port is opened)
#   --shared-host        the node also runs other services (a Wings game node): their listeners are WARN, not FAIL
#   --docker-host URI    the deploy user's rootless Docker socket (provider option docker_host, unix:///run/user/<uid>/docker.sock)
#   --offline            skip the checks that reach the internet (registry, Let's Encrypt)
#   -h, --help           this text
#
# Sizing formula (an ASSUMPTION of the platform, not a Penpot requirement; Penpot's docs state none):
#   RAM  >= 4 GB x N + 2 GB      (the plan's memory limits are hard and add up; 2 GB for the OS, Docker and Caddy)  FAIL below
#   vCPU >= N + 2                (2 vCPU per instance is a limit, not a reservation: 2:1 overcommit)                WARN below
#   disk >= (20 GB x N x 1.5 + 30 GB) / 0.85   (data + 50 % backups + OS/images + the 15 % headroom NodeQualification asks) FAIL below
set -uo pipefail

DEPLOY_USER="onhost"
SSH_PORT="22"
INSTANCES=1
DNS_NAME=""
PUBLIC_IP=""
OFFLINE=0
HTTP_PORT="80"
HTTPS_PORT="443"
CADDY_BIND=""
SHARED_HOST=0
DOCKER_HOST_URI=""
STACK_ROOT="/srv/onhost-penpot"
BACKUP_ROOT="/var/backups/onhost-penpot"
PROXY_SITES="/etc/caddy/onhost-penpot"
QUOTA_HELPER="/usr/local/sbin/onhost-penpot-quota"

while [ "$#" -gt 0 ]; do
  case "$1" in
    --instances) INSTANCES="${2:?}"; shift 2 ;;
    --deploy-user) DEPLOY_USER="${2:?}"; shift 2 ;;
    --ssh-port) SSH_PORT="${2:?}"; shift 2 ;;
    --dns-name) DNS_NAME="${2:?}"; shift 2 ;;
    --public-ip) PUBLIC_IP="${2:?}"; shift 2 ;;
    --http-port) HTTP_PORT="${2:?}"; shift 2 ;;
    --https-port) HTTPS_PORT="${2:?}"; shift 2 ;;
    --caddy-bind) CADDY_BIND="${2:?}"; shift 2 ;;
    --shared-host) SHARED_HOST=1; shift ;;
    --docker-host) DOCKER_HOST_URI="${2:?}"; shift 2 ;;
    --offline) OFFLINE=1; shift ;;
    -h|--help) sed -n '2,/^set -uo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "unknown option: $1 (see --help)" >&2; exit 2 ;;
  esac
done
[[ "$INSTANCES" =~ ^[0-9]+$ ]] && [ "$INSTANCES" -ge 1 ] || { echo "--instances must be a positive integer" >&2; exit 2; }
[[ "$SSH_PORT" =~ ^[0-9]{1,5}$ ]] || { echo "--ssh-port must be a port number" >&2; exit 2; }
[[ "$HTTP_PORT" =~ ^[0-9]{1,5}$ ]] && [[ "$HTTPS_PORT" =~ ^[0-9]{1,5}$ ]] || { echo "--http-port / --https-port must be port numbers" >&2; exit 2; }
[ -z "$CADDY_BIND" ] || [[ "$CADDY_BIND" =~ ^[0-9a-fA-F:.]{2,45}$ ]] || { echo "--caddy-bind is one IP address" >&2; exit 2; }
[ -z "$DOCKER_HOST_URI" ] || [[ "$DOCKER_HOST_URI" =~ ^unix:///[A-Za-z0-9._/-]{1,200}$ ]] || { echo "--docker-host must be unix:///<socket path>" >&2; exit 2; }
WEB_LOOPBACK=0
case "$CADDY_BIND" in 127.*|::1) WEB_LOOPBACK=1 ;; esac

FAILS=0; WARNS=0
pass() { printf 'PASS  %s\n' "$*"; }
warn() { printf 'WARN  %s\n' "$*"; WARNS=$((WARNS + 1)); }
fail() { printf 'FAIL  %s\n' "$*"; FAILS=$((FAILS + 1)); }
skip() { printf 'SKIP  %s\n' "$*"; }
IS_ROOT=0; [ "$(id -u)" -eq 0 ] && IS_ROOT=1
have() { command -v "$1" >/dev/null 2>&1; }

echo "== Penpot node verification: user=$DEPLOY_USER ssh-port=$SSH_PORT instances=$INSTANCES"

echo "-- system"
OS_ID=""; OS_VERSION=""
if [ -r /etc/os-release ]; then
  OS_ID="$(. /etc/os-release && printf '%s' "${ID:-}")"; OS_VERSION="$(. /etc/os-release && printf '%s' "${VERSION_ID:-}")"
fi
case "$OS_ID:$OS_VERSION" in
  debian:12|debian:13|ubuntu:24.04) pass "OS $OS_ID $OS_VERSION" ;;
  *) fail "OS '$OS_ID $OS_VERSION' is not Debian 12/13 or Ubuntu 24.04" ;;
esac
if [ "$(systemd-detect-virt -c 2>/dev/null || echo none)" != "none" ]; then warn "this is a container; a Penpot node must be a VM or bare metal (Docker in Docker is not supported)"; fi
if have timedatectl; then
  if [ "$(timedatectl show -p NTPSynchronized --value 2>/dev/null)" = "yes" ]; then pass "clock is NTP-synchronized"; else warn "clock is not NTP-synchronized (TLS issuance and the platform's timestamps need it)"; fi
fi

echo "-- capacity for $INSTANCES instance(s)"
need_ram_mb=$((INSTANCES * 4096 + 2048))
have_ram_mb="$(awk '/^MemTotal:/ {print int($2/1024)}' /proc/meminfo 2>/dev/null || echo 0)"
# the kernel reports a little less than the bought size: allow 3 % slack
if [ "$((have_ram_mb * 103 / 100))" -ge "$need_ram_mb" ]; then pass "RAM ${have_ram_mb} MB >= ${need_ram_mb} MB"; else fail "RAM ${have_ram_mb} MB < ${need_ram_mb} MB (4 GB x $INSTANCES + 2 GB)"; fi
need_cpu=$((INSTANCES + 2)); have_cpu="$(nproc 2>/dev/null || echo 0)"
if [ "$have_cpu" -ge "$need_cpu" ]; then pass "vCPU $have_cpu >= $need_cpu (2:1 overcommit; $((INSTANCES * 2 + 2)) for none)"; else warn "vCPU $have_cpu < $need_cpu (instances will contend for CPU)"; fi
# disk in GB x 100 to stay in integer arithmetic: (20*N*1.5 + 30) / 0.85
need_disk_gb=$(( ( (30 * INSTANCES + 30) * 100 + 84 ) / 85 ))
disk_target="/var/lib/docker"; [ -d "$disk_target" ] || disk_target="/"
have_disk_gb="$(df -Pk "$disk_target" | awk 'NR==2 {print int($2/1048576)}')"
if [ "$have_disk_gb" -ge "$need_disk_gb" ]; then pass "disk of $disk_target ${have_disk_gb} GB >= ${need_disk_gb} GB"; else fail "disk of $disk_target ${have_disk_gb} GB < ${need_disk_gb} GB ((20 GB x $INSTANCES x 1.5 + 30 GB) / 0.85)"; fi
free_pct="$(df -Pk "$disk_target" | awk 'NR==2 {gsub("%","",$5); print 100-$5}')"
if [ "$free_pct" -ge 15 ]; then pass "free space ${free_pct} % >= 15 % (node qualification: headroom)"; else fail "free space ${free_pct} % < 15 % (node qualification refuses the node)"; fi
if [ "$(df -Pk "$BACKUP_ROOT" 2>/dev/null | awk 'NR==2 {print $1}')" = "$(df -Pk "$disk_target" | awk 'NR==2 {print $1}')" ]; then
  warn "$BACKUP_ROOT is on the same filesystem as the stacks: a full disk loses the backups with the data (consider a second volume)"
fi

echo "-- Docker"
if have docker; then
  dv="$(docker version --format '{{.Server.Version}}' 2>/dev/null || true)"
  case "${dv%%.*}" in
    27|28|29) pass "Docker Engine $dv (adapter written for 27-29)" ;;
    "") fail "Docker is installed but the daemon does not answer" ;;
    *) warn "Docker Engine $dv is outside 27-29: the platform's version gate will hold the node until the version is verified" ;;
  esac
  if docker compose version --short >/dev/null 2>&1; then pass "docker compose plugin $(docker compose version --short)"; else fail "docker compose plugin missing (docker compose version fails)"; fi
  if systemctl is-active --quiet docker 2>/dev/null; then pass "docker service active"; else fail "docker service not active"; fi
  if systemctl is-enabled --quiet docker 2>/dev/null; then pass "docker enabled at boot"; else warn "docker not enabled at boot"; fi
  if grep -q '"max-size"' /etc/docker/daemon.json 2>/dev/null; then pass "container logs are rotated"; else warn "no log rotation in /etc/docker/daemon.json: container logs can fill the disk"; fi
else
  fail "docker is not installed"
fi

echo "-- Caddy"
if have caddy; then
  pass "Caddy $(caddy version 2>/dev/null | awk '{print $1}')"
  if systemctl is-active --quiet caddy 2>/dev/null; then pass "caddy service active"; else fail "caddy service not active"; fi
  if grep -Fxq "import ${PROXY_SITES}/*.caddy" /etc/caddy/Caddyfile 2>/dev/null; then pass "Caddyfile imports ${PROXY_SITES}/*.caddy"; else fail "Caddyfile lacks 'import ${PROXY_SITES}/*.caddy'"; fi
  if grep -Eq '^[[:space:]]*root[[:space:]]+\*[[:space:]]+/usr/share/caddy' /etc/caddy/Caddyfile 2>/dev/null; then fail "Caddyfile still serves the package default site (/usr/share/caddy)"; fi
  if [ -n "$CADDY_BIND" ]; then
    if grep -Eq "^[[:space:]]*default_bind[[:space:]]+${CADDY_BIND//./\.}([[:space:]]|\$)" /etc/caddy/Caddyfile 2>/dev/null; then pass "Caddy binds $CADDY_BIND (default_bind)"; else fail "Caddyfile has no 'default_bind $CADDY_BIND'"; fi
  fi
  for pp in "http_port:$HTTP_PORT:80" "https_port:$HTTPS_PORT:443"; do
    name="${pp%%:*}"; rest="${pp#*:}"; want="${rest%%:*}"; default="${rest##*:}"
    if [ "$want" != "$default" ] && ! grep -Eq "^[[:space:]]*${name}[[:space:]]+${want}([[:space:]]|\$)" /etc/caddy/Caddyfile 2>/dev/null; then fail "Caddyfile has no '$name $want'"; fi
  done
  if [ "$IS_ROOT" -eq 1 ]; then
    if caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1; then pass "Caddy configuration validates"; else fail "Caddy configuration does not validate"; fi
  else skip "Caddy validation needs root"; fi
else
  fail "caddy is not installed"
fi

echo "-- deploy user '$DEPLOY_USER'"
if id "$DEPLOY_USER" >/dev/null 2>&1; then
  uid="$(id -u "$DEPLOY_USER")"
  if [ "$uid" -ne 0 ]; then pass "exists, uid $uid (not root)"; else fail "$DEPLOY_USER is uid 0"; fi
  if [ -n "$DOCKER_HOST_URI" ]; then
    pass "rootless Docker ($DOCKER_HOST_URI): no docker group needed"
    if id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -qx docker; then warn "member of the docker group although Docker is rootless: that group is root on this host's system daemon"; fi
  elif id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -qx docker; then pass "member of docker"; else fail "not a member of the docker group"; fi
  if id -nG "$DEPLOY_USER" | tr ' ' '\n' | grep -Eqx 'sudo|wheel|admin'; then fail "member of a general sudo group: the platform's user may only run the two rules in /etc/sudoers.d/onhost-penpot"; else pass "no general sudo group"; fi
  home="$(getent passwd "$DEPLOY_USER" | cut -d: -f6)"
  ak="$home/.ssh/authorized_keys"
  if [ -f "$ak" ]; then
    if [ "$(stat -c '%a %U' "$ak")" = "600 $DEPLOY_USER" ]; then pass "authorized_keys 0600, owned by the user"; else fail "authorized_keys must be 0600 and owned by $DEPLOY_USER ($(stat -c '%a %U' "$ak"))"; fi
    if grep -q -E 'PRIVATE KEY' "$ak"; then fail "authorized_keys holds a PRIVATE key"; fi
    if [ "$(grep -c -E '^[^#]*(ssh-ed25519|ssh-rsa|ecdsa-sha2)' "$ak")" -eq 1 ]; then pass "exactly one authorized key"; else warn "authorized_keys does not hold exactly one key"; fi
    if grep -q 'from="' "$ak"; then pass "the key is bound to source addresses (from=)"; else warn "the key is not bound to source addresses"; fi
  else
    fail "$ak is missing: the platform cannot log in"
  fi
  if [ "$IS_ROOT" -eq 1 ]; then
    if passwd -S "$DEPLOY_USER" 2>/dev/null | awk '{print $2}' | grep -q '^L'; then pass "password login locked"; else fail "the user has a usable password (passwd -l $DEPLOY_USER)"; fi
    rules="$(sudo -l -U "$DEPLOY_USER" 2>/dev/null || true)"
    if printf '%s' "$rules" | grep -q 'systemctl reload caddy' && printf '%s' "$rules" | grep -q "$QUOTA_HELPER"; then pass "sudo: reload caddy and the quota helper"; else fail "sudo rules for reload caddy / quota helper are missing (/etc/sudoers.d/onhost-penpot)"; fi
    if printf '%s' "$rules" | grep -E 'NOPASSWD' | grep -Eq 'NOPASSWD:[[:space:]]*ALL[[:space:]]*$'; then fail "the user may run ANY command as root"; fi
    if have sshd; then
      eff="$(sshd -T -C "user=$DEPLOY_USER,host=verify,addr=127.0.0.1" 2>/dev/null || true)"
      if printf '%s' "$eff" | grep -qi '^passwordauthentication no'; then pass "sshd: password authentication off"; else warn "sshd allows password authentication (the deploy user's password is locked, but turn it off for the node)"; fi
      if printf '%s' "$eff" | grep -qi '^hostkeyalgorithms ssh-ed25519$'; then pass "sshd: host key type is ssh-ed25519 only (one fingerprint to pin)"; else warn "sshd offers several host key types: pin the fingerprint the platform's client negotiates, or rerun provision-node.sh without --keep-host-key-algorithms"; fi
      if ss -ltnH 2>/dev/null | awk '{print $4}' | grep -Eq "[:.]${SSH_PORT}\$"; then pass "sshd listens on $SSH_PORT"; else fail "nothing listens on SSH port $SSH_PORT"; fi
    fi
    docker_env=(); [ -z "$DOCKER_HOST_URI" ] || docker_env=("DOCKER_HOST=$DOCKER_HOST_URI")
    if sudo -n -u "$DEPLOY_USER" env "${docker_env[@]}" docker info >/dev/null 2>&1 || su -s /bin/sh -c "${docker_env[*]} docker info >/dev/null 2>&1" "$DEPLOY_USER"; then pass "the user can talk to the Docker daemon${DOCKER_HOST_URI:+ ($DOCKER_HOST_URI)}"; else fail "the user cannot talk to the Docker daemon (group membership needs a new login; rootless: loginctl enable-linger and the socket path)"; fi
  else
    skip "password lock, sudo rules, sshd settings and Docker access of the user need root"
  fi
else
  fail "user $DEPLOY_USER does not exist"
fi

echo "-- directories and tools"
for entry in "$STACK_ROOT:750" "$BACKUP_ROOT:750" "$PROXY_SITES:755"; do
  d="${entry%%:*}"; m="${entry##*:}"
  if [ -d "$d" ] && [ "$(stat -c '%a %U' "$d")" = "$m $DEPLOY_USER" ]; then pass "$d $m $DEPLOY_USER"; else fail "$d must exist, mode $m, owned by $DEPLOY_USER"; fi
done
for tool in flock curl; do if have "$tool"; then pass "$tool present"; else fail "$tool missing (ports lock / probe)"; fi; done
if [ -x "$QUOTA_HELPER" ] && [ "$(stat -c '%a %U' "$QUOTA_HELPER")" = "755 root" ]; then pass "$QUOTA_HELPER (root, 0755)"; else fail "$QUOTA_HELPER missing or wrong owner/mode"; fi
fsinfo="$(findmnt -n -o FSTYPE,OPTIONS --target /var/lib/docker 2>/dev/null || true)"
if printf '%s' "$fsinfo" | grep -q '^xfs' && printf '%s' "$fsinfo" | grep -Eq 'prjquota|pquota'; then pass "/var/lib/docker is XFS with project quota"; else warn "/var/lib/docker is not XFS with prjquota: the plan's storage is not enforced (doctor: 'every Penpot node limits the storage of a stack' stays WARN)"; fi

echo "-- network exposure"
if have ss; then
  exposed="$(ss -ltnH 2>/dev/null | awk '{print $4}' | grep -v -E '^(127\.|\[::1\]|::1)' | sed -E 's/.*[:.]([0-9]+)$/\1/' | sort -un | tr '\n' ' ')"
  bad=""
  for p in $exposed; do
    if [ "$WEB_LOOPBACK" -eq 0 ] && { [ "$p" = "$HTTP_PORT" ] || [ "$p" = "$HTTPS_PORT" ]; }; then continue; fi
    [ "$p" = "$SSH_PORT" ] || bad="$bad $p"
  done
  # ports 53 of the resolver stub etc. are bound to loopback addresses other than 127.0.0.1 on some images; report, do not guess
  if [ -z "$bad" ]; then pass "only Caddy's ports and $SSH_PORT listen on a non-loopback address"
  elif [ "$SHARED_HOST" -eq 1 ]; then warn "also listening on a non-loopback address:${bad} (a shared host's own services; none of them may be a Penpot stack port)"
  else fail "also listening on a non-loopback address:${bad} (the Penpot stacks must bind 127.0.0.1 only)"; fi
  if ss -ltnH 2>/dev/null | awk '{print $4}' | grep -E '[:.](19[0-9]{3})$' | grep -v -E '^(127\.0\.0\.1|\[::1\])' | grep -q .; then fail "a stack port (19001-19999) is published beyond loopback"; fi
else
  skip "ss not available"
fi
if have ufw; then
  if [ "$IS_ROOT" -eq 1 ]; then
    ufw_status="$(ufw status verbose 2>/dev/null || true)"
    if printf '%s' "$ufw_status" | grep -q '^Status: active'; then
      pass "ufw active"
      if printf '%s' "$ufw_status" | grep -q 'deny (incoming)'; then pass "ufw: default deny incoming"
      elif [ "$SHARED_HOST" -eq 1 ]; then warn "ufw default for incoming is not deny (a shared host: its owner's policy)"
      else fail "ufw default for incoming is not deny"; fi
      if [ "$WEB_LOOPBACK" -eq 0 ]; then
        for p in "$HTTP_PORT/tcp" "$HTTPS_PORT/tcp"; do if printf '%s' "$ufw_status" | grep -q "^$p "; then pass "ufw allows $p"; else fail "ufw does not allow $p"; fi; done
      fi
      if printf '%s' "$ufw_status" | grep -E "^${SSH_PORT}/tcp +ALLOW IN +Anywhere" | grep -q .; then warn "SSH port $SSH_PORT is open to the whole internet (runbook: from the control plane only)"; else pass "SSH is not open to Anywhere"; fi
    else
      warn "ufw is not active (fine only when the provider's network firewall does the same: 80, 443, SSH from the control plane)"
    fi
  else skip "ufw status needs root"; fi
else
  warn "ufw is not installed (see the note above about a provider firewall)"
fi

echo "-- internet and DNS"
if [ "$OFFLINE" -eq 1 ]; then
  skip "registry and ACME reachability (--offline)"
else
  if curl -fsS -o /dev/null --max-time 15 https://registry-1.docker.io/v2/ 2>/dev/null || [ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 https://registry-1.docker.io/v2/ 2>/dev/null)" = "401" ]; then pass "Docker Hub reachable (images are pulled by digest)"; else fail "Docker Hub not reachable from the node"; fi
  if curl -fsS -o /dev/null --max-time 15 https://acme-v02.api.letsencrypt.org/directory 2>/dev/null; then pass "Let's Encrypt reachable (Caddy certificates)"; else fail "Let's Encrypt not reachable: Caddy cannot issue certificates"; fi
fi
if [ -n "$DNS_NAME" ]; then
  resolved="$(getent ahostsv4 "$DNS_NAME" 2>/dev/null | awk 'NR==1 {print $1}')"
  if [ -z "$resolved" ]; then warn "$DNS_NAME does not resolve yet (the platform publishes <label>.penpot.onhost.cz when a stack is made)"
  elif [ -n "$PUBLIC_IP" ] && [ "$resolved" != "$PUBLIC_IP" ]; then fail "$DNS_NAME resolves to $resolved, not $PUBLIC_IP"
  else pass "$DNS_NAME resolves to $resolved"; fi
fi

echo "-- SSH host key fingerprints (for the provider option ssh_fingerprint; compare with ssh-keyscan from a trusted machine)"
for k in /etc/ssh/ssh_host_ed25519_key.pub /etc/ssh/ssh_host_ecdsa_key.pub /etc/ssh/ssh_host_rsa_key.pub; do
  if [ -r "$k" ] && have ssh-keygen; then ssh-keygen -l -E sha256 -f "$k" | awk '{print "      " $2 "  " $4}'; fi
done

echo
echo "== result: $FAILS FAIL, $WARNS WARN"
[ "$FAILS" -eq 0 ]
