-- Soft-delete (archive) support for courses and batches.
-- Deletion is replaced by status='archived' so that related data
-- (batches / students) is retained instead of being cascade-deleted.
ALTER TABLE courses ADD COLUMN status ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER name;
ALTER TABLE batches ADD COLUMN status ENUM('active','archived') NOT NULL DEFAULT 'active' AFTER name;
