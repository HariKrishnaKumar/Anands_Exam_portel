-- QA fixture reset: make both evaluation tests retakeable by student id 2
DELETE FROM student_answers
WHERE submission_id IN (SELECT id FROM submissions WHERE student_id = 2 AND test_id IN (7, 8));
DELETE FROM submissions WHERE student_id = 2 AND test_id IN (7, 8);
UPDATE tests SET status = 'active', start_time = NULL, end_time = NULL WHERE id IN (7, 8);
SELECT t.id, t.title, t.status,
       (SELECT COUNT(*) FROM submissions s WHERE s.test_id = t.id AND s.student_id = 2) AS subs
FROM tests t WHERE t.id IN (7, 8);
