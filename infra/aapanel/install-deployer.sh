#!/usr/bin/env bash
# ONhost — install the gated deployer (TASK-0032) root-owned outside the site tree, from a verified SHA:
#   /usr/local/sbin/onhost-deploy                 ← infra/aapanel/deploy.sh at $SHA
#   /usr/local/lib/onhost-deploy/deploy-gate.php  ← infra/aapanel/deploy-gate.php at $SHA
#   /usr/local/lib/onhost-deploy/source-sha       ← $SHA (the deployer refuses a target that carries a different one)
#
# The files come out of root's repository ($DEPLOY_GIT_DIR, outside the site tree) with `git show`, never from the
# www-writable working tree. The deployer
# is the judge of every later release, so it is held to the release rules itself:
#   * production (APP_ENV in the root-owned $ENV_FILE, fail closed): TAG must be an annotated tag whose last signature
#     is SSH with nothing appended after it, signed by a key in $DEPLOY_STATE_DIR/allowed_signers, naming itself, and
#     pointing at $SHA;
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
DEPLOY_GIT_DIR="${DEPLOY_GIT_DIR:-${DEPLOY_STATE_DIR}/repo.git}"
DEPLOYER_BIN="${DEPLOYER_BIN:-/usr/local/sbin/onhost-deploy}"
DEPLOYER_LIB_DIR="${DEPLOYER_LIB_DIR:-/usr/local/lib/onhost-deploy}"
DEPLOY_OWNER_UID="${DEPLOY_OWNER_UID:-0}"

die() { printf '✖ %s\n' "$*" >&2; exit 2; }
# git on root's repository, never on a .git in the site tree (review round 2, security HIGH): $APP_DIR stays writable by
# www, so www could rename a root-owned $APP_DIR/.git after the preflight looked at it and put its own there — config
# (filters, textconv, sshCommand, url rewrites), info/attributes, alternates and hooks that root's fetch, status, diff
# and checkout would then obey, and the old global config named $APP_DIR safe.directory, which switched git's own
# ownership check off. GIT_DIR names the root-only repository under the root-only state dir, so git never looks for a
# .git in the tree; the tree is only the work tree. No system or global config is read (no safe.directory for a
# www-owned path), no hooks, no fsmonitor. What the work tree itself can still carry (a .gitattributes, a nested
# .gitignore) runs nothing without a config that names a driver, and changes only files www can write anyway.
# Identical in install.sh and install-deployer.sh.
g() { GIT_DIR="$DEPLOY_GIT_DIR" GIT_WORK_TREE="$APP_DIR" GIT_CONFIG_NOSYSTEM=1 GIT_CONFIG_GLOBAL=/dev/null git -C "$APP_DIR" -c core.hooksPath=/dev/null -c core.fsmonitor=false "$@"; }
# the repository is a real directory, owned by DEPLOY_OWNER_UID throughout and writable by nobody else. Identical in
# install.sh and install-deployer.sh.
repo_is_roots() { [ -d "$DEPLOY_GIT_DIR" ] && [ ! -L "$DEPLOY_GIT_DIR" ] && [ -z "$(find -P "$DEPLOY_GIT_DIR" \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]; }

# APP_ENV as phpdotenv reads it; several differing definitions or none print nothing (= production, fail closed).
# Same rule as deploy-gate.php parse-env, in bash: the helper of $SHA is not trusted before $SHA is.
app_env() {
  [ -r "$1" ] || return 0
  sed -nE 's/^[[:space:]]*(export[[:space:]]+)?APP_ENV[[:space:]]*=[[:space:]]*//p' "$1" \
    | sed -E -e "s/^\"([^\"]*)\".*$/\\1/" -e "s/^'([^']*)'.*$/\\1/" -e 's/[[:space:]]+#.*$//' -e 's/[[:space:]]+$//' \
    | sort -u | awk 'NR == 1 { v = $0 } END { if (NR == 1) print v }'
}
# An annotated tag object ($1, its object id — resolved ONCE by the caller, so the object verified is the object read)
# whose `tag` header is $2, whose LAST signature block is SSH with nothing but blank lines after its END line, which
# points at $3 and which a key in $4 signed. Git picks the verifier from the signature itself (a PGP or X.509 block
# would go to root's GnuPG keyring, not allowed_signers): gpg.program/gpg.x509.program are `false`, GNUPGHOME is empty.
# Git verifies only what precedes the last BEGIN line and OpenSSH drops everything from END on, so a line appended
# after END — an `Accept-Gate:` added by someone who can push tags but lacks the owner's key — passed verify-tag
# unsigned (VERIFIED with git 2.47 + OpenSSH, review round 3). Such a tag is refused, and only the signed message (the
# body before the last BEGIN line) is written to $5 for the gate to read. Kept identical in install-deployer.sh
# (DeployGateTest compares the two).
verify_signed_tag() { # $1 = tag object id, $2 = tag name, $3 = expected commit, $4 = allowed_signers, $5 = message file
  local oid=$1 obj name begin gh rc=0
  [ "$(g cat-file -t "$oid" 2>/dev/null)" = tag ] || { echo "tag $2 is lightweight or missing: production needs an annotated, signed tag"; return 1; }
  obj="$(g cat-file tag "$oid")"
  name="$(printf '%s\n' "$obj" | awk 'NF == 0 { exit } /^tag / { print substr($0, 5); exit }')"
  [ "$name" = "$2" ] || { echo "the tag object under refs/tags/$2 calls itself '$name', not '$2'"; return 1; }
  begin="$(printf '%s\n' "$obj" | grep -nE '^-----BEGIN [A-Z ]+-----$' | tail -n 1)"
  [ "${begin#*:}" = "-----BEGIN SSH SIGNATURE-----" ] || { echo "tag $2 is not SSH-signed (${begin:-no signature})"; return 1; }
  begin="${begin%%:*}"
  printf '%s\n' "$obj" | awk -v b="$begin" 'NR > b && ended && NF { bad = 1 } NR > b && $0 == "-----END SSH SIGNATURE-----" { ended = 1 } END { exit (ended && !bad) ? 0 : 1 }' \
    || { echo "tag $2 carries unsigned content after its signature (nothing may follow -----END SSH SIGNATURE-----)"; return 1; }
  [ "$(g rev-parse "${oid}^{commit}")" = "$3" ] || { echo "tag $2 does not point at $3"; return 1; }
  gh="$(mktemp -d)"
  GNUPGHOME="$gh" g -c gpg.format=ssh -c gpg.program=false -c gpg.x509.program=false -c gpg.ssh.program=ssh-keygen \
    -c gpg.ssh.allowedSignersFile="$4" verify-tag "$oid" >/dev/null 2>&1 || rc=$?
  rm -rf "$gh"
  [ "$rc" = 0 ] || { echo "tag $2 is not signed by a key in $4"; return 1; }
  printf '%s\n' "$obj" | awk -v b="$begin" 'NR >= b { exit } body { print } NF == 0 { body = 1 }' > "${5:-/dev/null}"
}
root_only() { [ -z "$(find -P "$@" -maxdepth 0 \( ! -uid "$DEPLOY_OWNER_UID" -o -perm /022 \) -print -quit)" ]; }

[[ "$SHA" =~ ^[0-9a-f]{40}$ ]] || die "SHA must be the full 40-character commit to install the deployer from"
[ -d "$APP_DIR" ] && [ ! -L "$APP_DIR" ] || die "$APP_DIR is not a directory"
repo_is_roots || die "$DEPLOY_GIT_DIR must be root's repository: owned by uid $DEPLOY_OWNER_UID throughout and writable by nobody else, outside the site tree (staging-launch.md S0/S1b)"
(umask 077; mkdir -p "$DEPLOY_STATE_DIR") && chmod 700 "$DEPLOY_STATE_DIR"

g cat-file -e "${SHA}^{commit}" 2>/dev/null || die "$SHA is not in $DEPLOY_GIT_DIR (GIT_DIR=$DEPLOY_GIT_DIR git fetch --tags origin first)"
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
    [[ "$TAG" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]{0,127}$ ]] && [[ "$TAG" != *..* ]] || die "TAG '$TAG' is not a plain tag name"
    tag_oid="$(g rev-parse -q --verify "refs/tags/${TAG}")" || die "tag $TAG is missing (git fetch --tags origin first)"
    why="$(verify_signed_tag "$tag_oid" "$TAG" "$SHA" "$signers")" || die "$why"
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
