-- Remove everything csv-import.spec.js created.
--
-- The faculty specs assert exact fixture counts for their college (5 students
-- / 3 tests). This spec builds a throwaway test named 'CSV Import QA Test', so
-- it has to take it back afterwards or kpiTests drifts to 4, 5, 6 ... on every
-- subsequent run. Deleting all rows with that title also clears any leftovers
-- from earlier runs that predate this cleanup.
DELETE FROM questions WHERE test_id IN (
    SELECT id FROM tests WHERE title = 'CSV Import QA Test'
);
DELETE FROM tests WHERE title = 'CSV Import QA Test';
