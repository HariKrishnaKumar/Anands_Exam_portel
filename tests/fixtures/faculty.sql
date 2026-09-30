-- ============================================================
-- Fixture: FACULTY PORTAL TEST DATA
-- Additive only. Safe to re-run (uses ON DUPLICATE KEY / IGNORE).
--
-- Provides:
--   * one admin-assigned faculty credential for college 1
--   * a small student roster with semester + year values
--   * three tests (id 1..3 — id 3 is the one reports specs target)
--   * submissions + PCI records so attendance/score analytics have data
--
-- Apply:
--   mysql -u root test_platform < tests/fixtures/faculty.sql
-- ============================================================

USE test_platform;

-- ------------------------------------------------------------
-- 1. Faculty credential for college 1  (password: BGSCCMALUR@563130)
--    bcrypt hash generated with PASSWORD_BCRYPT cost 10.
--    A second college exists with NO credential, so tests can prove
--    that college_id really is part of the login check.
-- ------------------------------------------------------------
INSERT INTO colleges (id, college_code, nick_name, name)
VALUES (2, 'COL000001', 'Other Tech', 'Other Institute Of Technology')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO faculty (college_id, email, name, password_hash, is_active, created_by)
VALUES (1, 'faculty@bgsmalur.edu', 'Prof. Faculty', '$2y$10$Bb3Nm7VEqCiEb/465IAJXeYejUsJVgoqeenw4NDyBlskYrZn7x3B2', 1, 1)
ON DUPLICATE KEY UPDATE
    email         = VALUES(email),
    name          = VALUES(name),
    password_hash = VALUES(password_hash),
    is_active     = 1;

-- ------------------------------------------------------------
-- 2. Existing student gets a semester + section
-- ------------------------------------------------------------
UPDATE students SET semester = 3, section = 'A' WHERE id = 1;

-- ------------------------------------------------------------
-- 3. Extra students in college 1 (batches 1, 2 and 4)
-- ------------------------------------------------------------
INSERT INTO students (id, batch_id, section, name, phone, email, college_name, roll_number, year_of_joining, semester, course_name, password_hash)
VALUES
 (2, 1, 'A', 'Ananya Sharma', '9000000002', 'ananya.student@testplatform.com',
  'BGS Institute Of Management Malur', 'ORC8542510AQ146', 2024, 3,
  'Bachelor of Commerce (BCom)', '$2y$10$kJnnStTRK3ZotVV9azBPl.XqQFP./BBBSBneJywqBMHz1HhJcce6S'),
 (3, 1, 'A', 'Rahul Verma', '9000000003', 'rahul.student@testplatform.com',
  'BGS Institute Of Management Malur', 'ORC8542510AQ147', 2024, 4,
  'Bachelor of Commerce (BCom)', '$2y$10$kJnnStTRK3ZotVV9azBPl.XqQFP./BBBSBneJywqBMHz1HhJcce6S'),
 (4, 2, 'B', 'Sneha Iyer', '9000000004', 'sneha.student@testplatform.com',
  'BGS Institute Of Management Malur', 'ORC8542510AQ148', 2025, 2,
  'Bachelor of Commerce (BCom)', '$2y$10$kJnnStTRK3ZotVV9azBPl.XqQFP./BBBSBneJywqBMHz1HhJcce6S'),
 (5, 4, 'A', 'Vikram Reddy', '9000000005', 'vikram.student@testplatform.com',
  'BGS Institute Of Management Malur', 'ORC8542510AQ149', 2026, 1,
  'Bachelor of Computer Applications (BCA)', '$2y$10$kJnnStTRK3ZotVV9azBPl.XqQFP./BBBSBneJywqBMHz1HhJcce6S')
ON DUPLICATE KEY UPDATE
    section         = VALUES(section),
    semester        = VALUES(semester),
    year_of_joining = VALUES(year_of_joining);

-- ------------------------------------------------------------
-- 3b. A student in the OTHER college — must never appear in the
--     faculty dashboard for college 1.
-- ------------------------------------------------------------
INSERT INTO courses (id, college_id, name)
VALUES (4, 2, 'Bachelor of Technology (BTech)')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO batches (id, course_id, name)
VALUES (5, 4, 'OIT_BACH_202508')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO students (id, batch_id, section, name, phone, email, college_name, roll_number, year_of_joining, semester, course_name, password_hash)
VALUES (6, 5, 'A', 'Foreign College Student', '9000000006', 'foreign.student@testplatform.com',
        'Other Institute Of Technology', 'OIT20250001', 2025, 5,
        'Bachelor of Technology (BTech)', '$2y$10$kJnnStTRK3ZotVV9azBPl.XqQFP./BBBSBneJywqBMHz1HhJcce6S')
ON DUPLICATE KEY UPDATE college_name = VALUES(college_name), semester = VALUES(semester);

-- ------------------------------------------------------------
-- 3b. Sweep stray tests in THIS college
-- ------------------------------------------------------------
-- The assertions in faculty*.spec.js are exact (kpiTests must be 3, not
-- "at least 3"), so any test left behind in college 1 — a manual run, a
-- throwaway form submission — fails every one of them. Only tests 1..3
-- belong here, so clear the rest and everything hanging off them first.
-- College 2 is deliberately untouched: other specs rely on its tests.
-- Children are removed first to satisfy the FKs (student_answers ->
-- questions -> tests).
-- ------------------------------------------------------------
DELETE sa FROM student_answers sa
  JOIN questions q ON q.id = sa.question_id
  JOIN tests    t ON t.id = q.test_id
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

