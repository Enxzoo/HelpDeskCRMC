<?php
function studentAcademicFixture(mysqli $db): array
{
    $db->query('INSERT IGNORE INTO school_terms (academic_year, semester, registration_open) VALUES ("2026-2027", "1st Semester", 1)');
    $program = $db->query('SELECT program_id, college_id FROM school_programs WHERE code = "BSIT"')->fetch_assoc();
    $term = $db->query('SELECT term_id FROM school_terms WHERE academic_year = "2026-2027" AND semester = "1st Semester"')->fetch_assoc();
    return ['college_id' => (string)$program['college_id'], 'program_id' => (string)$program['program_id'], 'year_level' => '2',
        'term_id' => (string)$term['term_id'], 'section' => 'BSIT-2A', 'enrollment_type' => 'Regular', 'terms_accepted' => '1', 'privacy_accepted' => '1',
        'terms_version' => HELPDESK_TERMS_VERSION, 'privacy_version' => HELPDESK_PRIVACY_VERSION];
}

function studentProfileFixture(mysqli $db, int $userId): void
{
    $academic = studentAcademicFixture($db);
    $stmt = $db->prepare('INSERT INTO student_profiles (user_id, program_id, term_id, year_level, section, enrollment_type,
        revision)
        VALUES (?, ?, ?, 2, "BSIT-2A", "Regular", 1)
        ON DUPLICATE KEY UPDATE program_id = VALUES(program_id), term_id = VALUES(term_id),
        year_level = 2, enrollment_type = "Regular"');
    $program = (int)$academic['program_id']; $term = (int)$academic['term_id'];
    $stmt->bind_param('iii', $userId, $program, $term);
    $stmt->execute();
}
