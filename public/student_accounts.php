<?php
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/StudentProfile.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
session_start();
requireRole('admin');
header('Cache-Control: no-store');
$model = new StudentProfile();
$error = null;
$selectedId = null;
try {
    if (isset($_GET['user_id']))
        $selectedId = StudentProfile::id($_GET, 'user_id');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify())
            throw new StudentAccountException('Invalid or expired form submission.', 403);
        $selectedId = StudentProfile::id($_POST, 'user_id');
        $action = StudentProfile::text($_POST, 'action', 20, true);
        if ($action === 'access')
            $model->setAccess((int) $_SESSION['user_id'], $_POST);
        elseif ($action === 'academic')
            $model->updateAcademic((int) $_SESSION['user_id'], $_POST);
        else
            throw new StudentAccountException('Unknown account action.');
        $_SESSION['student_admin_notice'] = 'Student account updated.';
        header('Location: student_accounts.php?user_id=' . $selectedId);
        exit;
    }
    $search = StudentProfile::text($_GET, 'search', 150);
    $status = StudentProfile::text($_GET, 'status', 30);
    $page = isset($_GET['page']) ? StudentProfile::id($_GET, 'page') : 1;
} catch (StudentAccountException $exception) {
    $error = $exception->getMessage();
    http_response_code($exception->status);
} catch (Throwable $exception) {
    error_log('Student account update failed: ' . $exception->getMessage());
    $error = 'Unable to update this student account. Please try again.';
    http_response_code(503);
}
$search = $search ?? '';
$status = $status ?? '';
try {
    $listing = $model->students($search, $status, $page ?? 1);
} catch (StudentAccountException $exception) {
    $error = $exception->getMessage();
    http_response_code($exception->status);
    $listing = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
}
$profile = $selectedId ? $model->find($selectedId) : null;
if ($selectedId && !$profile) {
    http_response_code(404);
    $error = 'Student account not found.';
}
$notice = $_SESSION['student_admin_notice'] ?? '';
unset($_SESSION['student_admin_notice']);
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$catalog = $model->catalog();
$workspaceRole = 'admin';
$workspacePage = 'student_accounts';
$workspaceTitle = 'Student accounts';
?>
<!DOCTYPE html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Accounts - HelpdeskCRMC</title>
    <?php require __DIR__ . '/assets/components/ui-fonts.php'; ?>
    <?= stylesheet_bundle('workspace_ui') ?>
    <?= stylesheet_bundle('workspace_tables') ?>
    <?= stylesheet_bundle('student_accounts') ?>
    <script src="assets/js/workspace.js?v=<?= md5_file(__DIR__ . '/assets/js/workspace.js') ?>" defer></script>
    <script src="assets/js/student_profile.js?v=<?= md5_file(__DIR__ . '/assets/js/student_profile.js') ?>"
        defer></script>
</head>

