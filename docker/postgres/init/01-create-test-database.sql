-- Runs once, when the Postgres data volume is first created.
--
-- The test suite runs against PostgreSQL rather than SQLite, because DevLab's
-- schema depends on jsonb, GIN indexes, partial unique indexes and CHECK
-- constraints. See phpunit.xml for the reasoning.
--
-- Separate database, so `php artisan test` cannot truncate development data.

SELECT 'CREATE DATABASE devlab_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'devlab_testing')\gexec

GRANT ALL PRIVILEGES ON DATABASE devlab_testing TO CURRENT_USER;
