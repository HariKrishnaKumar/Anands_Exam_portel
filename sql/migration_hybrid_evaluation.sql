-- ============================================================
-- MIGRATION: Hybrid Test Evaluation (auto MCQ + manual subjective)
-- Run ONCE against an existing database (order #11 in this project's
-- migration sequence). Fresh installs get the same columns via
-- schema.sql and must NOT run this file.
--
-- Adds:
--   submissions.evaluation_status  -> 'pending_manual_review' | 'evaluated'
--   submissions.auto_score         -> MCQ points earned at submit time
--   submissions.manual_score       -> admin-awarded subjective points
--   submissions.total_score        -> auto_score + manual_score
--   submissions.evaluated_at       -> when evaluation completed
--   submissions.evaluator_id       -> admins.id who graded it
--   student_answers.is_auto_graded -> 1 = machine graded (MCQ)
--   student_answers.evaluation_remarks -> evaluator feedback per question
-- Backfills existing rows so nothing already submitted is lost.
-- ============================================================

-- 1) submissions: new columns -----------------------------
ALTER TABLE submissions
    ADD COLUMN evaluation_status ENUM('pending_manual_review','evaluated')
        NOT NULL DEFAULT 'pending_manual_review' AFTER status,
    ADD COLUMN auto_score DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER total_marks,
    ADD COLUMN manual_score DECIMAL(6,2) NULL DEFAULT NULL AFTER auto_score,
    ADD COLUMN total_score DECIMAL(6,2) NULL DEFAULT NULL AFTER manual_score,
    ADD COLUMN evaluated_at DATETIME NULL DEFAULT NULL AFTER total_score,
    ADD COLUMN evaluator_id INT NULL DEFAULT NULL AFTER evaluated_at,
    ADD INDEX idx_submissions_evalstatus (evaluation_status),
    ADD CONSTRAINT fk_submissions_evaluator
        FOREIGN KEY (evaluator_id) REFERENCES admins(id) ON DELETE SET NULL;

-- 2) student_answers: new columns -------------------------
ALTER TABLE student_answers
    ADD COLUMN is_auto_graded TINYINT(1) NOT NULL DEFAULT 1 AFTER marks_obtained,
    ADD COLUMN evaluation_remarks TEXT NULL AFTER evaluated_at;

-- 3) Backfill A: flag which answers are machine-gradable --
UPDATE student_answers sa
JOIN questions q ON q.id = sa.question_id
SET sa.is_auto_graded = IF(q.type = 'mcq', 1, 0);

-- 4) Backfill B: auto-grade ungraded MCQ answers for any test that was
--      actually finished (submitted/evaluated). Correct = full marks,
--      wrong/blank = 0. Admin overrides (marks_obtained already set) kept.
UPDATE student_answers sa
JOIN questions q ON q.id = sa.question_id
JOIN submissions s ON s.id = sa.submission_id
SET sa.marks_obtained = IF(
        JSON_UNQUOTE(JSON_EXTRACT(sa.answer_json, '$.selected')) <=> q.correct_answer,
        q.marks, 0),
    sa.evaluated_at = NOW()
WHERE q.type = 'mcq'
  AND sa.marks_obtained IS NULL
  AND s.status IN ('submitted','evaluated');

-- 5) Backfill C: classify each submission + mirror totals -
--      Pure-MCQ tests that were submitted become evaluated instantly.
--      Hybrid tests with ungraded subjective answers go to the pending queue.
--      NOTE: computed inside a derived table so every condition reads
--      PRE-update values (multi-column SET order becomes irrelevant).
UPDATE submissions s
JOIN (
    SELECT s2.id,
           COALESCE(agg.a_pts, 0)          AS a_pts,
           COALESCE(agg.m_pts, 0)          AS m_pts,
           COALESCE(agg.ungraded_subj, 0)  AS ungraded_subj,
           qc.max_subj,
           qc.max_all,
           s2.status                       AS orig_status,
           s2.total_marks_obtained         AS orig_obtained
    FROM submissions s2
    JOIN (
        SELECT t.id AS test_id,
               COALESCE(SUM(CASE WHEN q.type <> 'mcq' THEN q.marks ELSE 0 END), 0) AS max_subj,
               COALESCE(SUM(q.marks), 0) AS max_all
        FROM tests t
        LEFT JOIN questions q ON q.test_id = t.id
        GROUP BY t.id
    ) qc ON qc.test_id = s2.test_id
    LEFT JOIN (
        SELECT sa.submission_id,
               SUM(CASE WHEN q.type = 'mcq' THEN COALESCE(sa.marks_obtained, 0) ELSE 0 END) AS a_pts,
               SUM(CASE WHEN q.type <> 'mcq' THEN COALESCE(sa.marks_obtained, 0) ELSE 0 END) AS m_pts,
               SUM(CASE WHEN q.type <> 'mcq' AND sa.marks_obtained IS NULL THEN 1 ELSE 0 END) AS ungraded_subj
        FROM student_answers sa
        JOIN questions q ON q.id = sa.question_id
        GROUP BY sa.submission_id
    ) agg ON agg.submission_id = s2.id
    WHERE s2.status IN ('submitted', 'evaluated')
) calc ON calc.id = s.id
SET s.auto_score  = calc.a_pts,
    s.manual_score = CASE WHEN calc.orig_status = 'evaluated' THEN calc.m_pts ELSE NULL END,
    s.total_score  = CASE WHEN calc.orig_status = 'evaluated'
                          THEN COALESCE(calc.orig_obtained, ROUND(calc.a_pts + calc.m_pts, 2))
                          ELSE NULL END,
    s.evaluated_at = CASE WHEN calc.orig_status = 'evaluated' THEN NOW() ELSE NULL END,
    s.evaluation_status = CASE
        WHEN calc.orig_status = 'evaluated' THEN 'evaluated'
        WHEN calc.max_subj = 0 THEN 'evaluated'                                   -- pure MCQ
        WHEN calc.ungraded_subj = 0 THEN 'evaluated'                              -- subjective fully graded
        ELSE 'pending_manual_review'
    END,
    -- Pure-MCQ submitted tests flip legacy status + mirror final score
    s.status = CASE WHEN calc.orig_status = 'submitted' AND calc.max_subj = 0 THEN 'evaluated' ELSE calc.orig_status END,
    s.total_marks_obtained = CASE
        WHEN calc.orig_status = 'submitted' AND calc.max_subj = 0 THEN calc.a_pts
        ELSE calc.orig_obtained
    END;
