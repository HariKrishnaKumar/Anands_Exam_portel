-- ============================================================
-- Student Test & Analysis Platform - Database Schema
-- Engine: MySQL 8.0+
-- Fluent 2 Design System
-- ============================================================

CREATE DATABASE IF NOT EXISTS test_platform
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE test_platform;

-- ------------------------------------------------------------
-- 1. ADMIN
-- ------------------------------------------------------------
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL DEFAULT 'Admin',
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admins_email (email)
) ENGINE=InnoDB;

-- Seed default admin (password: admin123)
-- Hash generated with: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
INSERT INTO admins (email, name, password_hash) VALUES
('admin@testplatform.com', 'Administrator', '$2y$10$GplGcU3j94wcRek.tkhrLeAeSlf9YyEoqOZB81R9X/pnn.1Fk4R0a');

-- ------------------------------------------------------------
-- 2. COLLEGES
-- ------------------------------------------------------------
CREATE TABLE colleges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_colleges_name (name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. COURSES (unique per college)
-- ------------------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    college_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_college_course (college_id, name),
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    INDEX idx_courses_college (college_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. BATCHES
-- ------------------------------------------------------------
CREATE TABLE batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_batches_course (course_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. STUDENTS
-- ------------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    gender ENUM('male','female','other') NOT NULL,
    college_name VARCHAR(255) NOT NULL,
    branch VARCHAR(255) NOT NULL,
    roll_number VARCHAR(100) NOT NULL,
    year_of_joining INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE RESTRICT,
    INDEX idx_students_email (email),
    INDEX idx_students_batch (batch_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. GUEST ENTRIES (QR / Guest Link)
-- ------------------------------------------------------------
CREATE TABLE guest_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    test_id INT DEFAULT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    type ENUM('guest','qr') NOT NULL DEFAULT 'guest',
    temp_data JSON DEFAULT NULL,
    student_id INT DEFAULT NULL,
    status ENUM('pending','linked','expired') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_guest_token (token),
    INDEX idx_guest_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. TESTS
-- ------------------------------------------------------------
CREATE TABLE tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INT NOT NULL DEFAULT 30,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
    max_tab_switches INT DEFAULT NULL COMMENT 'NULL = unlimited, just recorded',
    shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batch_id) REFERENCES batches(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id),
    INDEX idx_tests_batch (batch_id),
    INDEX idx_tests_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. QUESTIONS
-- ------------------------------------------------------------
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    type ENUM('mcq','coding','explanation') NOT NULL DEFAULT 'mcq',
    question_text TEXT NOT NULL,
    options_json JSON DEFAULT NULL COMMENT 'For MCQ: [{"key":"A","text":"..."},...]',
    correct_answer TEXT DEFAULT NULL COMMENT 'MCQ correct option key / code / rubric reference',
    marks INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    INDEX idx_questions_test (test_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. SUBMISSIONS
-- ------------------------------------------------------------
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    status ENUM('in_progress','submitted','evaluated') NOT NULL DEFAULT 'in_progress',
    evaluation_status ENUM('pending_manual_review','evaluated') NOT NULL DEFAULT 'pending_manual_review'
        COMMENT 'Hybrid grading: pending = awaiting manual review of coding/explanation answers',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    timer_extended_minutes INT NOT NULL DEFAULT 0,
    total_marks_obtained DECIMAL(10,2) DEFAULT NULL,
    total_marks DECIMAL(10,2) DEFAULT NULL,
    auto_score DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT 'MCQ points earned automatically at submit time',
    manual_score DECIMAL(6,2) DEFAULT NULL COMMENT 'Subjective points awarded by an admin',
    total_score DECIMAL(6,2) DEFAULT NULL COMMENT 'auto_score + manual_score once fully evaluated',
    evaluated_at DATETIME DEFAULT NULL,
    evaluator_id INT DEFAULT NULL,
    UNIQUE KEY uk_student_test (student_id, test_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_evaluator FOREIGN KEY (evaluator_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_submissions_status (status),
    INDEX idx_submissions_evalstatus (evaluation_status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. STUDENT ANSWERS
-- ------------------------------------------------------------
CREATE TABLE student_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_json JSON NOT NULL COMMENT 'MCQ: {"selected":"A"} / Coding: {"code":"..."} / Explanation: {"text":"..."}',
    marks_obtained DECIMAL(10,2) DEFAULT NULL,
    is_auto_graded TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = graded by machine (MCQ), 0 = graded by admin',
    evaluated_at DATETIME DEFAULT NULL,
    evaluation_remarks TEXT NULL COMMENT 'Evaluator feedback for coding/explanation answers',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_submission_question (submission_id, question_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. TAB SWITCH LOGS
-- ------------------------------------------------------------
CREATE TABLE tab_switch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    switch_count INT NOT NULL DEFAULT 1,
    type ENUM('switch','timer_extend') NOT NULL DEFAULT 'switch',
    metadata JSON DEFAULT NULL COMMENT 'For timer_extend: {"extended_by":5}',
    timestamp DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    INDEX idx_tab_submission (submission_id),
    INDEX idx_tab_type (type)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 12. PCI RECORDS
-- ------------------------------------------------------------
CREATE TABLE pci_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    test_id INT NOT NULL,
    pci_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '0.00 - 100.00',
    mcq_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    coding_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    explanation_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    mcq_weight DECIMAL(5,2) NOT NULL DEFAULT 40.00,
    coding_weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    explanation_weight DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_student_test_pci (student_id, test_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (test_id) REFERENCES tests(id) ON DELETE CASCADE,
    INDEX idx_pci_student (student_id)
) ENGINE=InnoDB;
