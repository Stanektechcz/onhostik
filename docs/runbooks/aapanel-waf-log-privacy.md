# aaPanel WAF attack log: what it keeps and how to keep less (P10, P-R73)

**Proposal, not executed.** The firewall of aaPanel (btwaf) is the owner's to change (rule of the programme: Claude never changes
btwaf, ufw, sshd or Docker of a host). This page states the finding, what was verified by reading, the options, the risk, the
decision P-R73 and the exact steps for the owner — staging first, production only through `PRODUKCE-prikazy-<date>.md`.

## Finding (staging protocol 2026-10-14, finding 2)

The attack log `/www/server/btwaf/totla_db/http_log/*.log` keeps, for every blocked request, the **whole raw request including
every header** — so also the session cookies of other sites on the same host (e.g. `onhost-session`, `XSRF-TOKEN`, a panel's
session). Whoever reads a file can replay a stolen session of a visitor who was blocked once.

## Verified by reading on staging (2026-10-13, mode S; names, counts and modes only — no content was printed)

| What | Value |
| --- | --- |
| Directory | `/www/server/btwaf/totla_db/http_log`, owner `www:www`, mode `755` |
| Files | 14 412 files, 69 MB; each is a JSON array with **one string** (the raw request); names are hashes |
| File mode | **`-rw-rw-rw-` (0666)** — readable and writable by every local user, not only root |
| Age | oldest file 2026-07-07; 12 329 files older than 7 days, 7 434 older than 30 days |
| WAF setting | `/www/server/btwaf/config.json` has `"log_save": 30` — it evidently does **not** prune `http_log` (files from July remain) |
| Rotation | no `logrotate` entry for btwaf (only `btmp`) |

ASSUMED (not verified): the production host, once installed with aaPanel and btwaf, behaves the same; btwaf offers no switch that
stores the request without headers (the panel shows none in its UI on staging; verify in the WAF's *Settings* page).

## Options

| Option | Effect | Cost / risk |
| --- | --- | --- |
| A. **Retention 7 days** (P-R73): a root cron that deletes files older than 7 days | 12 329 of 14 412 files gone on staging; at most a week of cookies at rest | none for security work: a WAF hit older than a week is not investigated from this log |
| B. **Permissions root 0600** on the files, `0700` on the directory | no other local user (site users, PHP-FPM pools of other sites) can read them | btwaf writes as `www`: new files appear `0666` again → the cron in A must also re-mode them, or the directory must stay writable for `www`; ASSUMED that btwaf keeps working with a `0700 www` directory |
| C. **Mask the headers**: a root cron that rewrites each file, replacing `Cookie:`/`Authorization:` header values with `[masked]` | the log stays useful (path, query, client) without credentials | rewriting files the WAF still appends to can race; run on files older than one hour only |
| D. Switch the WAF's request logging off | nothing at rest | loses the evidence of attacks; not recommended |

**Decision P-R73:** retention 7 days, headers not kept or masked, access root only (A + C + B as far as btwaf allows). The owner
runs it on the host; the lawyer's question about a record of processing (ROPA) is part of LG-4 only if the log is kept longer than
needed for security (with 7 days it is a technical security log).

## Steps for the owner (mode V) — staging first

Shell: aaPanel → Terminál as root, or `ssh` with the owner's key.

```bash
D=/www/server/btwaf/totla_db/http_log
# 0. look (no content): counts, ages, modes
echo "files $(ls $D | wc -l)"; find $D -type f -mtime +7 | wc -l; stat -c '%a %U:%G' $D; ls -l $D | head -3
# 1. the cron (one file, root 0644): delete after 7 days, mask cookie/authorization in files older than an hour, files 0600
cat > /etc/cron.d/onhost-btwaf-log <<'CRON'
# P10 / P-R73: the WAF attack log keeps 7 days, no cookies or credentials, root-readable only
17 * * * * root D=/www/server/btwaf/totla_db/http_log; find "$D" -type f -mtime +7 -delete; find "$D" -type f -mmin +60 -name '*.log' -exec sed -i -E 's/((Cookie|cookie|Authorization|authorization|X-Xsrf-Token|x-xsrf-token): ?)[^\\]*/\1[masked]/g' {} + ; find "$D" -type f -exec chmod 0600 {} +
CRON
chmod 0644 /etc/cron.d/onhost-btwaf-log
# 2. run it once by hand, then verify
bash -c "$(grep -v '^#' /etc/cron.d/onhost-btwaf-log | sed 's/^17 \* \* \* \* root //')"
find $D -type f -mtime +7 | wc -l                       # 0
stat -c '%a' $D/* | sort | uniq -c                      # only 600
grep -l -E '(Cookie|cookie): [^[]' $(find $D -type f -mmin +60) 2>/dev/null | wc -l   # 0 (unmasked cookies in files older than an hour)
```

**Check that the WAF still logs:** trigger a harmless blocked request on the staging site (e.g. `curl -s 'https://staging.onhost.cz/?id=1%20union%20select%201'`),
then `ls -t $D | head -1` shows a new file within a minute.

**Rollback:** `rm /etc/cron.d/onhost-btwaf-log` (deleted files do not come back — that is the point; nothing else changes).

**What to send back (no content of any file):** the three numbers of step 0 before and after, the `stat` output, the last `grep … | wc -l`, and whether a new file appeared after the test request.

## Production

The same steps in `PRODUKCE-prikazy-<date>.md`, after the staging run, with the owner's "ano" to that step. The privacy page keeps
listing the WAF cookie `server_session_<hash>` (P-R72); this log is not a cookie and is not listed there.
