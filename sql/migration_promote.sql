-- Migration: Student Promotion Feature
-- Adds duration_years to courses and semester_order to batches

-- 1. Add course duration (in years) to courses table
ALTER TABLE `courses` ADD COLUMN `duration_years` INT NOT NULL DEFAULT 4 AFTER `name`;

-- 2. Add semester order to batches table
ALTER TABLE `batches` ADD COLUMN `semester_order` INT DEFAULT NULL AFTER `section`;