DELETE q FROM questions q
  JOIN tests t ON t.id = q.test_id
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

DELETE s FROM submissions s
  JOIN tests t ON t.id = s.test_id
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

DELETE ts FROM test_sections ts
  JOIN tests t ON t.id = ts.test_id
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

DELETE pr FROM pci_records pr
  JOIN tests t ON t.id = pr.test_id
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

DELETE t FROM tests t
 WHERE t.id NOT IN (1, 2, 3)
   AND t.batch_id IN (
       SELECT b.id FROM batches b
         JOIN courses c ON c.id = b.course_id
        WHERE c.college_id = 1
   );

-- ------------------------------------------------------------
-- 4. Tests (id 3 is the target of seeded-data-check / encoding-check)
-- ------------------------------------------------------------
INSERT INTO tests (id, batch_id, title, description, duration_minutes, passing_marks, total_marks, test_type, start_time, end_time, status, created_by)
VALUES
 (1, 1, 'Unit Test 1',    'Fixture test for college analytics', 30, 40, 100, 'general', '2026-01-10 10:00:00', '2026-01-10 10:30:00', 'completed', 1),
 (2, 2, 'Unit Test 2',    'Fixture test for college analytics', 30, 40, 100, 'general', '2026-02-10 10:00:00', '2026-02-10 10:30:00', 'completed', 1),
 (3, 1, 'Mid Term Exam',  'Fixture exam for reports specs',     60, 40, 100, 'general', '2026-03-15 09:00:00', '2026-03-15 10:00:00', 'completed', 1)
ON DUPLICATE KEY UPDATE
    title = VALUES(title), status = VALUES(status), batch_id = VALUES(batch_id);

-- ------------------------------------------------------------
-- 5. Questions for test 3
-- ------------------------------------------------------------
INSERT INTO questions (id, test_id, type, question_text, options_json, correct_answer, marks, sort_order)
VALUES
 (1, 3, 'mcq', 'Fixture question 1?', '[{"key":"A","text":"One"},{"key":"B","text":"Two"},{"key":"C","text":"Three"},{"key":"D","text":"Four"}]', 'A', 1, 0),
 (2, 3, 'mcq', 'Fixture question 2?', '[{"key":"A","text":"One"},{"key":"B","text":"Two"},{"key":"C","text":"Three"},{"key":"D","text":"Four"}]', 'B', 1, 1)
ON DUPLICATE KEY UPDATE question_text = VALUES(question_text);

-- ------------------------------------------------------------
-- 6. Submissions — test 3: students 1, 2, 3 attended (batch 1);
--                   students 4, 5 are in other batches (not eligible).
--    Test 1: students 1 and 2 attended.
-- ------------------------------------------------------------
INSERT INTO submissions (id, student_id, test_id, status, evaluation_status, started_at, submitted_at, total_marks_obtained, total_marks, auto_score, manual_score, total_score, evaluated_at, evaluator_id)
VALUES
 (1, 1, 3, 'evaluated', 'evaluated', '2026-03-15 09:00:00', '2026-03-15 09:42:00', 85.00, 100.00, 55.00, 30.00, 85.00, '2026-03-15 11:00:00', 1),
 (2, 2, 3, 'evaluated', 'evaluated', '2026-03-15 09:00:00', '2026-03-15 09:51:00', 72.00, 100.00, 47.00, 25.00, 72.00, '2026-03-15 11:00:00', 1),
 (3, 3, 3, 'submitted', 'pending_manual_review', '2026-03-15 09:00:00', '2026-03-15 09:58:00', NULL, 100.00, 40.00, NULL, NULL, NULL, NULL),
 (4, 1, 1, 'evaluated', 'evaluated', '2026-01-10 10:00:00', '2026-01-10 10:21:00', 60.00, 100.00, 40.00, 20.00, 60.00, '2026-01-10 12:00:00', 1),
 (5, 2, 1, 'evaluated', 'evaluated', '2026-01-10 10:00:00', '2026-01-10 10:25:00', 78.00, 100.00, 50.00, 28.00, 78.00, '2026-01-10 12:00:00', 1)
ON DUPLICATE KEY UPDATE
    status = VALUES(status), total_score = VALUES(total_score),
    auto_score = VALUES(auto_score), total_marks_obtained = VALUES(total_marks_obtained);

-- ------------------------------------------------------------
-- 7. PCI records for test 3 (feeds the admin reports marks table)
-- ------------------------------------------------------------
INSERT INTO pci_records (student_id, test_id, pci_score, mcq_score, coding_score, explanation_score)
VALUES
 (1, 3, 85.00, 90.00, 80.00, 85.00),
 (2, 3, 72.00, 75.00, 68.00, 73.00),
 (3, 3, 64.00, 70.00, 60.00, 62.00)
ON DUPLICATE KEY UPDATE pci_score = VALUES(pci_score);

SELECT 'faculty fixture applied' AS result;
