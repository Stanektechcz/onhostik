#!/usr/bin/env bash
# ONhost — install the gated deployer (TASK-0032) root-owned outside the site tree, from a verified SHA:
#   /usr/local/sbin/onhost-deploy                 ← infra/aapanel/deploy.sh at $SHA
#   /usr/local/lib/onhost-deploy/deploy-gate.php  ← infra/aapanel/deploy-gate.php at $SHA
#   /usr/local/lib/onhost-deploy/source-sha       ← $SHA (the deployer refuses a target that carries a different one)
#
# The files come out of the root-owned .git with `git show`, never from the www-writable working tree. The deployer
# is the judge of every later release, so it is held to the release rules itself:
#   * production (APP_ENV in the root-owned $ENV_FILE, fail closed): TAG must be an annotated tag whose last signature
#     is SSH, signed by a key in $DEPLOY_STATE_DIR/allowed_signers, naming itself, and pointing at $SHA;
#   * it only moves forward: a SHA that does not descend from the installed one is refused;
#   * FIRST=1 is for a host without a deployer only — refused when one is installed (it would skip the rule above).
#
#   SHA=<40-hex> [TAG=<signed tag>] [FIRST=1] bash install-deployer.sh
set -euo pipefail

SITE="${SITE:-staging.onhost.cz}"
APP_DIR="${APP_DIR:-/www/wwwroot/${SITE}}"
ENV_FILE="${ENV_FILE:-${ENV_DIR:-/etc/onhost}/app.env}"
SHA="${SHA:-}"
TAG="${TAG:-}"
FIRST="${FIRST:-0}"
DEPLOY_STATE_DIR="${DEPLOY_STATE_DIR:-/var/lib/onhost-deploy/${SITE}}"
DEPLOYER_BIN="${DEPLOYER_BIN:-/usr/local/sbin/onhost-deploy}"
DEPLOYER_LIB_DIR="${DEPLOYER_LIB_DIR:-/usr/local/lib/onhost-deploy}"
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"

die() { printf '✖ %s\n' "$*" >&2; exit 2; }
g() { GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL="${DEPLOY_STATE_DIR}/gitconfig" git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }

