-- QA fixtures: student account + test_live (id 7) + QA Hybrid Test (id 8)
-- Idempotent-ish: wipes and re-creates only the QA-scoped rows.

DELETE FROM student_answers WHERE submission_id IN (SELECT id FROM submissions WHERE test_id IN (7,8));
DELETE FROM tab_switch_logs WHERE submission_id IN (SELECT id FROM submissions WHERE test_id IN (7,8));
DELETE FROM pci_records WHERE test_id IN (7,8);
DELETE FROM submissions WHERE test_id IN (7,8);
DELETE FROM questions WHERE test_id IN (7,8);
DELETE FROM tests WHERE id IN (7,8);
DELETE FROM students WHERE email = 'hariiphones83@gmail.com';

-- QA rows deliberately live in an EXISTING batch that belongs to a *different*
-- college than the faculty fixtures (batch 5 -> course 4 -> college 2). Keeping
-- QA data out of college 1 is what lets faculty-dashboard/faculty-e2e assert
-- exact counts (5 students / 3 tests) without the QA account skewing them.
-- The old "QA College" inserts used ids 1/1/1, which the real seed had already
-- taken, so they were silently IGNOREd and the QA student landed in college 1.
INSERT IGNORE INTO batches (id, course_id, name) VALUES (5, 4, 'OIT_BACH_202508');

-- Student (auto id: students.id=2 already belongs to a real seeded account,
-- and students has no `gender` column — both made the original insert abort
-- before tests 7/8 were ever created)
INSERT INTO students (batch_id, name, phone, email, college_name, roll_number, year_of_joining, course_name, password_hash)
VALUES (5, 'Hari QA', '9000000000', 'hariiphones83@gmail.com', 'Other Institute Of Technology', 'QA002', 2024, 'Bachelor of Technology (BTech)',
        '$2y$10$5Lv8B0ffsTw62Yt4L1pA..b4KDCUNerKCDZudYMUCW969UeGwnG.6');

-- Test 7: pure MCQ, 10 questions, 13 marks total (7x1 + 3x2)
INSERT INTO tests (id, batch_id, title, description, duration_minutes, passing_marks, total_marks, status, created_by)
VALUES (7, 5, 'test_live', 'Pure MCQ instant-evaluation QA fixture', 30, 6, 13, 'active', 1);

INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES
(7,'mcq','Q7-01: 2+2=?','[{"key":"A","text":"3"},{"key":"B","text":"4"},{"key":"C","text":"5"},{"key":"D","text":"6"}]','A',1,1),
(7,'mcq','Q7-02: Capital of France?','[{"key":"A","text":"Berlin"},{"key":"B","text":"Paris"},{"key":"C","text":"Rome"},{"key":"D","text":"Madrid"}]','B',1,2),
(7,'mcq','Q7-03: 5 x 3?','[{"key":"A","text":"15"},{"key":"B","text":"10"},{"key":"C","text":"8"},{"key":"D","text":"53"}]','A',1,3),
(7,'mcq','Q7-04: Color of sky (day)?','[{"key":"A","text":"Green"},{"key":"B","text":"Red"},{"key":"C","text":"Blue"},{"key":"D","text":"Black"}]','C',1,4),
(7,'mcq','Q7-05: HTML stands for?','[{"key":"A","text":"Hyper Trainer Marking Language"},{"key":"B","text":"Hyper Text Markup Language"},{"key":"C","text":"High Text Machine Language"},{"key":"D","text":"Home Tool Markup Language"}]','B',1,5),
(7,'mcq','Q7-06: PHP is a ___ language.','[{"key":"A","text":"server-side"},{"key":"B","text":"client-side"},{"key":"C","text":"assembly"},{"key":"D","text":"hardware"}]','A',1,6),
(7,'mcq','Q7-07: SQL is used for?','[{"key":"A","text":"styling"},{"key":"B","text":"databases"},{"key":"C","text":"images"},{"key":"D","text":"printing"}]','B',1,7),
(7,'mcq','Q7-08: 12 / 4 = ?','[{"key":"A","text":"2"},{"key":"B","text":"4"},{"key":"C","text":"3"},{"key":"D","text":"6"}]','C',2,8),
(7,'mcq','Q7-09: Largest planet?','[{"key":"A","text":"Earth"},{"key":"B","text":"Mars"},{"key":"C","text":"Venus"},{"key":"D","text":"Jupiter"}]','D',2,9),
(7,'mcq','Q7-10: Binary of 5?','[{"key":"A","text":"100"},{"key":"B","text":"101"},{"key":"C","text":"110"},{"key":"D","text":"011"}]','B',2,10);

-- Test 8: hybrid — Q1=B correct, Q2=A correct (per evaluation.spec.js), coding, explanation
INSERT INTO tests (id, batch_id, title, description, duration_minutes, passing_marks, total_marks, status, created_by)
VALUES (8, 5, 'QA Hybrid Test', 'Hybrid pending-review QA fixture', 30, 3, 8, 'active', 1);

INSERT INTO questions (test_id, type, question_text, options_json, correct_answer, marks, sort_order) VALUES
(8,'mcq','H-Q1: 3+4?','[{"key":"A","text":"6"},{"key":"B","text":"7"},{"key":"C","text":"8"},{"key":"D","text":"9"}]','B',2,1),
(8,'mcq','H-Q2: 10-1?','[{"key":"A","text":"9"},{"key":"B","text":"8"},{"key":"C","text":"11"},{"key":"D","text":"10"}]','A',2,2),
(8,'coding','Write a function add(a,b) returning the sum.',NULL,NULL,2,3),
(8,'explanation','Explain what a loop does.',NULL,NULL,2,4);

SELECT t.id, t.title, t.status,
       (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS questions,
       (SELECT SUM(q.marks) FROM questions q WHERE q.test_id = t.id) AS total
FROM tests t WHERE t.id IN (7,8);
