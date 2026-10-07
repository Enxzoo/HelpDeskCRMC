<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/AdminWorkspace.php';
require_once __DIR__ . '/../app/models/Office.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
session_start();
requireRole('admin');
$offices = (new Office())->findAll();
$overview = (new AdminWorkspace())->overview();
$name = (string) ($_SESSION['name'] ?? 'Administrator');
$initials = mb_strtoupper(mb_substr($name, 0, 1));
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$icon = static fn($name) => '<img class="icon" src="assets/icons/' . $name . '.svg" alt="" width="18" height="18">';
$stats = $overview['stats'];
$schoolPrograms = (new StudentProfile())->catalog(true)['programs'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Admin Portal - HELPDESKCRMC</title>
    <?php require __DIR__ . '/assets/components/ui-fonts.php'; ?>
    <?= stylesheet_bundle('dashboard_admin') ?>
    <script src="assets/js/notifications.js?v=<?= md5_file(__DIR__ . '/assets/js/notifications.js') ?>" defer></script>
    <script src="assets/js/dashboard_admin.js?v=<?= md5_file(__DIR__ . '/assets/js/dashboard_admin.js') ?>"
        defer></script>
</head>

<body>
    <div class="admin-app">
        <?php $workspaceRole = 'admin';
        $workspacePage = 'overview';
        $workspaceDashboard = true;
        require __DIR__ . '/assets/components/workspace-sidebar.php'; ?>
        <button type="button" id="sidebarBackdrop" class="sidebar-backdrop" aria-label="Close navigation"
            hidden></button>
        <main class="admin-main">
            <header class="admin-header"><button type="button" class="icon-button mobile-menu" id="menuToggle"
                    aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"
                    title="Navigation"><?= $icon('menu') ?></button>
                <div class="breadcrumb"><span>Administration</span><span aria-hidden="true">/</span><strong
                        id="breadcrumbView">Overview</strong></div>
                <div class="header-tools"><time><?= $escape(date('M j, Y')) ?></time><button type="button"
                        class="icon-button" id="refreshView" title="Refresh current view"
                        aria-label="Refresh current view"><?= $icon('refresh-cw') ?></button><?php require __DIR__ . '/assets/components/notification-center.php'; ?>
                </div>
            </header>
            <div class="admin-content">
                <div class="admin-alert" id="adminAlert" role="alert" hidden><span id="adminAlertText"></span><button
                        type="button" class="icon-button" id="dismissAlert" title="Dismiss"
                        aria-label="Dismiss message"><?= $icon('x') ?></button></div>
                <section id="view-overview" class="admin-view" aria-labelledby="overviewTitle">
                    <div class="page-heading">
                        <div>
                            <h1 id="overviewTitle">Dashboard overview</h1>
                            <p>Across all offices</p>
                        </div><a class="button primary" href="#reports"><?= $icon('file-chart-column') ?>Generate
                            report</a>
                    </div>
                    <div class="metric-grid" id="overviewMetrics">
                        <article class="metric">
                            <div>Open concerns <?= $icon('inbox') ?></div>
                            <strong><?= $stats['total'] - $stats['resolved'] ?></strong><small>Pending, in progress
                                &amp; on hold</small>
                        </article>
                        <article class="metric">
                            <div>Unassigned <?= $icon('user-round-plus') ?></div>
                            <strong><?= $stats['unassigned'] ?></strong><small>Awaiting a staff member</small>
                        </article>
                        <article class="metric">
                            <div>Resolved <?= $icon('circle-check') ?></div>
                            <strong><?= $stats['resolved'] ?></strong><small><?= $stats['total'] ? round($stats['resolved'] / $stats['total'] * 100) : 0 ?>%
                                of submitted concerns</small>
                        </article>
                        <article class="metric">
                            <div>Active staff <?= $icon('users') ?></div>
                            <strong><?= $stats['active_staff'] ?></strong><small>Across all offices</small>
                        </article>
                    </div>
                    <div class="overview-split">
                        <section class="workload">
                            <div class="section-heading">
                                <h2>Office workload</h2><span>Open concerns</span>
                            </div>
                            <div id="officeWorkload"></div>
                        </section>
                        <section class="status-summary">
                            <h2>Concern status</h2>
                            <div id="statusSummary"></div>
                            <div class="system-totals" id="systemTotals"></div>
                        </section>
                    </div>
                    <section class="assignment-summary">
                        <div class="section-heading">
                            <h2>Awaiting assignment</h2><a href="#concerns" id="viewUnassigned">View all
                                <?= $icon('arrow-up-right') ?></a>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Concern</th>
                                        <th>Office</th>
                                        <th>Priority</th>
                                        <th>Received</th>
                                        <th class="align-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewQueue"></tbody>
                            </table>
                        </div>
                    </section>
                </section>
                <section id="view-staff" class="admin-view" aria-labelledby="staffTitle" hidden>
                    <div class="page-heading">
                        <div>
                            <h1 id="staffTitle">Staff accounts</h1>
                            <p id="staffSummary">Loading staff...</p>
                        </div><button type="button" class="button primary" id="addStaff"><?= $icon('plus') ?>Add
                            staff</button>
                    </div>
                    <div class="filter-bar"><label class="search-field"><?= $icon('search') ?><input type="search"
                                id="staffSearch" placeholder="Search staff" aria-label="Search staff"
                                maxlength="150"></label><label><span class="sr-only">Office</span><select
                                id="staffOffice">
                                <option value="">All offices</option><?php foreach ($offices as $office): ?>
                                    <option value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label><span class="sr-only">Account status</span><select id="staffStatus">
                                <option value="">All statuses</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select></label></div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff member</th>
                                    <th>Office</th>
                                    <th>Status</th>
                                    <th>Open concerns</th>
                                    <th>Last sign-in</th>
                                    <th class="align-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="staffRows"></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="staffPagination"></div>
                </section>
                <section id="view-knowledge" class="admin-view" aria-labelledby="knowledgeTitle" hidden>
                    <div class="page-heading">
                        <div>
                            <h1 id="knowledgeTitle">Knowledge base</h1>
                            <p id="knowledgeSummary">Loading entries...</p>
                        </div><button type="button" class="button primary" id="addKnowledge"><?= $icon('plus') ?>Add
                            entry</button>
                    </div>
                    <div class="filter-bar"><label class="search-field"><?= $icon('search') ?><input type="search"
                                id="knowledgeSearch" placeholder="Search entries" aria-label="Search knowledge entries"
                                maxlength="150"></label><label><span class="sr-only">Office</span><select
                                id="knowledgeOffice">
                                <option value="">All offices</option>
                                <option value="global">General / All offices</option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label><span class="sr-only">Publishing status</span><select
                                id="knowledgeStatus">
                                <option value="">All statuses</option>
                                <option>Published</option>
                                <option>Draft</option>
                            </select></label></div>
                    <div class="knowledge-list" id="knowledgeEntries"></div>
                    <div class="pagination" id="knowledgePagination"></div>
                </section>
                <section id="view-concerns" class="admin-view" aria-labelledby="concernsTitle" hidden>
                    <div class="page-heading">
                        <div>
                            <h1 id="concernsTitle">Concerns</h1>
                            <p id="concernsSummary">Loading concerns...</p>
                        </div><a href="#reports" class="button"><?= $icon('file-chart-column') ?>Reports</a>
                    </div>
                    <form class="filter-bar" id="concernFilters"><label
                            class="search-field"><?= $icon('search') ?><input type="search" id="concernSearch"
                                name="search" placeholder="Search concern or student" aria-label="Search concerns"
                                maxlength="150"></label><label><span class="sr-only">Office</span><select
                                id="concernOffice" name="office_id">
                                <option value="">All offices</option><?php foreach ($offices as $office): ?>
                                    <option value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label><span class="sr-only">Concern status</span><select
                                id="concernStatus" name="status">
                                <option value="">All statuses</option><?php foreach (Inquiry::STATUSES as $status): ?>
                                    <option><?= $escape($status) ?></option><?php endforeach; ?>
                            </select></label><label><span class="sr-only">Assignment</span><select
                                id="concernAssignment" name="assignment">
                                <option value="">All assignments</option>
                                <option value="unassigned">Unassigned</option>
                            </select></label></form>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Concern / Student</th>
                                    <th>Office</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned staff</th>
                                    <th class="align-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="concernRows"></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="concernPagination"></div>
                </section>
                <section id="view-reports" class="admin-view" aria-labelledby="reportsTitle" hidden>
                    <div class="page-heading">
                        <div>
                            <h1 id="reportsTitle">Reports</h1>
                            <p>Inquiries &amp; concerns</p>
                        </div>
                    </div>
                    <form id="reportForm" class="report-form">
                        <fieldset class="segmented">
                            <legend class="sr-only">Report type</legend><label><input type="radio" name="type"
                                    value="concerns" checked><span>Concerns</span></label><label><input type="radio"
                                    name="type" value="inquiries"><span>Ben inquiries</span></label>
                        </fieldset>
                        <div class="report-fields"><label>From<input type="date" name="from" required
                                    value="<?= date('Y-m-01') ?>"></label><label>To<input type="date" name="to" required
                                    value="<?= date('Y-m-d') ?>"></label><label id="reportOfficeField">Office<select
                                    name="office_id">
                                    <option value="">All offices</option><?php foreach ($offices as $office): ?>
                                        <option value="<?= (int) $office['office_id'] ?>">
                                            <?= $escape($office['office_name']) ?></option><?php endforeach; ?>
                                </select></label><label id="reportStatusField">Status<select name="status">
                                    <option value="">All statuses</option>
                                    <?php foreach (Inquiry::STATUSES as $status): ?>
                                        <option><?= $escape($status) ?></option><?php endforeach; ?>
                                </select></label><label id="reportCategoryField" hidden>Category<select name="category"
                                    disabled>
                                    <option value="">All categories</option>
                                    <?php $categories = getDbConnection()->query('SELECT DISTINCT category FROM chat_sessions ORDER BY category');
                                    while ($category = $categories->fetch_assoc()): ?>
                                        <option><?= $escape($category['category']) ?></option><?php endwhile; ?>
                                </select></label><label>Program<select name="program_id">
                                    <option value="">All programs</option>
                                    <?php foreach ($schoolPrograms as $program): ?>
                                        <option value="<?= (int) $program['program_id'] ?>"><?= $escape($program['name']) ?>
                                        </option><?php endforeach; ?>
                                </select></label><button type="submit"
                                class="button primary"><?= $icon('file-chart-column') ?>Generate report</button></div>
                    </form>
                    <div class="report-empty" id="reportEmpty"><?= $icon('chart-no-axes-combined') ?>
                        <h2>No report generated</h2>
                    </div>
                    <section id="reportPreview" hidden>
                        <div class="section-heading report-heading">
                            <div>
                                <h2 id="reportPreviewTitle"></h2>
                                <p id="reportPreviewMeta"></p>
                            </div>
                            <div class="report-actions"><button type="button" class="button"
                                    id="printReport"><?= $icon('printer') ?>Print / PDF</button><button type="button"
                                    class="button primary" id="downloadReport"><?= $icon('download') ?>Download
                                    CSV</button></div>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead id="reportHead"></thead>
                                <tbody id="reportRows"></tbody>
                            </table>
                        </div>
                        <p class="report-footer" id="reportFooter"></p>
                    </section>
                </section>
            </div>
        </main>
    </div>
    <dialog class="admin-dialog" id="staffDialog" aria-labelledby="staffDialogTitle">
        <form id="staffForm">
            <div class="dialog-heading">
                <h2 id="staffDialogTitle">Add staff</h2><button type="button" class="icon-button"
                    data-close="staffDialog" aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div><input type="hidden" name="user_id">
            <div class="form-grid"><label>First name<input name="first_name" required maxlength="100"
                        autocomplete="given-name"></label><label>Last name<input name="last_name" required
                        maxlength="100" autocomplete="family-name"></label><label class="full">Email address<input type="email"
                        name="email" required maxlength="150" autocomplete="email"></label><label
                    class="full">Office assignment<select name="office_id" required>
                        <option value="">Select office</option><?php foreach ($offices as $office): ?>
                            <option value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select></label><label class="full"><span id="staffPasswordLabel">Password (8+ characters)</span><input
                        type="password" name="password" minlength="8" maxlength="72"
                        autocomplete="new-password" placeholder="Minimum 8 characters"></label><label class="checkbox-label full"><input type="checkbox"
                        name="is_active" checked>Active account</label></div>
            <p class="form-error" role="alert" hidden></p>
            <div class="dialog-actions"><button type="button" class="button"
                    data-close="staffDialog">Cancel</button><button type="submit" class="button primary">Save staff
                    account</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog knowledge-dialog" id="knowledgeDialog" aria-labelledby="knowledgeDialogTitle">
        <form id="knowledgeForm">
            <div class="dialog-heading">
                <h2 id="knowledgeDialogTitle">Add knowledge entry</h2><button type="button" class="icon-button"
                    data-close="knowledgeDialog" aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div><input type="hidden" name="entry_id">
            <div class="form-grid"><label class="full">Title<input name="title" required
                        maxlength="150"></label><label>Office<select name="office_id">
                        <option value="">General / All offices</option><?php foreach ($offices as $office): ?>
                            <option value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select></label><label>Status<select name="status">
                        <option>Draft</option>
                        <option>Published</option>
                    </select></label><label class="full">Content<textarea name="content" rows="10" required
                        maxlength="6000"></textarea></label></div>
            <p class="form-error" role="alert" hidden></p>
            <div class="dialog-actions"><button type="button" class="button"
                    data-close="knowledgeDialog">Cancel</button><button type="submit" class="button primary">Save
                    entry</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog concern-dialog" id="concernDialog" aria-labelledby="concernDialogTitle">
        <div class="dialog-heading">
            <div><small id="concernDialogId"></small>
                <h2 id="concernDialogTitle">Concern</h2>
            </div><button type="button" class="icon-button" data-close="concernDialog" aria-label="Close"
                title="Close"><?= $icon('x') ?></button>
        </div>
        <div id="concernDetail"></div>
        <form id="assignmentForm"><input type="hidden" name="inquiry_id"><label>Assigned staff<select name="staff_id"
                    id="assignmentStaff"></select></label>
            <p class="form-error" role="alert" hidden></p>
            <div class="dialog-actions"><button type="button" class="button"
                    data-close="concernDialog">Close</button><button type="submit" class="button primary"
                    id="saveAssignment">Save assignment</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog confirm-dialog" id="deleteDialog" aria-labelledby="deleteTitle"
        aria-describedby="deleteMessage">
        <form id="deleteForm">
            <div class="dialog-heading">
                <h2 id="deleteTitle">Delete?</h2><button type="button" class="icon-button" data-close="deleteDialog"
                    aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div>
            <p id="deleteMessage"></p>
            <p class="form-error" role="alert" hidden></p>
            <div class="dialog-actions"><button type="button" class="button"
                    data-close="deleteDialog">Cancel</button><button type="submit" class="button danger">Delete</button>
            </div>
        </form>
    </dialog>
    <dialog class="action-confirm-dialog" id="actionConfirmDialog" aria-labelledby="actionConfirmTitle"
        aria-describedby="actionConfirmMessage">
        <div class="action-confirm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
                <path d="M12 3 3.8 7v5.3c0 4.2 3.5 7.9 8.2 9.2 4.7-1.3 8.2-5 8.2-9.2V7L12 3Z" />
                <path d="M12 8v4m0 4h.01" />
            </svg>
        </div>
        <h2 id="actionConfirmTitle">Please confirm</h2>
        <p id="actionConfirmMessage"></p>
        <div class="action-confirm-actions">
            <button type="button" class="action-confirm-continue" id="actionConfirmContinue">Confirm</button>
            <button type="button" class="action-confirm-cancel" id="actionConfirmCancel">Cancel</button>
        </div>
    </dialog>
    <div class="admin-toast" id="adminToast" role="status" aria-live="polite" hidden></div>
    <script>window.ADMIN_BOOTSTRAP = <?= json_encode(['offices' => $offices, 'overview' => $overview], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
    <script src="assets/js/student_account_actions.js?v=<?= md5_file(__DIR__ . '/assets/js/student_account_actions.js') ?>" defer></script>
</body>

</html>