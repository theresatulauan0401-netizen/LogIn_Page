#!/usr/bin/env python3
"""Hope, Inc. ERP - migration runner (tasks S1-DBA-01 / S1-DBA-02), PostgreSQL.

Runs the numbered .sql files in db/migrations/ in order, skipping the ones
already applied. Uses only the Python standard library and the `psql`
command-line client, so there is nothing to pip install and no Docker.
Each migration runs in ONE transaction: if anything fails, nothing is kept.

Usage (from the repo root):
    python db/scripts/migrate.py            # apply pending migrations
    python db/scripts/migrate.py --status   # show applied / pending
    python db/scripts/migrate.py --reset    # DROP and rebuild the database (dev only)
"""
import argparse
import hashlib
import os
import re
import shutil
import subprocess
import sys
from pathlib import Path

DB_DIR = Path(__file__).resolve().parents[1]
MIGRATIONS_DIR = DB_DIR / "migrations"
ENV_FILE = DB_DIR / ".env"
NAME_PATTERN = re.compile(r"^(\d{3})_[a-z0-9_]+\.sql$")


def load_config():
    """Read db/.env (if present); real environment variables win."""
    cfg = {
        "DB_HOST": "localhost",
        "DB_PORT": "5432",
        "DB_USER": "postgres",
        "DB_PASSWORD": "",
        "DB_NAME": "hope_erp",
    }
    if ENV_FILE.exists():
        for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, value = line.split("=", 1)
            cfg[key.strip()] = value.strip().strip('"').strip("'")
    for key in cfg:
        if key in os.environ:
            cfg[key] = os.environ[key]
    if not re.fullmatch(r"[A-Za-z0-9_]+", cfg["DB_NAME"]):
        sys.exit("DB_NAME may only contain letters, numbers and underscores.")
    return cfg


def psql(cfg, args, database=None, capture=False):
    """Run psql. The password goes in PGPASSWORD, not on the command line."""
    if shutil.which("psql") is None:
        sys.exit("The 'psql' command was not found. Install PostgreSQL and add its "
                 "bin folder to your PATH (see db/README.md, step 1).")
    cmd = ["psql", "-h", cfg["DB_HOST"], "-p", cfg["DB_PORT"], "-U", cfg["DB_USER"],
           "-d", database or cfg["DB_NAME"], "-v", "ON_ERROR_STOP=1",
           "-X", "-q", "-w"] + args
    env = dict(os.environ, PGPASSWORD=cfg["DB_PASSWORD"], PGCLIENTENCODING="UTF8")
    result = subprocess.run(cmd, env=env, capture_output=True, text=True)
    if result.returncode != 0:
        print(result.stderr.strip(), file=sys.stderr)
        sys.exit(f"PostgreSQL reported an error (exit code {result.returncode}).")
    return result.stdout if capture else None


def ensure_database(cfg):
    exists = psql(cfg, ["-At", "-c",
                        f"SELECT 1 FROM pg_database WHERE datname = '{cfg['DB_NAME']}';"],
                  database="postgres", capture=True).strip()
    if not exists:
        psql(cfg, ["-c", f'CREATE DATABASE "{cfg["DB_NAME"]}" ENCODING \'UTF8\';'],
             database="postgres")
    psql(cfg, ["-c",
               "CREATE TABLE IF NOT EXISTS schema_migrations ("
               " filename   VARCHAR(255) PRIMARY KEY,"
               " checksum   CHAR(64)     NOT NULL,"
               " applied_at TIMESTAMPTZ  NOT NULL DEFAULT now());"])


def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def list_migration_files():
    files = sorted(MIGRATIONS_DIR.glob("*.sql"))
    seen = {}
    for path in files:
        match = NAME_PATTERN.match(path.name)
        if not match:
            sys.exit(f"Bad file name: {path.name}\n"
                     "Use NNN_short_description.sql, e.g. 001_hope_base_tables.sql")
        number = match.group(1)
        if number in seen:
            sys.exit(f"Two migrations share the number {number}: "
                     f"{seen[number]} and {path.name}.\nRenumber the newer one "
                     "(this is the usual cause of merge conflicts).")
        seen[number] = path.name
    return files


def applied_migrations(cfg):
    out = psql(cfg, ["-At", "-F", "\t", "-c",
                     "SELECT filename, checksum FROM schema_migrations ORDER BY filename;"],
               capture=True)
    rows = {}
    for line in out.splitlines():
        if line.strip():
            name, checksum = line.split("\t")
            rows[name] = checksum
    return rows


def cmd_status(cfg):
    ensure_database(cfg)
    applied = applied_migrations(cfg)
    files = list_migration_files()
    if not files:
        print("No migrations found in db/migrations/ yet.")
    for path in files:
        state = "applied" if path.name in applied else "PENDING"
        print(f"  [{state:>7}] {path.name}")
    return 0


def cmd_migrate(cfg):
    ensure_database(cfg)
    applied = applied_migrations(cfg)
    files = list_migration_files()
    for path in files:
        if path.name in applied and applied[path.name] != sha256(path):
            sys.exit(f"{path.name} was already applied but has been edited.\n"
                     "Never edit a merged migration: add a new numbered one instead.")
    pending = [p for p in files if p.name not in applied]
    if not pending:
        print("Database is up to date. Nothing to apply.")
        return 0
    for path in pending:
        print(f"Applying {path.name} ...")
        # --single-transaction: the migration and its bookkeeping row succeed or fail together
        psql(cfg, ["--single-transaction", "-f", str(path), "-c",
                   "INSERT INTO schema_migrations (filename, checksum) "
                   f"VALUES ('{path.name}', '{sha256(path)}');"])
    print(f"Done. Applied {len(pending)} migration(s).")
    return 0


def cmd_reset(cfg, assume_yes):
    if not assume_yes:
        answer = input(f"This DROPS the database '{cfg['DB_NAME']}' and all its data.\n"
                       "Type the database name to continue: ")
        if answer.strip() != cfg["DB_NAME"]:
            print("Cancelled.")
            return 1
    psql(cfg, ["-c", f'DROP DATABASE IF EXISTS "{cfg["DB_NAME"]}" WITH (FORCE);'],
         database="postgres")
    print(f"Dropped {cfg['DB_NAME']}. Rebuilding from migrations ...")
    return cmd_migrate(cfg)


def main():
    parser = argparse.ArgumentParser(description="Hope, Inc. ERP migration runner (PostgreSQL)")
    parser.add_argument("--status", action="store_true", help="show applied/pending")
    parser.add_argument("--reset", action="store_true",
                        help="drop the database and rebuild it (development only)")
    parser.add_argument("--yes", action="store_true", help="skip the --reset prompt")
    args = parser.parse_args()
    cfg = load_config()
    if args.status:
        sys.exit(cmd_status(cfg))
    if args.reset:
        sys.exit(cmd_reset(cfg, args.yes))
    sys.exit(cmd_migrate(cfg))


if __name__ == "__main__":
    main()
