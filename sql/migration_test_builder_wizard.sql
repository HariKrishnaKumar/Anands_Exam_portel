-- Migration: Test Builder 3-Step Wizard
-- Date: 2026-08-21
-- Adds total_marks, test_type columns to tests table.

-- 1. Add total_marks column (auto-calculated from questions, or manually set)
ALTER TABLE `tests`
  ADD COLUMN `total_marks` INT(11) NOT NULL DEFAULT 0 AFTER `passing_marks`;

-- 2. Add test_type column for categorization
ALTER TABLE `tests`
  ADD COLUMN `test_type` VARCHAR(50) NOT NULL DEFAULT 'general' AFTER `total_marks`;

-- 3. Index for test_type filtering
ALTER TABLE `tests`
  ADD INDEX `idx_tests_type` (`test_type`);

-- 4. Add status 'scheduled' to the ENUM (for scheduled publications)
ALTER TABLE `tests`
  MODIFY COLUMN `status` ENUM('upcoming','active','paused','completed','scheduled') NOT NULL DEFAULT 'upcoming';
