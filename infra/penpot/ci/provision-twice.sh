#!/usr/bin/env bash
# CI (deploy-scripts job, J2 / TASK-0154): a REAL run of provision-node.sh on the throw-away GitHub runner, the way staging Path A
# runs it (shared host with Docker already running, Caddy on loopback 8081/8444 behind a front proxy with local certificates,
# rootless Docker for the deploy user, no firewall or sshd change). Asserts what the dry-run tests cannot: the second run reports
# 0 changes, the deploy user never ends up in the docker group (and is taken out of it by the next run if something put it
# there), Caddy listens only on loopback with local_certs. Needs root (sudo) and network (apt). Never run this on a real server.
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK="${1:-$(mktemp -d)}"
mkdir -p "$WORK"
[ "$(id -u)" -eq 0 ] || { echo "run as root (sudo) on a throw-away machine only" >&2; exit 2; }
[ "${CI:-}" = "true" ] || { echo "CI only: this script changes the machine it runs on" >&2; exit 2; }

ssh-keygen -q -t ed25519 -N '' -C 'onhost-ci' -f "$WORK/deploy" </dev/null
FLAGS=(--ssh-pubkey-file "$WORK/deploy.pub" --ssh-allow 127.0.0.1 --ssh-allow ::1 --deploy-user onhost --shared-host
  --no-docker-group --local-certs --caddy-bind 127.0.0.1 --http-port 8081 --https-port 8444 --keep-host-key-algorithms --no-firewall)

fail() { echo "FAIL: $*" >&2; exit 1; }
run() { # run N: the script, its log, its rc
  local n="$1" rc=0
  bash "$HERE/provision-node.sh" "${FLAGS[@]}" >"$WORK/run$n.log" 2>&1 || rc=$?
  echo "run $n: rc=$rc, $(grep -E '^== 7\.' "$WORK/run$n.log" || echo 'no summary')"
  [ "$rc" -eq 0 ] || { tail -n 40 "$WORK/run$n.log" >&2; fail "run $n exited $rc"; }
}
in_docker() { id -nG onhost | tr ' ' '\n' | grep -qx docker; }

run 1
run 2
grep -q '^== 7\. Done (0 change(s)' "$WORK/run2.log" || { grep -E 'changed:|installed:' "$WORK/run2.log" >&2 || true; fail "the second run changed something"; }
in_docker && fail "the deploy user is in the docker group after --no-docker-group"
grep -Eq '^[[:space:]]*local_certs$' /etc/caddy/Caddyfile || fail "the Caddyfile has no local_certs"
grep -Eq '^[[:space:]]*default_bind 127\.0\.0\.1$' /etc/caddy/Caddyfile || fail "the Caddyfile does not bind loopback"
ss -ltnH | awk '{print $4}' | grep -Eq '^127\.0\.0\.1:8081$' || fail "Caddy does not listen on 127.0.0.1:8081"
if ss -ltnH | awk '{print $4}' | grep -Eq '^(0\.0\.0\.0|\*|\[::\]):(8081|8444)$'; then fail "Caddy listens on a public address"; fi
grep -q 'docker-host unix:///run/user/' "$WORK/run2.log" || fail "the next step does not name the rootless socket"

# something (a package, an operator, an older script) puts the user into docker: the next run takes it out, the one after is quiet
usermod -aG docker onhost
run 3
grep -q 'removed from docker' "$WORK/run3.log" || fail "run 3 did not take the user out of the docker group"
in_docker && fail "the deploy user is still in the docker group after run 3"
run 4
grep -q '^== 7\. Done (0 change(s)' "$WORK/run4.log" || fail "run 4 changed something"

echo "OK: provision-node.sh is idempotent (0 changes on re-run), keeps the deploy user out of docker, Caddy on loopback with local_certs"
