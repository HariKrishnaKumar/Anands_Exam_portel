-- Promotion fixtures for promote-no-duplicates.spec.js.
--
-- promoteCohort() advances every student in the promoted batch by one
-- semester, but batch 9 (seeded by stress-fixture.sql) ships with no students
-- at all — so the student half of the feature would go completely untested.
-- This seeds exactly one student into batch 9 to close that gap.
--
-- Batch 9 is course 4 (BTech) under college 2, so this student:
--   * never appears in the college-1 faculty rosters that faculty*.spec.js
--     assert exact row counts against (dashboard.php builds them with
--     "WHERE c.college_id = ?")
--   * never appears in seeded-data-check.spec.js, which only counts the marks
--     for test_id=3 and this student has no submissions
--
-- Idempotent, and deliberately resets semester to 3 on every run so repeated
-- promotions cannot walk the student up to the course ceiling and stick there.

INSERT INTO students (
    batch_id, section, name, phone, email, college_name,
    roll_number, year_of_joining, semester, course_name, password_hash
) VALUES (
    9, 'A', 'Promote QA Student', '9000000001', 'promote.qa@student.test',
    'Other Institute Of Technology', 'PROMOTEQA01', 2025, 3,
    'Bachelor of Technology (BTech)',
    '$2y$10$aR14dWlAIeY8l2KvldZZlOggRO9PGEYKuIANQWSO.WXZgZmeVnlUK'
)
ON DUPLICATE KEY UPDATE
    batch_id   = VALUES(batch_id),
    section    = VALUES(section),
    semester   = VALUES(semester),
    college_name = VALUES(college_name);
