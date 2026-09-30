-- Stress-test fixtures for stress-test.spec.js.
--
-- The suite drives the promotion API against two batches:
--   * batch 9  — a preview that must SUCCEED (HTTP 200 + promotion details)
--   * batch 10 — a batch already sitting on the course's semester ceiling,
--                so the API must refuse it (HTTP 400 "Cannot promote beyond")
--
-- Every course in this DB is duration_years = 4, i.e. 4 * 2 = 8 semesters, so
-- semester_order 8 is the ceiling. Neither batch existed before; both are seeded
-- under course 4 (BTech, college 2) so the college-1 fixtures that the faculty
-- specs assert exact counts against are left alone.
--
-- Idempotent: safe to run before every stress-test run.

INSERT INTO batches (id, course_id, name, semester_order, status)
VALUES
    (9,  4, 'OIT_BACH_202508_09', 3, 'active'),
    (10, 4, 'OIT_BACH_202508_10', 8, 'active')
ON DUPLICATE KEY UPDATE
    course_id      = VALUES(course_id),
    name           = VALUES(name),
    semester_order = VALUES(semester_order),
    status         = VALUES(status);
