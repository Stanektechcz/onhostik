---
name: database-change
description: Use when adding or changing an ONHOST migration, schema, model persistence, index, constraint, backfill, or data repair.
---

# Database change

1. Read the domain ADR, model, command handler, migration history, and nearest tests.
2. Write a failing behavior or migration test before the migration.
3. Design for rolling compatibility: additive first, bounded backfill, switch reads/writes, remove later.
4. Add tenant constraints, foreign keys, indexes, and explicit handling for append-only financial records.
5. Validate only on a disposable local/test database. Ask the database reviewer to inspect locks, rollback, and backfill safety.
6. Run `brain.ps1 test`; document irreversible steps and production approval separately.
