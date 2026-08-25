-- Migration: Add Batch Sections and Test Section Assignment
-- Date: 2026-08-21

-- 1. Add section column to batches table
ALTER TABLE `batches`
  ADD COLUMN `section` VARCHAR(10) DEFAULT NULL AFTER `name`;

-- 2. Composite index for batch lookups by course + section
ALTER TABLE `batches`
  ADD INDEX `idx_batches_course_section` (`course_id`, `section`);

-- 3. Create test_sections junction table
CREATE TABLE IF NOT EXISTS `test_sections` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `test_id`    INT(11) NOT NULL,
  `batch_id`   INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_test_batch` (`test_id`, `batch_id`),
  KEY `idx_ts_test` (`test_id`),
  KEY `idx_ts_batch` (`batch_id`),
  CONSTRAINT `ts_ibfk_test`  FOREIGN KEY (`test_id`)  REFERENCES `tests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ts_ibfk_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add section column to students table
ALTER TABLE `students`
  ADD COLUMN `section` VARCHAR(10) DEFAULT NULL AFTER `batch_id`;

-- 5. Add section column to unverified_students table
ALTER TABLE `unverified_students`
  ADD COLUMN `section` VARCHAR(10) DEFAULT NULL AFTER `batch_id`;

-- 6. Composite index on students for section queries
ALTER TABLE `students`
  ADD INDEX `idx_students_batch_section` (`batch_id`, `section`);