# APP_ENV as phpdotenv reads it; several differing definitions or none print nothing (= production, fail closed).
# Same rule as deploy-gate.php parse-env, in bash: the helper of $SHA is not trusted before $SHA is.
app_env() {
  [ -r "$1" ] || return 0
  sed -nE 's/^[[:space:]]*(export[[:space:]]+)?APP_ENV[[:space:]]*=[[:space:]]*//p' "$1" \
    | sed -E -e "s/^\"([^\"]*)\".*$/\\1/" -e "s/^'([^']*)'.*$/\\1/" -e 's/[[:space:]]+#.*$//' -e 's/[[:space:]]+$//' \
    | sort -u | awk 'NR == 1 { v = $0 } END { if (NR == 1) print v }'
}
# the same checks as deploy.sh verify_signed_tag (kept in step with it; DeployGateTest covers both)
verify_signed_tag() { # $1 = tag name, $2 = expected commit, $3 = allowed_signers
  local ref="refs/tags/$1" obj name last gh rc=0
  [ "$(g cat-file -t "$ref" 2>/dev/null)" = tag ] || { echo "tag $1 is lightweight or missing"; return 1; }
  obj="$(g cat-file tag "$ref")"
  name="$(printf '%s\n' "$obj" | awk 'NF == 0 { exit } /^tag / { print substr($0, 5); exit }')"
  [ "$name" = "$1" ] || { echo "the tag object under $ref calls itself '$name', not '$1'"; return 1; }
  last="$(printf '%s\n' "$obj" | grep -E '^-----BEGIN [A-Z ]+-----$' | tail -n 1)"
  [ "$last" = "-----BEGIN SSH SIGNATURE-----" ] || { echo "tag $1 is not SSH-signed (${last:-no signature})"; return 1; }
  [ "$(g rev-parse "${ref}^{commit}")" = "$2" ] || { echo "tag $1 does not point at $2"; return 1; }
  gh="$(mktemp -d)"
  GNUPGHOME="$gh" g -c gpg.format=ssh -c gpg.program=false -c gpg.x509.program=false -c gpg.ssh.program=ssh-keygen \
    -c gpg.ssh.allowedSignersFile="$3" verify-tag "$ref" >/dev/null 2>&1 || rc=$?
  rm -rf "$gh"
  [ "$rc" = 0 ] || { echo "tag $1 is not signed by a key in $3"; return 1; }
}
root_only() { [ -z "$(find -P "$@" -maxdepth 0 \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]; }

[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || die "SHA must be the full 40-character commit to install the deployer from"
[ -d "$APP_DIR/.git" ] || die "$APP_DIR is not a checkout"
[ -z "$(find "$APP_DIR/.git" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ] \
  || die ".git must belong to uid $DEPLOY_OWNER_UID and be writable by nobody else (chown -R root:root .git && chmod -R go-w .git)"
(umask 077; mkdir -p "$DEPLOY_STATE_DIR") && chmod 700 "$DEPLOY_STATE_DIR"
[ -f "$DEPLOY_STATE_DIR/gitconfig" ] || (umask 077; printf '[safe]\n\tdirectory = %s\n' "$APP_DIR" > "$DEPLOY_STATE_DIR/gitconfig")

g cat-file -e "${SHA}^{commit}" 2>/dev/null || die "$SHA is not in $APP_DIR (git fetch --tags origin first)"
installed="$(cat "$DEPLOYER_LIB_DIR/source-sha" 2>/dev/null || true)"
if [ "$FIRST" = 1 ]; then
  [ -z "$installed" ] && [ ! -e "$DEPLOYER_BIN" ] || die "a deployer is already installed (${installed:-$DEPLOYER_BIN}): FIRST=1 is for a first install only; a newer SHA installs without it"
elif [ -z "$installed" ]; then
  die "no deployer is installed yet: FIRST=1 SHA=$SHA bash $0"
else
  g merge-base --is-ancestor "$installed" "$SHA" 2>/dev/null || die "$SHA does not descend from the installed deployer $installed (no downgrades)"
fi

case "$(app_env "$ENV_FILE")" in
  staging|local|testing) ;;
  *) # production, or an environment file that does not say clearly: the judge comes only from the owner's signed tag
    [ -n "$TAG" ] || die "production ($ENV_FILE): TAG=<the owner's signed release tag pointing at $SHA> is required"
    signers="$DEPLOY_STATE_DIR/allowed_signers"
    [ -f "$signers" ] && root_only "$signers" || die "no trusted $signers (root-owned, the owner's SSH public key)"
    why="$(verify_signed_tag "$TAG" "$SHA" "$signers")" || die "$why"
    ;;
esac

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
g show "${SHA}:infra/aapanel/deploy.sh" > "$work/deploy.sh" || die "infra/aapanel/deploy.sh is not in $SHA"
g show "${SHA}:infra/aapanel/deploy-gate.php" > "$work/deploy-gate.php" || die "infra/aapanel/deploy-gate.php is not in $SHA"
bash -n "$work/deploy.sh" || die "deploy.sh at $SHA does not parse"

mkdir -p "$(dirname "$DEPLOYER_BIN")"
install -d -m 0755 "$DEPLOYER_LIB_DIR"
install -m 0755 "$work/deploy.sh" "$DEPLOYER_BIN.new" && mv -f "$DEPLOYER_BIN.new" "$DEPLOYER_BIN"
install -m 0644 "$work/deploy-gate.php" "$DEPLOYER_LIB_DIR/deploy-gate.php"
(umask 022; printf '%s\n' "$SHA" > "$DEPLOYER_LIB_DIR/source-sha.new") && mv -f "$DEPLOYER_LIB_DIR/source-sha.new" "$DEPLOYER_LIB_DIR/source-sha"
echo "deployer installed from $SHA${TAG:+ (tag $TAG)}: $DEPLOYER_BIN, $DEPLOYER_LIB_DIR/{deploy-gate.php,source-sha}"
