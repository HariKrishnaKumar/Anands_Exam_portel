-- ============================================================
-- Migration: College Registration Wizard
-- Adds: admin roles, extended college fields, streams, batches
-- ============================================================

-- 1. Add role column to admins
ALTER TABLE admins
    ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin'
    COMMENT 'super_admin, platform_admin, admin';

-- Set existing seed admin as super_admin
UPDATE admins SET role = 'super_admin' WHERE email = 'admin@testplatform.com';

-- 1b. Add name column to admins
ALTER TABLE admins
    ADD COLUMN name VARCHAR(255) NOT NULL DEFAULT 'Admin' AFTER email;

-- Set name for seed admin
UPDATE admins SET name = 'Administrator' WHERE email = 'admin@testplatform.com';

-- 2. Extend colleges table with full registration fields
ALTER TABLE colleges
    ADD COLUMN college_code VARCHAR(20) UNIQUE COMMENT 'Auto-generated unique college ID (COL######)' AFTER id,
    ADD COLUMN nick_name VARCHAR(255) UNIQUE COMMENT 'Required unique nickname/short name' AFTER college_code,
    ADD COLUMN established_year YEAR DEFAULT NULL AFTER nick_name,
    ADD COLUMN website VARCHAR(255) DEFAULT NULL AFTER established_year,
    ADD COLUMN email VARCHAR(255) DEFAULT NULL AFTER website,
    ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER email,
    ADD COLUMN country VARCHAR(100) DEFAULT NULL AFTER address,
    ADD COLUMN state VARCHAR(100) DEFAULT NULL AFTER country,
    ADD COLUMN district VARCHAR(100) DEFAULT NULL AFTER state,
    ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER district,
    ADD COLUMN pincode VARCHAR(10) DEFAULT NULL AFTER city,
    ADD COLUMN logo VARCHAR(500) DEFAULT NULL AFTER pincode,
    ADD COLUMN description TEXT DEFAULT NULL AFTER logo,
    ADD COLUMN recognized_university VARCHAR(255) DEFAULT NULL AFTER description,
    ADD COLUMN affiliated_university VARCHAR(255) DEFAULT NULL AFTER recognized_university,
    ADD COLUMN autonomous VARCHAR(255) DEFAULT NULL AFTER affiliated_university,
    ADD COLUMN accreditation_naac TINYINT(1) NOT NULL DEFAULT 0 AFTER autonomous,
    ADD COLUMN naac_grade VARCHAR(5) DEFAULT NULL AFTER accreditation_naac,
    ADD COLUMN accreditation_nba TINYINT(1) NOT NULL DEFAULT 0 AFTER naac_grade,
    ADD COLUMN accreditation_aicte TINYINT(1) NOT NULL DEFAULT 0 AFTER accreditation_nba,
    ADD COLUMN accreditation_ugc TINYINT(1) NOT NULL DEFAULT 0 AFTER accreditation_aicte,
    ADD COLUMN status ENUM('active', 'archived') NOT NULL DEFAULT 'active' AFTER accreditation_ugc,
    ADD COLUMN updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- 3. Create college_streams table
CREATE TABLE IF NOT EXISTS college_streams (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    college_id  INT             NOT NULL,
    stream_name VARCHAR(255)    NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    UNIQUE KEY uk_college_stream (college_id, stream_name),
    INDEX idx_streams_college (college_id)
) ENGINE=InnoDB;

-- 4. Create college_batches table
CREATE TABLE IF NOT EXISTS college_batches (
    id              INT             AUTO_INCREMENT PRIMARY KEY,
    college_id      INT             NOT NULL,
    stream_id       INT             NOT NULL,
    academic_year   VARCHAR(9)      NOT NULL COMMENT 'e.g. 2024-2025',
    joining_year    INT             NOT NULL,
    joining_month   INT             NOT NULL COMMENT '1-12',
    course_duration INT             NOT NULL COMMENT 'in years',
    ending_year     INT             NOT NULL,
    batch_nick_name VARCHAR(100)    NOT NULL UNIQUE,
    status          ENUM('active', 'upcoming', 'completed') NOT NULL DEFAULT 'active',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(id) ON DELETE CASCADE,
    FOREIGN KEY (stream_id) REFERENCES college_streams(id) ON DELETE CASCADE,
    INDEX idx_cbatches_college (college_id),
    INDEX idx_cbatches_stream (stream_id)
) ENGINE=InnoDB;

-- 5. Create uploads directory placeholder
-- (PHP will create the directory at runtime)
