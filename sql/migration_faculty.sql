-- ============================================================
-- Migration: FACULTY LOGIN + STUDENT SEMESTER
-- Additive only — no existing table is modified except one new
-- nullable column on `students`.
--
-- Apply:
--   mysql -u root test_platform < sql/migration_faculty.sql
-- Safe to re-run (idempotent).
-- ============================================================

USE test_platform;

-- ------------------------------------------------------------
-- 1. FACULTY (one credential set per college, assigned by admin)
--    NOT self-registered: rows are only created by the admin UI.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faculty (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Faculty',
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_faculty_college (college_id),
    UNIQUE KEY uk_faculty_email (email),
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_faculty_email (email)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. STUDENTS.SEMESTER  (nullable; existing rows keep NULL and the
--    UI treats NULL as "Not set". MySQL has no ADD COLUMN IF
--    NOT EXISTS, so guard via information_schema + PREPARE.)
-- ------------------------------------------------------------
SET @semester_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'students'
      AND COLUMN_NAME  = 'semester'
);

SET @ddl = IF(
    @semester_exists = 0,
    'ALTER TABLE students ADD COLUMN semester TINYINT NULL DEFAULT NULL AFTER year_of_joining',
    'SELECT ''semester column already present'' AS note'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. Handy composite index for the faculty dashboard joins
--    (tests -> batches -> courses -> colleges scoping)
-- ------------------------------------------------------------
SET @idx_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'tests'
      AND INDEX_NAME   = 'idx_tests_batch_status'
);

SET @ddl2 = IF(
    @idx_exists = 0,
    'ALTER TABLE tests ADD INDEX idx_tests_batch_status (batch_id, status)',
    'SELECT ''idx_tests_batch_status already present'' AS note'
);

PREPARE stmt2 FROM @ddl2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- ------------------------------------------------------------
-- 4. FACULTY LOGIN ACTIVITY LOG
--    So an admin can see WHEN each faculty member signed in and
--    from where. latitude/longitude come from the browser's
--    Geolocation API on the login page and stay NULL when the
--    user denies permission or the browser has no GPS/network fix.
--    No foreign keys: the log must survive a faculty row being
--    removed (it is an audit record).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS faculty_login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT DEFAULT NULL,
    college_id INT DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    latitude DECIMAL(10,7) DEFAULT NULL,
    longitude DECIMAL(10,7) DEFAULT NULL,
    accuracy_m INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fll_college_time (college_id, created_at),
    INDEX idx_fll_faculty (faculty_id)
) ENGINE=InnoDB;

SELECT 'migration_faculty.sql applied' AS result;
