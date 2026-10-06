CREATE TABLE IF NOT EXISTS school_colleges (
    college_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS school_programs (
    program_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    college_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    max_year_level TINYINT UNSIGNED NOT NULL DEFAULT 4,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (college_id) REFERENCES school_colleges(college_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS school_terms (
    term_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year VARCHAR(9) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    registration_open TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_school_term (academic_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id INT UNSIGNED PRIMARY KEY,
    middle_name VARCHAR(100) NOT NULL DEFAULT '',
    suffix VARCHAR(20) NOT NULL DEFAULT '',
    mobile_number VARCHAR(25) NOT NULL DEFAULT '',
    program_id INT UNSIGNED DEFAULT NULL,
    term_id INT UNSIGNED DEFAULT NULL,
    year_level TINYINT UNSIGNED DEFAULT NULL,
    section VARCHAR(50) NOT NULL DEFAULT '',
    enrollment_type ENUM('Regular','Irregular','Transferee','Returning') DEFAULT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (program_id) REFERENCES school_programs(program_id),
    FOREIGN KEY (term_id) REFERENCES school_terms(term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_account_events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    reason VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (actor_id) REFERENCES users(user_id),
    KEY idx_student_events (student_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserve existing account IDs; missing academic details do not block sign-in.
INSERT INTO student_profiles (user_id)
SELECT user_id FROM users WHERE role = 'student'
AND user_id NOT IN (SELECT user_id FROM student_profiles);

SET @profile_column = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inquiries' AND COLUMN_NAME = 'student_profile_snapshot');
SET @profile_sql = IF(@profile_column = 0,
    'ALTER TABLE inquiries ADD COLUMN student_profile_snapshot JSON DEFAULT NULL', 'SELECT 1');
PREPARE profile_migration FROM @profile_sql;
EXECUTE profile_migration;
DEALLOCATE PREPARE profile_migration;

-- Starting catalog from CRMC public academic pages; the school can edit it.
INSERT INTO school_colleges (code, name) VALUES
('CCS', 'College of Computer Studies'), ('CTE', 'College of Teacher Education'),
('CBE', 'College of Business Education'), ('CCJE', 'College of Criminal Justice Education'),
('PSYCH', 'Psychology Program')
ON DUPLICATE KEY UPDATE code = VALUES(code);

INSERT INTO school_programs (college_id, code, name)
SELECT c.college_id, p.code, p.name FROM school_colleges c JOIN (
    SELECT 'CCS' AS college, 'BSIT' AS code, 'Bachelor of Science in Information Technology' AS name
    UNION ALL SELECT 'CTE', 'BEED', 'Bachelor of Elementary Education'
    UNION ALL SELECT 'CTE', 'BSED-ENGLISH', 'Bachelor of Secondary Education major in English'
    UNION ALL SELECT 'CTE', 'BSED-MATH', 'Bachelor of Secondary Education major in Mathematics'
    UNION ALL SELECT 'CTE', 'BSED-SCIENCE', 'Bachelor of Secondary Education major in Science'
    UNION ALL SELECT 'CTE', 'BSED-SOCIAL', 'Bachelor of Secondary Education major in Social Studies'
    UNION ALL SELECT 'CCJE', 'BSCRIM', 'Bachelor of Science in Criminology'
    UNION ALL SELECT 'PSYCH', 'BSPSYCH', 'Bachelor of Science in Psychology'
    UNION ALL SELECT 'CBE', 'BSA', 'Bachelor of Science in Accountancy'
    UNION ALL SELECT 'CBE', 'BSBA-FM', 'Bachelor of Science in Business Administration major in Financial Management'
) p ON p.college = c.code
ON DUPLICATE KEY UPDATE code = VALUES(code);
