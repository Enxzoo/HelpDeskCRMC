<?php
$sidebarEscape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$sidebarIcon = static fn(string $glyph): string => '<img class="icon" src="assets/icons/' . $glyph . '.svg" alt="" width="18" height="18">';
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
<aside class="sidebar<?= $sidebarAdmin ? ' admin-sidebar' : '' ?>" id="<?= $sidebarId ?>" aria-label="<?= $sidebarAdmin ? 'Administration' : 'Student' ?> navigation">
    <?php if (!$sidebarDashboard): ?><button type="button" class="icon-button workspace-close" id="workspaceClose" aria-label="Close navigation" title="Close navigation"><?= $sidebarIcon('x') ?></button><?php endif; ?>
    <div class="brand">
        <img src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
        <div class="brand-name">Helpdesk<span>CRMC</span></div>
    </div>
<div class="nav-section">
        <div class="nav-label">Workspace</div>
        <?php foreach ($sidebarLinks as $sidebarKey => [$sidebarGlyph, $sidebarLabel, $sidebarHref]): ?>
        <?php $sidebarView = $sidebarDashboard && in_array($sidebarKey, ['overview', 'staff', 'knowledge', 'concerns', 'reports'], true); ?>
        <a class="nav-item<?= $workspacePage === $sidebarKey ? ' active' : '' ?>" href="<?= $sidebarView ? '#' . $sidebarKey : $sidebarHref ?>"<?= $sidebarView ? ' data-view="' . $sidebarKey . '"' : '' ?>><?= $sidebarIcon($sidebarGlyph) ?><?= $sidebarLabel ?><?php if ($sidebarDashboard && $sidebarKey === 'concerns'): ?><span class="nav-count" id="navConcernCount"><?= (int)$stats['unassigned'] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </div>
    <div class="sidebar-spacer"></div>

    <form method="post" action="logout.php" id="logoutForm">
        <button type="submit" class="nav-item student-logout-link" style="margin-top:8px;width:100%;text-align:left;border:none;background:none;cursor:pointer;"><?= $sidebarIcon('log-out') ?>Logout</button>
    </form>

    <div class="sidebar-foot">
        <div class="sidebar-profile">
            <div class="avatar"><?= $sidebarEscape($sidebarInitials) ?></div>
            <div>
                <div class="user-name"><?= $sidebarEscape($sidebarName) ?></div>
                <div class="user-sub"><?= $sidebarAdmin ? 'Administrator' : 'Student' ?></div>
            </div>
        </div>
    </div>
</aside>
<?php if (!$sidebarDashboard): ?>
<button type="button" id="workspaceBackdrop" class="workspace-backdrop" aria-label="Close navigation" hidden></button>
<dialog class="admin-dialog confirm-dialog logout-confirm" id="workspaceLogout" aria-labelledby="workspaceLogoutTitle"><h2 id="workspaceLogoutTitle">Sign out?</h2><p>Are you sure you want to sign out of your account?</p><div class="dialog-actions"><button type="button" class="button" id="workspaceStay">Stay signed in</button><button type="button" class="button danger" id="workspaceSignOut">Sign out</button></div></dialog>
<?php endif; ?>