<body class="student-account-page" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <div class="workspace-app" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <?php require __DIR__ . '/assets/components/workspace-sidebar.php'; ?>
        <main class="workspace-main" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <?php require __DIR__ . '/assets/components/workspace-header.php'; ?>
            <div class="account-shell wide" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h1 class="account-title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Student accounts</h1>
                <?php if ($notice): ?>
                    <div class="account-success" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status"><?= $escape($notice) ?></div><?php endif; ?>
                <?php if ($error): ?>
                    <div class="account-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert"><?= $escape($error) ?></div><?php endif; ?>
                <?php if ($profile): ?>
                    <div class="account-state" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($profile['first_name'] . ' ' . $profile['last_name']) ?></strong>
                        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($profile['student_number']) ?> |
                            <?= (int) $profile['is_active'] ? 'Enabled' : 'Suspended' ?>
                        </p>
                    </div>
                    <dl class="account-details" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <?php foreach (['email' => 'Email', 'middle_name' => 'Middle name', 'suffix' => 'Suffix', 'mobile_number' => 'Mobile number', 'college_name' => 'College', 'program_name' => 'Program / Course', 'year_level' => 'Year level', 'section' => 'Section / Block', 'academic_year' => 'Academic year', 'semester' => 'Term', 'enrollment_type' => 'Enrollment classification', 'terms_version' => 'Terms version accepted', 'terms_accepted_at' => 'Terms accepted at', 'privacy_version' => 'Privacy policy version accepted', 'privacy_accepted_at' => 'Privacy consent recorded at'] as $key => $label): ?>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <dt <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $label ?></dt>
                                <dd <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($profile[$key] ?: 'Not recorded') ?></dd>
                            </div><?php endforeach; ?>
                    </dl>
                    <?php if ((int) $profile['is_active']): ?>
                        <details class="account-management account-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <summary <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Update academic details</summary>
                            <form <?= dev_locator_attributes(__FILE__, __LINE__) ?> method="POST"><?= csrf_field() ?><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="action" value="academic"><input <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                    type="hidden" name="user_id" value="<?= $selectedId ?>"><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="revision"
                                    value="<?= (int) $profile['revision'] ?>"><?php $academicOnly = true;
                                       $values = $profile;
                                       require __DIR__ . '/assets/components/student-profile-fields.php'; ?>
                                <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Change reason<textarea <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="reason" required
                                            maxlength="1000" rows="3"></textarea></label></div>
                                <div class="account-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="submit">Update academic
                                        details</button></div>
                            </form>
                        </details>
                    <?php endif; ?>
                    <form method="POST" class="account-management account-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= csrf_field() ?><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden"
                            name="action" value="access"><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="user_id"
                            value="<?= $selectedId ?>"><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="is_active"
                            value="<?= (int) $profile['is_active'] ? '0' : '1' ?>">
                        <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label
                                class="full" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= (int) $profile['is_active'] ? 'Suspension' : 'Reinstatement' ?>
                                reason<textarea <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="reason" rows="2" required maxlength="1000"></textarea></label></div>
                        <div class="account-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button
                                class="button <?= (int) $profile['is_active'] ? 'danger' : 'primary' ?>" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>
                                type="submit"><?= (int) $profile['is_active'] ? 'Suspend access' : 'Reinstate access' ?></button>
                        </div>
                    </form>
                    <section class="account-history" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account history</h2><?php foreach ($model->history($selectedId) as $event): ?>
                            <article <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($event['action']) ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($event['actor_name'] . ' | ' . $event['created_at']) ?></small>
                                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($event['reason']) ?></p>
                            </article><?php endforeach; ?>
                    </section>
                <?php else: ?>
                    <form method="GET" class="account-filters" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Search<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="search"
                                value="<?= $escape($search) ?>" maxlength="150"
                                placeholder="Student ID, name, or email"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account access<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="status">
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All accounts</option><?php foreach (['Enabled', 'Suspended'] as $item): ?>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> <?= $status === $item ? 'selected' : '' ?>><?= $item ?></option><?php endforeach; ?>
                            </select></label><button class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="submit"><img class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                src="assets/icons/search.svg" alt="">Search</button></form>
                    <div class="table-scroll" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <table <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <thead <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Student</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Student ID</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Program</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Access</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>></th>
                                </tr>
                            </thead>
                            <tbody <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <?php foreach ($listing['items'] as $student): ?>
                                    <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                        <td <?= dev_locator_attributes(__FILE__, __LINE__) ?>><strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($student['first_name'] . ' ' . $student['last_name']) ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($student['email']) ?></small>
                                        </td>
                                        <td <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($student['student_number'] ?: 'Not provided') ?></td>
                                        <td <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($student['program_name'] ?: 'Not provided') ?></td>
                                        <td <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= (int) $student['is_active'] ? 'Enabled' : 'Suspended' ?></td>
                                        <td <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                                href="student_accounts.php?user_id=<?= (int) $student['user_id'] ?>"
                                                title="View student account"
                                                aria-label="View <?= $escape($student['first_name'] . ' ' . $student['last_name']) ?>"><img
                                                    class="icon" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> src="assets/icons/eye.svg" alt=""></a></td>
                                    </tr><?php endforeach; ?>
                                <?php if (!$listing['items']): ?>
                                    <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                        <td colspan="5" class="empty-cell" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No student accounts match these filters.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="pagination" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Student pages"><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $listing['total'] ?>
                            students</span><?php foreach ([-1 => 'chevron-left', 1 => 'chevron-right'] as $step => $icon): ?><?php $target = $listing['page'] + $step; ?><?php if ($target >= 1 && $target <= $listing['pages']): ?><a
                                    class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>
                                    href="?<?= $escape(http_build_query(['search' => $search, 'status' => $status, 'page' => $target])) ?>"
                                    aria-label="<?= $step < 0 ? 'Previous' : 'Next' ?> page"><img class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                        src="assets/icons/<?= $icon ?>.svg"
                                        alt=""></a><?php endif; ?><?php endforeach; ?><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $listing['page'] ?> /
                            <?= $listing['pages'] ?></span></nav>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>