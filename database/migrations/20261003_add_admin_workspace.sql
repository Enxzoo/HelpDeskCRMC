-- Staff removal retains response authorship and concern history.
SET @admin_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
        AND table_name = 'users' AND column_name = 'deleted_at'),
    'SELECT 1', 'ALTER TABLE users ADD COLUMN deleted_at timestamp NULL DEFAULT NULL'
);
PREPARE admin_migration FROM @admin_sql;
EXECUTE admin_migration;
DEALLOCATE PREPARE admin_migration;

CREATE TABLE IF NOT EXISTS knowledge_entries (
    entry_id int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    office_id int UNSIGNED DEFAULT NULL,
    title varchar(150) NOT NULL,
    content text NOT NULL,
    status enum('Draft','Published') NOT NULL DEFAULT 'Draft',
    updated_by int UNSIGNED NOT NULL,
    created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_knowledge_status (status, office_id),
    FULLTEXT KEY idx_knowledge_search (title, content),
    CONSTRAINT fk_knowledge_office FOREIGN KEY (office_id) REFERENCES offices (office_id),
    CONSTRAINT fk_knowledge_editor FOREIGN KEY (updated_by) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
