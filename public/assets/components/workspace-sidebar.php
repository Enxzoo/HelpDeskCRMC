<?php
$sidebarEscape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$sidebarIcon = static fn(string $glyph): string => '<img class="icon"' . dev_locator_attributes(__FILE__, __LINE__) . ' src="assets/icons/' . $glyph . '.svg" alt="" width="18" height="18">';
$sidebarAdmin = $workspaceRole === 'admin';
$sidebarDashboard = !empty($workspaceDashboard);
$sidebarName = (string)($_SESSION['name'] ?? ($sidebarAdmin ? 'Administrator' : 'Student'));
$sidebarInitials = mb_strtoupper(mb_substr($sidebarName, 0, 1));
$sidebarId = $sidebarDashboard ? 'adminSidebar' : 'workspaceSidebar';
$sidebarHome = $sidebarAdmin ? 'dashboard_admin.php' : 'dashboard_student.php';
$sidebarLinks = $sidebarAdmin ? [
    'overview' => ['layout-dashboard', 'Overview', 'dashboard_admin.php#overview'],
    'staff' => ['users', 'Staff Accounts', 'dashboard_admin.php#staff'],
    'knowledge' => ['book-open', 'Knowledge Base', 'dashboard_admin.php#knowledge'],
    'concerns' => ['inbox', 'Concerns', 'dashboard_admin.php#concerns'],
    'reports' => ['chart-no-axes-combined', 'Reports', 'dashboard_admin.php#reports'],
    'student_accounts' => ['users', 'Student Accounts', 'student_accounts.php'],
    'school_catalog' => ['book-open', 'School Catalog', 'school_catalog.php'],
] : [
    'dashboard' => ['layout-dashboard', 'Dashboard', 'dashboard_student.php'],
    'profile' => ['users', 'My Profile', 'student_profile.php'],
    'privacy' => ['book-open', 'Privacy Policy', 'student_privacy.php'],
];
?>
<aside class="sidebar<?= $sidebarAdmin ? ' admin-sidebar' : '' ?>" id="<?= $sidebarId ?>" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="<?= $sidebarAdmin ? 'Administration' : 'Student' ?> navigation">
    <?php if (!$sidebarDashboard): ?><button type="button" class="icon-button workspace-close" id="workspaceClose" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Close navigation" title="Close navigation"><?= $sidebarIcon('x') ?></button><?php endif; ?>
    <div class="brand" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
        <div class="brand-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Helpdesk<span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>CRMC</span></div>
    </div>
<div class="nav-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="nav-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Workspace</div>
        <?php foreach ($sidebarLinks as $sidebarKey => [$sidebarGlyph, $sidebarLabel, $sidebarHref]): ?>
        <?php $sidebarView = $sidebarDashboard && in_array($sidebarKey, ['overview', 'staff', 'knowledge', 'concerns', 'reports'], true); ?>
        <a class="nav-item<?= $workspacePage === $sidebarKey ? ' active' : '' ?>" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="<?= $sidebarView ? '#' . $sidebarKey : $sidebarHref ?>"<?= $sidebarView ? ' data-view="' . $sidebarKey . '"' : '' ?>><?= $sidebarIcon($sidebarGlyph) ?><?= $sidebarLabel ?><?php if ($sidebarDashboard && $sidebarKey === 'concerns'): ?><span class="nav-count" id="navConcernCount" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= (int)$stats['unassigned'] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-spacer" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>

    <a href="logout.php" class="nav-item student-logout-link" <?= dev_locator_attributes(__FILE__, __LINE__) ?> style="margin-top:8px;"><?= $sidebarIcon('log-out') ?>Logout</a>

    <div class="sidebar-foot" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="sidebar-profile" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="avatar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $sidebarEscape($sidebarInitials) ?></div>
            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="user-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $sidebarEscape($sidebarName) ?></div>
                <div class="user-sub" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $sidebarAdmin ? 'Administrator' : 'Student' ?></div>
            </div>
        </div>
    </div>
</aside>
<?php if (!$sidebarDashboard): ?>
<button type="button" id="workspaceBackdrop" class="workspace-backdrop" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Close navigation" hidden></button>
<dialog class="admin-dialog confirm-dialog logout-confirm" id="workspaceLogout" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="workspaceLogoutTitle"><h2 id="workspaceLogoutTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sign out?</h2><p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Are you sure you want to sign out of your account?</p><div class="dialog-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button" id="workspaceStay" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Stay signed in</button><button type="button" class="button danger" id="workspaceSignOut" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sign out</button></div></dialog>
<?php endif; ?>
