-- OTP Verification System for Student Signup
-- Run: mysql -u root test_platform < sql/migration_otp.sql

ALTER TABLE students
  ADD COLUMN email_verified   TINYINT(1)  NOT NULL DEFAULT 0  AFTER password_hash,
  ADD COLUMN otp_hash         VARCHAR(64) DEFAULT NULL         AFTER email_verified,
  ADD COLUMN otp_expires_at   DATETIME    DEFAULT NULL         AFTER otp_hash,
  ADD INDEX idx_email_verified (email_verified);
