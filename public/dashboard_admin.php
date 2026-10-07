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
$icon = static fn($name) => '<img class="icon"' . dev_locator_attributes(__FILE__, __LINE__) . ' src="assets/icons/' . $name . '.svg" alt="" width="18" height="18">';
$stats = $overview['stats'];
$schoolPrograms = (new StudentProfile())->catalog(true)['programs'];
?>
<!DOCTYPE html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

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

<body <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <div class="admin-app" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <?php $workspaceRole = 'admin';
        $workspacePage = 'overview';
        $workspaceDashboard = true;
        require __DIR__ . '/assets/components/workspace-sidebar.php'; ?>
        <button type="button" id="sidebarBackdrop" class="sidebar-backdrop" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Close navigation"
            hidden></button>
        <main class="admin-main" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <header class="admin-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="icon-button mobile-menu" id="menuToggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"
                    title="Navigation"><?= $icon('menu') ?></button>
                <div class="breadcrumb" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Administration</span><span <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">/</span><strong
                        id="breadcrumbView" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>Overview</strong></div>
                <div class="header-tools" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><time <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape(date('M j, Y')) ?></time><button type="button"
                        class="icon-button" id="refreshView" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> title="Refresh current view"
                        aria-label="Refresh current view"><?= $icon('refresh-cw') ?></button><?php require __DIR__ . '/assets/components/notification-center.php'; ?>
                </div>
            </header>
            <div class="admin-content" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="admin-alert" id="adminAlert" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert" hidden><span id="adminAlertText" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></span><button
                        type="button" class="icon-button" id="dismissAlert" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> title="Dismiss"
                        aria-label="Dismiss message"><?= $icon('x') ?></button></div>
                <section id="view-overview" class="admin-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="overviewTitle">
                    <div class="page-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h1 id="overviewTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Dashboard overview</h1>
                            <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Across all offices</p>
                        </div><a class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#reports"><?= $icon('file-chart-column') ?>Generate
                            report</a>
                    </div>
                    <div class="metric-grid" id="overviewMetrics" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <article class="metric" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Open concerns <?= $icon('inbox') ?></div>
                            <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $stats['total'] - $stats['resolved'] ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Pending, in progress
                                &amp; on hold</small>
                        </article>
                        <article class="metric" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Unassigned <?= $icon('user-round-plus') ?></div>
                            <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $stats['unassigned'] ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Awaiting a staff member</small>
                        </article>
                        <article class="metric" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Resolved <?= $icon('circle-check') ?></div>
                            <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $stats['resolved'] ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $stats['total'] ? round($stats['resolved'] / $stats['total'] * 100) : 0 ?>%
                                of submitted concerns</small>
                        </article>
                        <article class="metric" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Active staff <?= $icon('users') ?></div>
                            <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $stats['active_staff'] ?></strong><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Across all offices</small>
                        </article>
                    </div>
                    <div class="overview-split" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <section class="workload" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div class="section-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office workload</h2><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Open concerns</span>
                            </div>
                            <div id="officeWorkload" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                        </section>
                        <section class="status-summary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern status</h2>
                            <div id="statusSummary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                            <div class="system-totals" id="systemTotals" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                        </section>
                    </div>
                    <section class="assignment-summary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div class="section-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Awaiting assignment</h2><a href="#concerns" id="viewUnassigned" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>View all
                                <?= $icon('arrow-up-right') ?></a>
                        </div>
                        <div class="table-scroll" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <table <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <thead <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                    <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                        <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern</th>
                                        <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</th>
                                        <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Priority</th>
                                        <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Received</th>
                                        <th class="align-right" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="overviewQueue" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></tbody>
                            </table>
                        </div>
                    </section>
                </section>
                <section id="view-staff" class="admin-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="staffTitle" hidden>
                    <div class="page-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h1 id="staffTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Staff accounts</h1>
                            <p id="staffSummary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Loading staff...</p>
                        </div><button type="button" class="button primary" id="addStaff" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('plus') ?>Add
                            staff</button>
                    </div>
                    <div class="filter-bar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label class="search-field" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('search') ?><input type="search"
                                id="staffSearch" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> placeholder="Search staff" aria-label="Search staff"
                                maxlength="150"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</span><select
                                id="staffOffice" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All offices</option><?php foreach ($offices as $office): ?>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account status</span><select id="staffStatus" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All statuses</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="1">Active</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="0">Inactive</option>
                            </select></label></div>
                    <div class="table-scroll" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <table <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <thead <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Staff member</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Status</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Open concerns</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Last sign-in</th>
                                    <th class="align-right" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="staffRows" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="staffPagination" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                </section>
                <section id="view-knowledge" class="admin-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="knowledgeTitle" hidden>
                    <div class="page-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h1 id="knowledgeTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Knowledge base</h1>
                            <p id="knowledgeSummary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Loading entries...</p>
                        </div><button type="button" class="button primary" id="addKnowledge" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('plus') ?>Add
                            entry</button>
                    </div>
                    <div class="filter-bar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label class="search-field" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('search') ?><input type="search"
                                id="knowledgeSearch" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> placeholder="Search entries" aria-label="Search knowledge entries"
                                maxlength="150"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</span><select
                                id="knowledgeOffice" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All offices</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="global">General / All offices</option>
                                <?php foreach ($offices as $office): ?>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Publishing status</span><select
                                id="knowledgeStatus" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All statuses</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Published</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Draft</option>
                            </select></label></div>
                    <div class="knowledge-list" id="knowledgeEntries" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                    <div class="pagination" id="knowledgePagination" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                </section>
                <section id="view-concerns" class="admin-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="concernsTitle" hidden>
                    <div class="page-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h1 id="concernsTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concerns</h1>
                            <p id="concernsSummary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Loading concerns...</p>
                        </div><a href="#reports" class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('file-chart-column') ?>Reports</a>
                    </div>
                    <form class="filter-bar" id="concernFilters" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label
                            class="search-field" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= $icon('search') ?><input type="search" id="concernSearch" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                name="search" placeholder="Search concern or student" aria-label="Search concerns"
                                maxlength="150"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</span><select
                                id="concernOffice" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> name="office_id">
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All offices</option><?php foreach ($offices as $office): ?>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?>
                                    </option><?php endforeach; ?>
                            </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern status</span><select
                                id="concernStatus" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> name="status">
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All statuses</option><?php foreach (Inquiry::STATUSES as $status): ?>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($status) ?></option><?php endforeach; ?>
                            </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Assignment</span><select
                                id="concernAssignment" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> name="assignment">
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All assignments</option>
                                <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="unassigned">Unassigned</option>
                            </select></label></form>
                    <div class="table-scroll" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <table <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <thead <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <tr <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern / Student</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Priority</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Status</th>
                                    <th <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Assigned staff</th>
                                    <th class="align-right" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Action</th>
                                </tr>
                            </thead>
                            <tbody id="concernRows" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></tbody>
                        </table>
                    </div>
                    <div class="pagination" id="concernPagination" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
                </section>
                <section id="view-reports" class="admin-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="reportsTitle" hidden>
                    <div class="page-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <h1 id="reportsTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Reports</h1>
                            <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Inquiries &amp; concerns</p>
                        </div>
                    </div>
                    <form id="reportForm" class="report-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <fieldset class="segmented" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <legend class="sr-only" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Report type</legend><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="radio" name="type"
                                    value="concerns" checked><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concerns</span></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="radio"
                                    name="type" value="inquiries"><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Ben inquiries</span></label>
                        </fieldset>
                        <div class="report-fields" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>From<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="date" name="from" required
                                    value="<?= date('Y-m-01') ?>"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>To<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="date" name="to" required
                                    value="<?= date('Y-m-d') ?>"></label><label id="reportOfficeField" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office<select <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                                    name="office_id">
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All offices</option><?php foreach ($offices as $office): ?>
                                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>">
                                            <?= $escape($office['office_name']) ?></option><?php endforeach; ?>
                                </select></label><label id="reportStatusField" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Status<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="status">
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All statuses</option>
                                    <?php foreach (Inquiry::STATUSES as $status): ?>
                                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($status) ?></option><?php endforeach; ?>
                                </select></label><label id="reportCategoryField" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>Category<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="category"
                                    disabled>
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All categories</option>
                                    <?php $categories = getDbConnection()->query('SELECT DISTINCT category FROM chat_sessions ORDER BY category');
                                    while ($category = $categories->fetch_assoc()): ?>
                                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($category['category']) ?></option><?php endwhile; ?>
                                </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Program<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="program_id">
                                    <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">All programs</option>
                                    <?php foreach ($schoolPrograms as $program): ?>
                                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $program['program_id'] ?>"><?= $escape($program['name']) ?>
                                        </option><?php endforeach; ?>
                                </select></label><button type="submit"
                                class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= $icon('file-chart-column') ?>Generate report</button></div>
                    </form>
                    <div class="report-empty" id="reportEmpty" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $icon('chart-no-axes-combined') ?>
                        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No report generated</h2>
                    </div>
                    <section id="reportPreview" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>
                        <div class="section-heading report-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <h2 id="reportPreviewTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></h2>
                                <p id="reportPreviewMeta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></p>
                            </div>
                            <div class="report-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button"
                                    id="printReport" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= $icon('printer') ?>Print / PDF</button><button type="button"
                                    class="button primary" id="downloadReport" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= $icon('download') ?>Download
                                    CSV</button></div>
                        </div>
                        <div class="table-scroll" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <table <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                <thead id="reportHead" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></thead>
                                <tbody id="reportRows" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></tbody>
                            </table>
                        </div>
                        <p class="report-footer" id="reportFooter" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></p>
                    </section>
                </section>
            </div>
        </main>
    </div>
    <dialog class="admin-dialog" id="staffDialog" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="staffDialogTitle">
        <form id="staffForm" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="dialog-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h2 id="staffDialogTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Add staff</h2><button type="button" class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="staffDialog" aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="user_id">
            <div class="form-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>First name<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="first_name" required maxlength="100"
                        autocomplete="given-name"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Last name<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="last_name" required
                        maxlength="100" autocomplete="family-name"></label><label class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Email address<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="email"
                        name="email" required maxlength="150" autocomplete="email"></label><label
                    class="full" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>Office assignment<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="office_id" required>
                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select office</option><?php foreach ($offices as $office): ?>
                            <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select></label><label class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span id="staffPasswordLabel" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Password (8+ characters)</span><input <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        type="password" name="password" minlength="8" maxlength="72"
                        autocomplete="new-password" placeholder="Minimum 8 characters"></label><label class="checkbox-label full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="checkbox"
                        name="is_active" checked>Active account</label></div>
            <p class="form-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert" hidden></p>
            <div class="dialog-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="staffDialog">Cancel</button><button type="submit" class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Save staff
                    account</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog knowledge-dialog" id="knowledgeDialog" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="knowledgeDialogTitle">
        <form id="knowledgeForm" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="dialog-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h2 id="knowledgeDialogTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Add knowledge entry</h2><button type="button" class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="knowledgeDialog" aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="entry_id">
            <div class="form-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Title<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="title" required
                        maxlength="150"></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Office<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="office_id">
                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">General / All offices</option><?php foreach ($offices as $office): ?>
                            <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="<?= (int) $office['office_id'] ?>"><?= $escape($office['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select></label><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Status<select <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="status">
                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Draft</option>
                        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Published</option>
                    </select></label><label class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Content<textarea <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="content" rows="10" required
                        maxlength="6000"></textarea></label></div>
            <p class="form-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert" hidden></p>
            <div class="dialog-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="knowledgeDialog">Cancel</button><button type="submit" class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Save
                    entry</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog concern-dialog" id="concernDialog" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="concernDialogTitle">
        <div class="dialog-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>><small id="concernDialogId" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></small>
                <h2 id="concernDialogTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern</h2>
            </div><button type="button" class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-close="concernDialog" aria-label="Close"
                title="Close"><?= $icon('x') ?></button>
        </div>
        <div id="concernDetail" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>
        <form id="assignmentForm" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="inquiry_id"><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Assigned staff<select name="staff_id"
                    id="assignmentStaff" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>></select></label>
            <p class="form-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert" hidden></p>
            <div class="dialog-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="concernDialog">Close</button><button type="submit" class="button primary"
                    id="saveAssignment" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>Save assignment</button></div>
        </form>
    </dialog>
    <dialog class="admin-dialog confirm-dialog" id="deleteDialog" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="deleteTitle"
        aria-describedby="deleteMessage">
        <form id="deleteForm" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="dialog-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h2 id="deleteTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Delete?</h2><button type="button" class="icon-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-close="deleteDialog"
                    aria-label="Close" title="Close"><?= $icon('x') ?></button>
            </div>
            <p id="deleteMessage" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></p>
            <p class="form-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert" hidden></p>
            <div class="dialog-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    data-close="deleteDialog">Cancel</button><button type="submit" class="button danger" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Delete</button>
            </div>
        </form>
    </dialog>
    <dialog class="action-confirm-dialog" id="actionConfirmDialog" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="actionConfirmTitle"
        aria-describedby="actionConfirmMessage">
        <div class="action-confirm-icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
            <svg <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
                <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M12 3 3.8 7v5.3c0 4.2 3.5 7.9 8.2 9.2 4.7-1.3 8.2-5 8.2-9.2V7L12 3Z" />
                <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M12 8v4m0 4h.01" />
            </svg>
        </div>
        <h2 id="actionConfirmTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Please confirm</h2>
        <p id="actionConfirmMessage" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></p>
        <div class="action-confirm-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <button type="button" class="action-confirm-continue" id="actionConfirmContinue" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Confirm</button>
            <button type="button" class="action-confirm-cancel" id="actionConfirmCancel" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Cancel</button>
        </div>
    </dialog>
    <div class="admin-toast" id="adminToast" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status" aria-live="polite" hidden></div>
    <script>window.ADMIN_BOOTSTRAP = <?= json_encode(['offices' => $offices, 'overview' => $overview], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
    <script src="assets/js/student_account_actions.js?v=<?= md5_file(__DIR__ . '/assets/js/student_account_actions.js') ?>" defer></script>
</body>

</html>
