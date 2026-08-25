-- ============================================================
-- Migration: students.updated_at (for "Date Modified" sort)
-- Adds an updated_at column that MySQL auto-bumps on UPDATE.
-- Existing rows are backfilled with their created_at.
-- ============================================================

-- 1. Add column (idempotent-safe: only if it does not exist)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'test_platform' AND TABLE_NAME = 'students' AND COLUMN_NAME = 'updated_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE students ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT "students.updated_at already exists — skipping" AS notice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Backfill existing rows so "Date Modified" == creation date until edited
UPDATE students SET updated_at = COALESCE(updated_at, created_at);