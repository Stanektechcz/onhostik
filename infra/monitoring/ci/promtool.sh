#!/usr/bin/env bash
# P12 (phase Q): check and unit-test the alert rules with the real promtool, and the Alertmanager config with amtool, on a
# throw-away CI runner (lesson provision-twice: configuration of infrastructure is proven by running the tool, not by reading).
#   bash infra/monitoring/ci/promtool.sh <empty work dir>
set -euo pipefail

work="${1:?usage: promtool.sh <empty work dir>}"
repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
PROM_VERSION=2.53.2
AM_VERSION=0.27.0
mkdir -p "$work"
cd "$work"

fetch() { # <project> <version> — the release tarball, checked against the release's own sha256sums.txt
  local project=$1 version=$2 file="$1-$2.linux-amd64.tar.gz"
  curl -fsSL "https://github.com/prometheus/$project/releases/download/v$version/$file" -o "$file"
  curl -fsSL "https://github.com/prometheus/$project/releases/download/v$version/sha256sums.txt" -o "$project.sha256"
  grep " $file\$" "$project.sha256" | sha256sum -c -
  tar -xzf "$file"
}
fetch prometheus "$PROM_VERSION"
fetch alertmanager "$AM_VERSION"
promtool="$work/prometheus-$PROM_VERSION.linux-amd64/promtool"
amtool="$work/alertmanager-$AM_VERSION.linux-amd64/amtool"

"$promtool" check rules "$repo/infra/monitoring/slo-alerts.yml"
(cd "$repo/infra/monitoring" && "$promtool" test rules slo-alerts.test.yml)

# the Alertmanager config as the monitoring VM gets it: the secret files exist there, so they exist here (empty)
mkdir -p "$work/am"
sed "s#/etc/alertmanager/#$work/am/#g" "$repo/infra/monitoring/alertmanager.yml" > "$work/am/alertmanager.yml"
: > "$work/am/oncall.token"; : > "$work/am/smtp.password"
"$amtool" check-config "$work/am/alertmanager.yml"
echo "monitoring rules and config: OK"
