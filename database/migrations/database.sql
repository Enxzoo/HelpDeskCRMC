-- ============================================================
-- HELPDESKCRMC — AI-Assisted Student Inquiry and Concern
-- Management System (Cebu Roosevelt Memorial Colleges)
-- Database: MySQL 8.0+ (InnoDB, utf8mb4)
-- ============================================================

CREATE DATABASE IF NOT EXISTS helpdeskcrmc
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE helpdeskcrmc;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. OFFICES
-- School offices that receive/handle concerns (e.g. Registrar,
-- Cashier, Guidance, IT). Staff belong to an office and
-- knowledge-base entries are tied to an office.
-- ------------------------------------------------------------
CREATE TABLE offices (
    office_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_name     VARCHAR(100) NOT NULL UNIQUE,
    description     VARCHAR(255) NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. CONCERN TYPES
-- Concern types are grouped per office (student selects office
-- then concern type before describing the concern to Ben).
-- ------------------------------------------------------------
CREATE TABLE concern_types (
    concern_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id       INT UNSIGNED NOT NULL,
    type_name       VARCHAR(100) NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_concern_types_office
        FOREIGN KEY (office_id) REFERENCES offices(office_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_office_type (office_id, type_name)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 3. USERS
-- Single table for Student, Staff, and Admin accounts,
-- differentiated by role. Staff accounts reference the office
-- they belong to.
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role            ENUM('student','staff','admin') NOT NULL,
    office_id       INT UNSIGNED NULL,  -- required for staff, NULL for student/admin
    student_number  VARCHAR(30) NULL UNIQUE,   -- for students only
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,     -- store via password_hash() in PHP
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_office
        FOREIGN KEY (office_id) REFERENCES offices(office_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. KNOWLEDGE BASE
-- Verified information Ben retrieves from. Entries belong to
-- an office and (optionally) a specific concern type.
-- ------------------------------------------------------------
CREATE TABLE knowledge_base (
    kb_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    office_id       INT UNSIGNED NOT NULL,
    concern_type_id INT UNSIGNED NULL,
    title           VARCHAR(150) NOT NULL,
    question_text   TEXT NOT NULL,     -- sample question / query the entry answers
    answer_text     TEXT NOT NULL,     -- verified answer/policy content
    keywords        TEXT NULL,         -- optional extra terms to aid TF-IDF matching
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NULL, -- admin user_id who added it
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_kb_office
        FOREIGN KEY (office_id) REFERENCES offices(office_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_kb_concern_type
        FOREIGN KEY (concern_type_id) REFERENCES concern_types(concern_type_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_kb_created_by
        FOREIGN KEY (created_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FULLTEXT KEY ft_kb_search (question_text, answer_text, keywords)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. INQUIRIES
-- The core concern/inquiry submitted by a student, directed to
-- an office. Tracks status through its lifecycle.
-- ------------------------------------------------------------
CREATE TABLE inquiries (
    inquiry_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    office_id       INT UNSIGNED NOT NULL,
    concern_type_id INT UNSIGNED NULL,
    assigned_staff_id INT UNSIGNED NULL,      -- set by admin on assignment
    subject         VARCHAR(150) NOT NULL,
    description     TEXT NOT NULL,
    status          ENUM('Pending','In Progress','Resolved') NOT NULL DEFAULT 'Pending',
    source          ENUM('ai_escalation','general_inquiry') NOT NULL DEFAULT 'general_inquiry',
    resolved_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inquiries_student
        FOREIGN KEY (student_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_inquiries_office
        FOREIGN KEY (office_id) REFERENCES offices(office_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inquiries_concern_type
        FOREIGN KEY (concern_type_id) REFERENCES concern_types(concern_type_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inquiries_staff
        FOREIGN KEY (assigned_staff_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_inquiries_status (status),
    INDEX idx_inquiries_student (student_id),
    INDEX idx_inquiries_staff (assigned_staff_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. AI CONVERSATION MESSAGES (Ben's chat log with the student)
-- Keeps conversation context so follow-up questions work, and
-- provides the transcript that gets summarized on escalation.
-- ------------------------------------------------------------
CREATE TABLE ai_conversations (
    conversation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      INT UNSIGNED NOT NULL,
    inquiry_id      INT UNSIGNED NULL,  -- linked once escalated to a formal inquiry
    office_id       INT UNSIGNED NULL,
    concern_type_id INT UNSIGNED NULL,
    is_resolved     TINYINT(1) NOT NULL DEFAULT 0,
    is_escalated    TINYINT(1) NOT NULL DEFAULT 0,
    started_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at        TIMESTAMP NULL,
    CONSTRAINT fk_conv_student
        FOREIGN KEY (student_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_conv_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_conv_office
        FOREIGN KEY (office_id) REFERENCES offices(office_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_conv_concern_type
        FOREIGN KEY (concern_type_id) REFERENCES concern_types(concern_type_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ai_messages (
    message_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender          ENUM('student','ben') NOT NULL,
    message_text    TEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_msg_conversation
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(conversation_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. AI MATCH LOGS
-- Records each AI / knowledge base match attempt
-- against the knowledge base.
-- ------------------------------------------------------------
CREATE TABLE ai_match_logs (
    match_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    message_id      INT UNSIGNED NULL,      -- the student message that triggered matching
    kb_id           INT UNSIGNED NULL,      -- best-matching KB entry, NULL if no match
    similarity_score DECIMAL(5,4) NULL,     -- cosine similarity score, e.g. 0.8421
    matched          TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matchlog_conversation
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(conversation_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_matchlog_message
        FOREIGN KEY (message_id) REFERENCES ai_messages(message_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_matchlog_kb
        FOREIGN KEY (kb_id) REFERENCES knowledge_base(kb_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 8. INQUIRY RESPONSES
-- Staff replies to a student's inquiry.
-- ------------------------------------------------------------
CREATE TABLE inquiry_responses (
    response_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inquiry_id      INT UNSIGNED NOT NULL,
    staff_id        INT UNSIGNED NOT NULL,
    message         TEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_response_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_response_staff
        FOREIGN KEY (staff_id) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. INQUIRY ATTACHMENTS
-- Files attached either when a student submits a concern or
-- when staff respond to one.
-- ------------------------------------------------------------
CREATE TABLE inquiry_attachments (
    attachment_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inquiry_id      INT UNSIGNED NOT NULL,
    response_id     INT UNSIGNED NULL,       -- NULL if attached at submission time
    uploaded_by     INT UNSIGNED NOT NULL,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    file_type       VARCHAR(100) NULL,
    file_size       INT UNSIGNED NULL,       -- bytes
    uploaded_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attach_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attach_response
        FOREIGN KEY (response_id) REFERENCES inquiry_responses(response_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attach_uploader
        FOREIGN KEY (uploaded_by) REFERENCES users(user_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 10. NOTIFICATIONS
-- Real-time notifications for students (status updates,
-- responses) and staff (new assignments).
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    inquiry_id      INT UNSIGNED NULL,
    title           VARCHAR(150) NOT NULL,
    message         VARCHAR(500) NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notif_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_notif_user_unread (user_id, is_read)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 11. FEEDBACK
-- Student rating/feedback submitted after a concern is
-- resolved.
-- ------------------------------------------------------------
CREATE TABLE feedback (
    feedback_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inquiry_id      INT UNSIGNED NOT NULL UNIQUE,
    student_id      INT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment         VARCHAR(500) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_inquiry
        FOREIGN KEY (inquiry_id) REFERENCES inquiries(inquiry_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_student
        FOREIGN KEY (student_id) REFERENCES users(user_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- SEED DATA (optional — remove/edit before production use)
-- ------------------------------------------------------------
INSERT INTO offices (office_name, description) VALUES
    ('Registrar', 'Handles enrollment, records, and academic documents'),
    ('Cashier', 'Handles tuition, fees, and payment concerns'),
    ('Guidance Office', 'Handles counseling and student welfare concerns'),
    ('MIS / IT Office', 'Handles system access and technical concerns');

-- Default admin account (replace password_hash value with a real
-- bcrypt/argon2 hash generated via PHP's password_hash() function)
INSERT INTO users (role, first_name, last_name, email, password_hash)
VALUES ('admin', 'System', 'Administrator', 'admin@crmc.edu.ph', 'REPLACE_WITH_PASSWORD_HASH');

-- Sample student account (student_number and student_id are separate:
-- student_number is the school ID printed on the student's records)
INSERT INTO users (role, student_number, first_name, last_name, email, password_hash)
VALUES ('student', '2023-00123', 'Juan', 'Dela Cruz', 'juan.delacruz@student.crmc.edu.ph', 'REPLACE_WITH_PASSWORD_HASH');

-- Sample staff account (must belong to an office — here, Registrar,
-- office_id 1 from the seed data above)
INSERT INTO users (role, office_id, first_name, last_name, email, password_hash)
VALUES ('staff', 1, 'Maria', 'Santos', 'maria.santos@crmc.edu.ph', 'REPLACE_WITH_PASSWORD_HASH');