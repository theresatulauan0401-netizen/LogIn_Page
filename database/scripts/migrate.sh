#!/usr/bin/env bash
# Applies every db/migrations/*.sql file that has not been applied yet, in name order.
# Usage:  export DATABASE_URL="postgresql://postgres:yourpassword@localhost:5432/hopedb"
#         ./db/migrate.sh
set -euo pipefail
: "${DATABASE_URL:?Set DATABASE_URL first (see db/README.md)}"
cd "$(dirname "$0")/migrations"

psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -c \
  "CREATE TABLE IF NOT EXISTS schema_migrations (filename TEXT PRIMARY KEY, applied_at TIMESTAMPTZ NOT NULL DEFAULT now());"

for f in *.sql; do
  done_already=$(psql "$DATABASE_URL" -tA -c "SELECT 1 FROM schema_migrations WHERE filename = '$f'")
  if [ "$done_already" = "1" ]; then
    echo "skip   $f"
  else
    echo "apply  $f"
    psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q --single-transaction -f "$f" \
      -c "INSERT INTO schema_migrations (filename) VALUES ('$f');"
  fi
done
echo "Done."
