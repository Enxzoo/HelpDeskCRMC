-- Preserve existing conversations while allowing separate chats in one category.
SET @chat_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
        AND table_name = 'chat_sessions' AND column_name = 'session_key'),
    'SELECT 1',
    'ALTER TABLE chat_sessions ADD COLUMN session_key char(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER session_id'
);
PREPARE chat_migration FROM @chat_sql;
EXECUTE chat_migration;
DEALLOCATE PREPARE chat_migration;

UPDATE chat_sessions SET session_key = REPLACE(UUID(), '-', ''), updated_at = updated_at WHERE session_key IS NULL;
ALTER TABLE chat_sessions MODIFY session_key char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;

SET @chat_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
        AND table_name = 'chat_sessions' AND index_name = 'uniq_student_session_key'),
    'SELECT 1',
    'ALTER TABLE chat_sessions ADD UNIQUE KEY uniq_student_session_key (student_id, session_key), ADD KEY idx_student_updated (student_id, updated_at, session_id)'
);
PREPARE chat_migration FROM @chat_sql;
EXECUTE chat_migration;
DEALLOCATE PREPARE chat_migration;

SET @chat_sql = IF(
    EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE()
        AND table_name = 'chat_sessions' AND index_name = 'uniq_student_category'),
    'ALTER TABLE chat_sessions DROP INDEX uniq_student_category', 'SELECT 1'
);
PREPARE chat_migration FROM @chat_sql;
EXECUTE chat_migration;
DEALLOCATE PREPARE chat_migration;
