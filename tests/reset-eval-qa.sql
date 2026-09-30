-- QA fixture reset: make both evaluation tests retakeable by the QA student.
-- Resolved by email, not a hardcoded id: students.id=2 belongs to a real seeded
-- student, so the QA account gets an auto-incremented id instead.
SET @qa_student := (SELECT id FROM students WHERE email = 'hariiphones83@gmail.com');

-- Keep the QA account and its two tests in batch 5 (college 2). They must stay
-- out of college 1, where faculty-dashboard / faculty-e2e assert exact counts
-- of 5 students and 3 tests. Idempotent, so a DB left over from before this
-- realignment self-heals on every run.
UPDATE students SET batch_id = 5 WHERE id = @qa_student;
UPDATE tests SET batch_id = 5 WHERE id IN (7, 8);

DELETE FROM student_answers
WHERE submission_id IN (SELECT id FROM submissions WHERE student_id = @qa_student AND test_id IN (7, 8));
DELETE FROM submissions WHERE student_id = @qa_student AND test_id IN (7, 8);
UPDATE tests SET status = 'active', start_time = NULL, end_time = NULL WHERE id IN (7, 8);
SELECT t.id, t.title, t.status,
       (SELECT COUNT(*) FROM submissions s WHERE s.test_id = t.id AND s.student_id = @qa_student) AS subs
FROM tests t WHERE t.id IN (7, 8);
