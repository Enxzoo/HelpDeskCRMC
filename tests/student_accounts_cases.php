<?php
function studentAccountThrows(Closure $operation, int $status = 400): void
{
    try { $operation(); }
    catch (StudentAccountException $error) { checkRegression($error->status === $status, 'Incorrect student account rejection status.'); return; }
    throw new RuntimeException('Invalid student account action was accepted.');
}

$profiles = new StudentProfile();
$details = ['first_name' => 'Defense', 'last_name' => 'Student', 'middle_name' => 'Optional', 'suffix' => '',
    'student_number' => '000123-DEFENSE', 'email' => 'defense.student@example.test',
    'password' => 'TestPass', 'confirm_password' => 'TestPass',
    'mobile_number' => '+63 912 345 6789'] + $academic;
foreach ([['first_name', []], ['college_id', []], ['college_id', '99999'], ['year_level', '8'], ['term_id', '99999'],
    ['terms_accepted', '0'], ['privacy_accepted', '0'], ['terms_accepted', []], ['privacy_accepted', []],
    ['terms_version', 'old'], ['privacy_version', []],
    ['enrollment_type', 'Unknown'], ['password', 'short'], ['password', '1234567'],
    ['password', str_repeat('a', 73)], ['confirm_password', 'different'],
    ['mobile_number', 'not a phone'], ['student_number', '<script>']] as [$key, $invalid]) {
    studentAccountThrows(fn() => $profiles->register(array_replace($details, [$key => $invalid])));
}
$caseId = $profiles->register($details + ['role' => 'admin', 'office_id' => 1]);
checkRegression(password_verify($details['password'], $users->findById($caseId)['password_hash']), 'An eight-character registration password was not stored correctly.');
checkRegression($users->findById($caseId)['role'] === 'student' && $users->findById($caseId)['office_id'] === null, 'Registration permits role or office injection.');
checkRegression($profiles->find($caseId)['student_number'] === '000123-DEFENSE', 'Registration lost leading zeroes.');
checkRegression($auth->dashboardFor('student') === 'dashboard_student.php', 'Student access is still approval-gated.');
$accepted = $profiles->find($caseId);
checkRegression($accepted['terms_version'] === HELPDESK_TERMS_VERSION && $accepted['privacy_version'] === HELPDESK_PRIVACY_VERSION
    && $accepted['terms_accepted_at'] !== null && $accepted['privacy_accepted_at'] !== null, 'Legal agreement versions and times were not recorded.');
checkRegression($profiles->snapshot($caseId) !== null, 'Registered academic details are unavailable to concerns.');
$profiles->updateDetails($caseId, array_replace($details, ['section' => 'BSIT-2B']));
checkRegression($profiles->find($caseId)['section'] === 'BSIT-2B', 'Student cannot update their own profile.');
$profiles->updateContact($caseId, ['mobile_number' => '+63 999 000 1111', 'program_id' => 99999, 'role' => 'admin']);
checkRegression((int)$profiles->find($caseId)['program_id'] === (int)$academic['program_id'] && $users->findById($caseId)['role'] === 'student', 'Contact editing modified protected fields.');
$snapshotInquiry = (new Inquiry())->create(['student_id' => $caseId, 'office_id' => 2, 'message' => 'Academic snapshot check', 'subject' => 'Academic snapshot check']);
$academicUpdate = ['user_id' => $caseId, 'revision' => $profiles->find($caseId)['revision'], 'reason' => 'New academic year level.'] + array_replace($academic, ['year_level' => '3']);
studentAccountThrows(fn() => $profiles->updateAcademic($fixtures['staff'], $academicUpdate), 403);
$profiles->updateAcademic($fixtures['admin'], $academicUpdate);
studentAccountThrows(fn() => $profiles->updateAcademic($fixtures['admin'], $academicUpdate), 409);
checkRegression((int)$profiles->find($caseId)['year_level'] === 3, 'Academic update was not applied.');
$historic = (new Inquiry())->findById($snapshotInquiry);
checkRegression((int)$historic['student_year_level'] === 2 && $historic['student_section'] === 'BSIT-2B', 'Profile update rewrote the academic context of an old concern.');
require_once __DIR__ . '/../app/models/AdminReport.php';
$report = (new AdminReport())->rows(AdminReport::filters(['type' => 'concerns', 'from' => '2000-01-01', 'to' => '2099-12-31', 'program_id' => $academic['program_id']]));
$snapshotFound = false;
while ($row = $report->fetch_row()) if ($row[0] === 'INQ-' . $snapshotInquiry) $snapshotFound = (int)$row[12] === 2;
checkRegression($snapshotFound, 'Academic report filter or submission-time columns failed.');
$profiles->setAccess($fixtures['admin'], ['user_id' => $caseId, 'is_active' => '0', 'reason' => 'Temporary administrative suspension.']);
checkRegression($profiles->snapshot($caseId) === null, 'Suspended student retains service access.');
studentAccountThrows(fn() => $profiles->updateContact($caseId, ['mobile_number' => '09123456789']), 409);
checkRegression(!$auth->login($details['email'], $details['password'])['success'], 'Suspended student can sign in.');
$profiles->setAccess($fixtures['admin'], ['user_id' => $caseId, 'is_active' => '1', 'reason' => 'Suspension resolved.']);
checkRegression($profiles->snapshot($caseId) !== null, 'Reinstating a student failed.');
checkRegression(count($profiles->history($caseId)) >= 6, 'Account changes are missing their audit history.');
$registered = $fixtures['registeredStudent'];
$changedEmail = ['first_name' => 'New', 'last_name' => 'Student', 'student_number' => 'REG-2026-001', 'email' => 'changed.student@example.test'] + $academic;
studentAccountThrows(fn() => $profiles->updateDetails($registered, $changedEmail));
checkRegression($users->findById($registered)['email'] === 'new.student@example.test', 'An email change bypassed re-authentication.');
$profiles->updateDetails($registered, $changedEmail + ['current_password' => $registrationPassword]);
checkRegression($users->findById($registered)['email'] === 'changed.student@example.test', 'Valid email re-authentication failed.');
checkRegression($profiles->find($registered)['terms_version'] === HELPDESK_TERMS_VERSION, 'Profile editing altered the legal agreement record.');
$before = (int)$db->query('SELECT COUNT(*) n FROM users')->fetch_assoc()['n'];
try { $profiles->register(array_replace($details, ['student_number' => 'ROLLBACK-TEST'])); }
catch (mysqli_sql_exception $error) { checkRegression($error->getCode() === 1062, 'Duplicate identity did not hit the unique constraint.'); }
checkRegression((int)$db->query('SELECT COUNT(*) n FROM users')->fetch_assoc()['n'] === $before, 'A failed registration left an extra account.');
$fixtures['profileCase'] = $caseId;
