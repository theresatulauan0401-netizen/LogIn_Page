-- DEV ONLY. Wipes everything so you can rebuild from migrations.
-- Run:  psql "$DATABASE_URL" -f db/scripts/reset_dev_database.sql   then   ./db/migrate.sh
-- NEVER run this against a shared or demo database.
DROP SCHEMA public CASCADE;
CREATE SCHEMA public;
