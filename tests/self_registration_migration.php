<?php
$beforeIds = $db->query('SELECT user_id, is_active FROM users ORDER BY user_id')->fetch_all(MYSQLI_ASSOC);
$beforeConcerns = (int)$db->query('SELECT COUNT(*) AS n FROM inquiries')->fetch_assoc()['n'];
$db->query("ALTER TABLE student_profiles
    ADD approval_status ENUM('Incomplete','Pending','Needs correction','Approved','Rejected') NOT NULL DEFAULT 'Pending',
    ADD review_reason VARCHAR(1000) NOT NULL DEFAULT '', ADD identity_check VARCHAR(30) DEFAULT NULL,
    ADD reviewed_by INT UNSIGNED DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL,
    ADD notice_version VARCHAR(20) DEFAULT NULL, ADD acknowledged_at DATETIME DEFAULT NULL,
    ADD CONSTRAINT legacy_student_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(user_id),
    ADD KEY idx_student_review (approval_status, updated_at)");
$legacyId = (int)$db->query('SELECT user_id FROM users WHERE role = "student" LIMIT 1')->fetch_assoc()['user_id'];
$db->query('INSERT INTO student_account_events (student_id, actor_id, action) VALUES (' . $legacyId . ', 1, "Approved")');
for ($attempt = 0; $attempt < 2; $attempt++) {
    $db->multi_query(file_get_contents(__DIR__ . '/../database/migrations/20261005_student_self_registration.sql'));
    do { $result = $db->store_result(); if ($result) $result->free(); } while ($db->more_results() && $db->next_result());
}
checkRegression($db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'student_profiles' AND COLUMN_NAME IN ('approval_status','reviewed_by','identity_check','review_reason','reviewed_at','notice_version','acknowledged_at')")->num_rows === 0, 'Legacy approval columns were not removed.');
checkRegression($db->query('SELECT user_id, is_active FROM users ORDER BY user_id')->fetch_all(MYSQLI_ASSOC) === $beforeIds, 'Account migration changed IDs or suspension flags.');
checkRegression((int)$db->query('SELECT COUNT(*) AS n FROM inquiries')->fetch_assoc()['n'] === $beforeConcerns, 'Account migration removed concerns.');
checkRegression((int)$db->query('SELECT COUNT(*) AS n FROM student_legal_acceptances')->fetch_assoc()['n'] === 0, 'Migration fabricated new legal acceptance for existing users.');
checkRegression((int)$db->query('SELECT COUNT(*) AS n FROM student_account_events WHERE action = "Approved"')->fetch_assoc()['n'] === 0, 'Legacy approval history survived the removal.');
