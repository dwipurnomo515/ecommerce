-- Separate database for the automated test suite so tests never touch dev data.
SELECT 'CREATE DATABASE testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec
