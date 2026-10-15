# Slow queries (PostgreSQL)

Decision N7, variant B (vlna 2, 2026-10-15). Slow queries are found on the database server, not in the
application: no `DB::listen` hook and no per-query logging in Laravel. Reasons: a listener on every query adds
load (the cache store is the database too), and query bindings carry personal data (e-mails, names, addresses).
A per-request summary of query counts is postponed until after N6.

Everything below is an operator step on the database host. It is prepared, not executed; production changes need
the owner's yes like any other production action.

## 1. Settings

In `postgresql.conf` (or `ALTER SYSTEM SET …`):

```
shared_preload_libraries = 'pg_stat_statements'   # needs a restart
pg_stat_statements.track = top
log_min_duration_statement = 500                 # ms; slow log
log_parameter_max_length = 0                     # never log bind values (PII)
log_parameter_max_length_on_error = 0
```

Then, once per database, as a superuser:

```sql
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
```

`pg_stat_statements` stores normalised statements (`$1`, `$2`), never bind values.
`log_parameter_max_length = 0` keeps the slow log free of bind values as well.

Check: `SHOW log_min_duration_statement;` → `500ms`, `SHOW log_parameter_max_length;` → `0`.

## 2. Reading

Top statements by total time:

```sql
SELECT calls, round(total_exec_time) AS total_ms, round(mean_exec_time, 1) AS mean_ms, rows,
       left(query, 200) AS query
FROM pg_stat_statements
ORDER BY total_exec_time DESC
LIMIT 20;
```

Swap the `ORDER BY` for `mean_exec_time DESC` to find single slow statements. The slow log
(`duration: … ms statement: …`) is in the PostgreSQL server log.

## 3. Rules

- Do not copy slow-log lines or `pg_stat_statements` rows into tickets, chat or the repository without checking
  them for literals (statements built without bindings can still contain values).
- Do not raise `log_parameter_max_length` and do not set `log_statement = all`.
- After a fix is deployed, reset the counters: `SELECT pg_stat_statements_reset();`.
- A finding becomes a task (index, query rewrite) through the normal task flow.
