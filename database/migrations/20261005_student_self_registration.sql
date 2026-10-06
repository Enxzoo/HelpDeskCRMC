CREATE TABLE IF NOT EXISTS student_legal_acceptances (
    user_id INT UNSIGNED PRIMARY KEY,
    terms_version VARCHAR(20) NOT NULL,
    terms_accepted_at DATETIME NOT NULL,
    privacy_version VARCHAR(20) NOT NULL,
    privacy_accepted_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remove legacy approval metadata without changing account IDs or access flags.
SET @review_foreign_keys = (SELECT GROUP_CONCAT(CONCAT('DROP FOREIGN KEY `', REPLACE(CONSTRAINT_NAME, '`', '``'), '`') SEPARATOR ', ')
    FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME = 'reviewed_by' AND REFERENCED_TABLE_NAME IS NOT NULL);
SET @review_sql = IF(@review_foreign_keys IS NULL, 'SELECT 1', CONCAT('ALTER TABLE student_profiles ', @review_foreign_keys));
PREPARE self_registration_migration FROM @review_sql;
EXECUTE self_registration_migration;
DEALLOCATE PREPARE self_registration_migration;

SET @review_index = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles' AND INDEX_NAME = 'idx_student_review');
SET @review_sql = IF(@review_index = 0, 'SELECT 1', 'ALTER TABLE student_profiles DROP INDEX idx_student_review');
PREPARE self_registration_migration FROM @review_sql;
EXECUTE self_registration_migration;
DEALLOCATE PREPARE self_registration_migration;

SET @review_columns = (SELECT GROUP_CONCAT(CONCAT('DROP COLUMN `', COLUMN_NAME, '`') SEPARATOR ', ')
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_profiles'
    AND COLUMN_NAME IN ('approval_status', 'review_reason', 'identity_check', 'reviewed_by', 'reviewed_at', 'notice_version', 'acknowledged_at'));
SET @review_sql = IF(@review_columns IS NULL, 'SELECT 1', CONCAT('ALTER TABLE student_profiles ', @review_columns));
PREPARE self_registration_migration FROM @review_sql;
EXECUTE self_registration_migration;
DEALLOCATE PREPARE self_registration_migration;

DELETE FROM student_account_events WHERE action IN ('Approved', 'Needs correction', 'Rejected', 'Pending');
UPDATE student_account_events SET action = 'Account created' WHERE action = 'Registration submitted';
UPDATE student_account_events SET action = 'Profile updated' WHERE action = 'Profile resubmitted';

-- Never fabricate acceptance of the new legal documents for existing accounts.
