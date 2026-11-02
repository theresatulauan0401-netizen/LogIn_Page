# Team Project Database

## Prerequisites
- PostgreSQL 15+ (https://www.postgresql.org/download/) — remember the password you set for the `postgres` user
- Git (Windows users: use **Git Bash** for all commands below)
- Make sure `psql` works: run `psql --version`

## Setup (first time only)
1. Clone the repo:
   git clone https://github.com/<org>/project-db.git
   cd project-db
2. Create your env file:
   cp .env.example .env
   Then open `.env` and set your own DB_PASSWORD.
3. Create the database:
   createdb -U postgres team_project
4. Run migrations:
   ./db/migrate.sh
5. (Optional) Load sample data:
   psql -U postgres -d team_project -f db/seeds/sample_data.sql

## Daily workflow
- Before you start working: `git pull` then `./db/migrate.sh`
- Verify: `psql -U postgres -d team_project -c "\dt"`

## Making a schema change
1. Create a NEW file in `db/migrations/` named `YYYYMMDD_HHMM_description.sql`
2. Write only the change (e.g. `ALTER TABLE users ADD COLUMN phone VARCHAR(20);`)
3. Run `./db/migrate.sh` to test locally
4. Commit and push, then open a Pull Request

## Rules
- NEVER edit or delete a migration that's already been merged. Add a new one instead.
- NEVER commit `.env` or passwords.
- One logical change per migration file.

## Troubleshooting
| Problem | Fix |
|---|---|
| `psql: command not found` | Add PostgreSQL's `bin` folder to your PATH |
| `password authentication failed` | Check DB_PASSWORD in `.env` |
| `database does not exist` | Run step 3 again |
| `permission denied: ./db/migrate.sh` | `chmod +x db/migrate.sh` |
| A migration failed halfway | Fix the SQL file (if not yet merged) and rerun; it rolls back automatically |
