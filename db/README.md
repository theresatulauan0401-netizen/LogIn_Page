# Hope, Inc. ERP: Database Setup (no Docker)

Maintained by the DB Admin. This guide gets you from nothing to a working
local PostgreSQL database that matches the team's migrations.

> The Hope database script (HopeDB_audit.sql) uses PostgreSQL features
> (JSONB, plpgsql triggers), so the team database is **PostgreSQL**, not MySQL.

## What you need

- **PostgreSQL 14 or newer** (includes the `psql` command-line client)
- **Python 3.8+** (the runner uses only the standard library, so no `pip install`)
- **Git**

## 1. Install PostgreSQL

1. Download the installer from postgresql.org/download and run it.
2. During install, set a **password for the `postgres` user** and remember it.
   Keep the default port **5432**.
3. Make sure the `psql` command works. Open a new terminal and run:

   ```
   psql --version
   ```

   If it says "command not found", add PostgreSQL's `bin` folder to your PATH.
   On Windows this is usually `C:\Program Files\PostgreSQL\16\bin`.

## 2. Configure your local connection

From the repo root:

```
copy db\.env.example db\.env        (Windows)
cp db/.env.example db/.env          (Mac / Linux)
```

Open `db/.env` and fill in your own values, mainly `DB_PASSWORD`.
**`db/.env` is ignored by Git. Never commit it and never share your password.**

| Setting | Default | Meaning |
|---|---|---|
| DB_HOST | localhost | where PostgreSQL runs |
| DB_PORT | 5432 | PostgreSQL port |
| DB_USER | postgres | your PostgreSQL user |
| DB_PASSWORD | (empty) | your PostgreSQL password |
| DB_NAME | hope_erp | database the project uses |

## 3. Build the database

```
python db/scripts/migrate.py
```

This creates the `hope_erp` database if needed, then runs every file in
`db/migrations/` in number order. Each file runs in one transaction: if
anything fails, that file is rolled back completely. Re-running is safe:
files that were already applied are skipped.

| Command | What it does |
|---|---|
| `python db/scripts/migrate.py` | apply pending migrations |
| `python db/scripts/migrate.py --status` | list applied and pending migrations |
| `python db/scripts/migrate.py --reset` | **drop** the database and rebuild it (asks you to confirm; development only) |

After pulling new work from the team, run the migrate command again to stay in sync.

## What is in the migrations

| File | Contents |
|---|---|
| `001_hope_base_tables.sql` | the 10 Hope tables (employee, department, job, jobHistory, customer, product, priceHist, sales, salesDetail, payment) and their sample data |
| `002_audit_and_soft_delete.sql` | `is_deleted` and `stamp` columns, `audit_log`, triggers (stamp, history, no hard delete, delete-blocked-by-children) and the `<table>_active` views |

## Check that it worked

```
psql -U postgres -d hope_erp -c "SELECT count(*) FROM employee_active;"
psql -U postgres -d hope_erp -c "SELECT table_name, action, action_by FROM audit_log LIMIT 5;"
```

The first should return 32 employees. Try a soft delete and see the stamp change:

```
UPDATE employee SET is_deleted = true WHERE empno = '00015';
SELECT empno, is_deleted, stamp FROM employee WHERE empno = '00015';
```

## Migration rules

1. **Name files `NNN_short_description.sql`**, for example `003_users_and_rights.sql`.
2. **Never edit a migration after it is merged.** The runner stops with an error if
   an applied file has changed. Add a new numbered file instead.
3. **No two files may share a number.** Tell the team which number you are taking.
4. **No passwords or real data in migrations or seed files.** First passwords are
   set through a one-time set-password link.
5. **Test from scratch before opening a PR:** run `--reset` and confirm everything builds.

## Troubleshooting

| Problem | Fix |
|---|---|
| `The 'psql' command was not found` | Add PostgreSQL's `bin` folder to PATH, then open a new terminal |
| `password authentication failed` | Check `DB_USER` and `DB_PASSWORD` in `db/.env` |
| `connection refused` | Start the PostgreSQL service; check `DB_HOST` and `DB_PORT` |
| `database is being accessed by other users` | Close pgAdmin/psql windows connected to it, then retry |
| `Two migrations share the number` | Rename your newer file with the next free number |
