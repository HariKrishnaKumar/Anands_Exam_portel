-- ============================================================
-- CLEANUP: Remove all students, tests, and non-BGS data
-- Keeps: BGS Institute Of Management and its courses/batches
-- ============================================================

USE test_platform;

-- Disable foreign key checks for faster truncation
SET FOREIGN_KEY_CHECKS = 0;

-- ─── 1. DELETE ALL STUDENT/TEST TRANSACTIONAL DATA ──────────
TRUNCATE TABLE student_answers;
TRUNCATE TABLE tab_switch_logs;
TRUNCATE TABLE pci_records;
TRUNCATE TABLE submissions;
TRUNCATE TABLE unverified_students;
TRUNCATE TABLE students;
TRUNCATE TABLE guest_entries;

-- ─── 2. DELETE ALL TESTS AND QUESTIONS ─────────────────────
TRUNCATE TABLE questions;
TRUNCATE TABLE test_sections;
TRUNCATE TABLE tests;

-- ─── 3. DELETE NON-BGS COLLEGE DATA ────────────────────────
-- Remove QA College (id=1) and its courses/batches
DELETE FROM college_batches WHERE college_id = 1;
DELETE FROM college_streams WHERE college_id = 1;
DELETE FROM courses WHERE college_id = 1;
DELETE FROM batches WHERE course_id IN (SELECT id FROM courses WHERE college_id = 1);
DELETE FROM colleges WHERE id = 1;

-- ─── 4. RESET AUTO INCREMENT COUNTERS ─────────────────────
ALTER TABLE students AUTO_INCREMENT = 1;
ALTER TABLE unverified_students AUTO_INCREMENT = 1;
ALTER TABLE tests AUTO_INCREMENT = 1;
ALTER TABLE questions AUTO_INCREMENT = 1;
ALTER TABLE submissions AUTO_INCREMENT = 1;
ALTER TABLE student_answers AUTO_INCREMENT = 1;
ALTER TABLE tab_switch_logs AUTO_INCREMENT = 1;
ALTER TABLE pci_records AUTO_INCREMENT = 1;
ALTER TABLE guest_entries AUTO_INCREMENT = 1;
ALTER TABLE test_sections AUTO_INCREMENT = 1;
ALTER TABLE colleges AUTO_INCREMENT = 1;
ALTER TABLE courses AUTO_INCREMENT = 1;
ALTER TABLE batches AUTO_INCREMENT = 1;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ─── VERIFY ────────────────────────────────────────────────
SELECT '=== REMAINING DATA ===' AS status;
SELECT 'colleges' as tbl, COUNT(*) as cnt FROM colleges UNION ALL
SELECT 'courses', COUNT(*) FROM courses UNION ALL
SELECT 'batches', COUNT(*) FROM batches UNION ALL
SELECT 'students', COUNT(*) FROM students UNION ALL
SELECT 'tests', COUNT(*) FROM tests UNION ALL
SELECT 'questions', COUNT(*) FROM questions UNION ALL
SELECT 'admins', COUNT(*) FROM admins;
