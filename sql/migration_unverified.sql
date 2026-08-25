-- ============================================================
-- Migration: Separate unverified students + failed login log
-- ============================================================

-- 0. Clean up any existing unverified students before schema change
DELETE FROM students WHERE email_verified = 0;

-- 1. Create unverified_students table (mirrors students without email_verified)
CREATE TABLE IF NOT EXISTS unverified_students (
    id              INT             AUTO_INCREMENT PRIMARY KEY,
    batch_id        INT             NOT NULL,
    name            VARCHAR(255)    NOT NULL,
    phone           VARCHAR(20)     NOT NULL,
    email           VARCHAR(255)    NOT NULL UNIQUE,
    gender          ENUM('male','female','other') NOT NULL,
    college_name    VARCHAR(255)    NOT NULL,
    branch          VARCHAR(255)    NOT NULL,
    roll_number     VARCHAR(100)    NOT NULL,
    year_of_joining INT             NOT NULL,
    course_name     VARCHAR(255)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    otp_hash        VARCHAR(64)     DEFAULT NULL,
    otp_expires_at  DATETIME        DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    INDEX idx_unverified_email (email),
    INDEX idx_unverified_otp_expires (otp_expires_at)
) ENGINE=InnoDB;

-- 2. Create failed_login_log table
CREATE TABLE IF NOT EXISTS failed_login_log (
    id              INT             AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255)    NOT NULL,
    student_name    VARCHAR(255)    DEFAULT NULL,
    ip_address      VARCHAR(45)     NOT NULL DEFAULT '',
    attempt_type    VARCHAR(50)     NOT NULL COMMENT 'signup_unverified, wrong_password, invalid_email, expired_otp, wrong_otp, duplicate_email',
    reason          VARCHAR(255)    DEFAULT NULL,
    attempted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_email (email),
    INDEX idx_failed_type (attempt_type),
    INDEX idx_failed_time (attempted_at)
) ENGINE=InnoDB;

-- 3. Remove OTP columns from students (all students there are now verified)
ALTER TABLE students
    DROP COLUMN email_verified,
    DROP COLUMN otp_hash,
    DROP COLUMN otp_expires_at;
