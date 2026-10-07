<?php
/**
 * dashboard_staff.php
 * HELPDESKCRMC — Staff Operations & Concern Queue Portal
 */

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/Inquiry.php';
require_once __DIR__ . '/../app/models/Office.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
require_once __DIR__ . '/../app/helpers/office_logo.php';

session_start();
requireLogin();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin')) {
  header('Location: login.php');
  exit;
}

$staffId = (int) $_SESSION['user_id'];
$currentUser = (new User())->findById($staffId);

// For admin users, show all inquiries. For staff, show only their office's inquiries.
$officeId = null;
if ($_SESSION['role'] === 'staff') {
  $officeId = $currentUser && !empty($currentUser['office_id'])
    ? (int) $currentUser['office_id']
    : null;
  if ($officeId === null) {
    http_response_code(403);
    exit('Your staff account is not assigned to an office.');
  }
}

$_SESSION['office_id'] = $officeId;

$inquiryModel = new Inquiry();
$officeModel = new Office();

$officesList = $officeModel->findAll();
$officeName = 'General Support';
$officeIcon = 'G';
$officeLogo = null;
if ($officeId) {
  $officeData = $officeModel->findById($officeId);
  if ($officeData) {
    $officeName = $officeData['office_name'];
    $officeIcon = strtoupper(substr($officeData['office_name'], 0, 1));
    $officeLogo = officeLogoPath($officeName);
  }
}

$stats = $inquiryModel->getStats($officeId);
$inquiries = $inquiryModel->findByOffice($officeId, null, true);
$initials = strtoupper(substr($_SESSION['name'], 0, 1));

// Count by status
$pendingCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
foreach ($inquiries as $inquiry) {
  if ($inquiry['status'] === 'Pending')
    $pendingCount++;
  if ($inquiry['status'] === 'In Progress')
    $inProgressCount++;
  if ($inquiry['status'] === 'Resolved')
    $resolvedCount++;
}
?>
<!DOCTYPE html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <title>Staff Portal - HELPDESKCRMC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <?= stylesheet_bundle('dashboard_staff') ?>
  <script src="assets/js/notifications.js?v=1" defer></script>
  <script src="assets/js/workspace.js?v=<?= md5_file(__DIR__ . '/assets/js/workspace.js') ?>" defer></script>
</head>

<body <?= dev_locator_attributes(__FILE__, __LINE__) ?>>

  <svg <?= dev_locator_attributes(__FILE__, __LINE__) ?> style="display:none" aria-hidden="true">
    <defs <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <symbol id="i-chat" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
      </symbol>
      <symbol id="i-clock" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <circle <?= dev_locator_attributes(__FILE__, __LINE__) ?> cx="12" cy="12" r="9" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M12 7v5l3 3" />
      </symbol>
      <symbol id="i-user" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <circle <?= dev_locator_attributes(__FILE__, __LINE__) ?> cx="12" cy="8" r="3.5" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5" />
      </symbol>
      <symbol id="i-logout" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M14 8l4 4-4 4" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M18 12H9" />
      </symbol>
      <symbol id="i-search" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <circle <?= dev_locator_attributes(__FILE__, __LINE__) ?> cx="11" cy="11" r="7" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="m21 21-4.35-4.35" />
      </symbol>
      <symbol id="i-send" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="m22 2-7 20-4-9-9-4 20-7z" />
      </symbol>
      <symbol id="i-check" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M5 13l4 4L19 7" />
      </symbol>
      <symbol id="i-paperclip" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7" />
      </symbol>
      <symbol id="i-building" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
        <rect <?= dev_locator_attributes(__FILE__, __LINE__) ?> x="3" y="4" width="18" height="16" rx="2" />
        <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M9 8h6M9 12h6M9 16h4" />
      </symbol>
    </defs>
  </svg>

  <div class="app" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <!-- Sidebar -->
    <aside class="sidebar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <div class="brand" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
        <div class="brand-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Helpdesk<span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>CRMC</span></div>
      </div>

      <div class="office-card" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="office-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Assigned Office</div>
        <div class="office-identity" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <?php if ($officeLogo): ?>
            <img class="office-identity-logo" <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="<?= htmlspecialchars($officeLogo, ENT_QUOTES, 'UTF-8') ?>" alt=""
              loading="lazy">
          <?php else: ?>
            <span class="office-identity-initial" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true"><?= htmlspecialchars($officeIcon) ?></span>
          <?php endif; ?>
          <div class="office-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($officeName) ?></div>
        </div>
      </div>

      <div class="nav-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="nav-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Workspace</div>
        <a class="nav-item active" href="#concerns" id="navConcerns" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-chat" />
          </svg>
          Concerns Queue
        </a>
        <a class="nav-item" href="#history" id="navHistory" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-clock" />
          </svg>
          History
        </a>
      </div>

      <div class="sidebar-spacer" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></div>

      <a href="logout.php" class="nav-item student-logout-link" <?= dev_locator_attributes(__FILE__, __LINE__) ?> style="margin-top:8px;"><svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-logout" />
        </svg>
        Logout</a>

      <div class="sidebar-foot" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="sidebar-profile" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <div class="avatar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($initials) ?></div>
          <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="user-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($_SESSION['name']) ?></div>
            <div class="user-sub" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Staff Member</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="main" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <!-- Header -->
      <header class="header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="header-top" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <div class="header-title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <h1 id="mainTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concerns Queue</h1>
              <div class="header-subtitle" id="mainSubtitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Manage and respond to student inquiries</div>
            </div>
            <img class="mobile-header-logo" <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
          </div>
          <div class="header-tools" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="header-stats" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="stat-box" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="stat-value pending" id="pendingCount" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $pendingCount ?></div>
                <div class="stat-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Pending</div>
              </div>
              <div class="stat-box" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="stat-value progress" id="inProgressCount" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $inProgressCount ?></div>
                <div class="stat-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>In Progress</div>
              </div>
              <div class="stat-box" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="stat-value resolved" id="resolvedCount" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $resolvedCount ?></div>
                <div class="stat-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Resolved</div>
              </div>
            </div>
            <div class="search-bar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
                <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-search" />
              </svg>
              <input type="search" placeholder="Search inquiry..." id="searchInput" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                aria-label="Search concerns by subject, student, or inquiry ID">
            </div>
            <?php require __DIR__ . '/assets/components/notification-center.php'; ?>
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <div class="content-area" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-view="list">
        <!-- Queue Panel -->
        <div class="queue-panel" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <div class="queue-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <h3 class="queue-title" id="queueTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Active Concerns</h3>
            <div class="queue-controls" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="filter-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="group" aria-label="Filter concerns">
                <button class="filter-btn active" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" data-status="all" aria-pressed="true">All</button>
                <button class="filter-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" data-status="pending" aria-pressed="false">Pending</button>
                <button class="filter-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" data-status="in_progress" aria-pressed="false">In
                  Progress</button>
                <button class="filter-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" data-status="resolved" aria-pressed="false">Resolved</button>
                <button class="filter-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" data-status="needs_triage" aria-pressed="false">Needs
                  triage</button>
              </div>
              <div class="sort-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <label for="sortSelect" class="sort-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sort by:</label>
                <select id="sortSelect" class="sort-select" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Sort concerns">
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="urgency">Urgency (High to Low)</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="time-newest">Time (Newest First)</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="time-oldest">Time (Oldest First)</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="status">Status</option>
                </select>
              </div>
            </div>
          </div>

          <div class="queue-list" id="concernsList" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <?php if (empty($inquiries)): ?>
              <div class="empty-state" id="queueEmptyNotice" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
                  <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                </svg>
                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No concerns in queue</p>
              </div>
            <?php else: ?>
              <?php foreach ($inquiries as $index => $inquiry): ?>
                <?php $urgency = $inquiry['urgency_priority'] ?? 'Needs triage'; ?>
                <?php $urgencyClass = strtolower(str_replace(['/', ' '], '-', $urgency)); ?>
                <button class="concern-card <?= $index === 0 ? 'selected' : '' ?>" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button"
                  data-inquiry-id="<?= $inquiry['inquiry_id'] ?>"
                  data-status="<?= htmlspecialchars(str_replace(' ', '_', strtolower($inquiry['status'])), ENT_QUOTES, 'UTF-8') ?>"
                  data-priority="<?= htmlspecialchars($urgencyClass, ENT_QUOTES, 'UTF-8') ?>"
                  data-created-at="<?= htmlspecialchars($inquiry['created_at'], ENT_QUOTES, 'UTF-8') ?>"
                  aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                  <span class="concern-card-topline" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <span
                      class="concern-status-badge status-<?= htmlspecialchars(str_replace(' ', '-', strtolower($inquiry['status'])), ENT_QUOTES, 'UTF-8') ?>" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>
                      <?= htmlspecialchars($inquiry['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span
                      class="urgency-badge urgency-<?= htmlspecialchars($urgencyClass, ENT_QUOTES, 'UTF-8') ?>" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars($urgency, ENT_QUOTES, 'UTF-8') ?></span>
                  </span>
                  <span class="concern-subject" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($inquiry['subject'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if (!empty($inquiry['duplicate_of_inquiry_id'])): ?>
                    <span class="duplicate-flag" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Possible repeat · linked to
                      INQ-<?= (int) $inquiry['duplicate_of_inquiry_id'] ?></span>
                  <?php endif; ?>
                  <span class="concern-meta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <span
                      class="concern-student" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars($inquiry['student_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span
                      class="concern-time" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars(date('M j, g:i A', strtotime($inquiry['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                  </span>
                  <span class="concern-id" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>INQ-<?= (int) $inquiry['inquiry_id'] ?></span>
                </button>
              <?php endforeach; ?>
              <div class="empty-state" id="queueEmptyNotice" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>
                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No concerns match this filter.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- History Panel -->
        <div class="history-panel" id="historyPanel" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>
          <div class="queue-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <h3 class="queue-title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Resolved Concerns</h3>
            <div class="queue-controls" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="history-search" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
                  <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-search" />
                </svg>
                <input type="search" placeholder="Search resolved concerns..." id="historySearch" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                  aria-label="Search resolved concerns by subject, student, or inquiry ID">
              </div>
              <div class="sort-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <label for="historySortSelect" class="sort-label" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sort by:</label>
                <select id="historySortSelect" class="sort-select" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Sort resolved concerns">
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="resolved-newest">Recently Resolved</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="resolved-oldest">Oldest First</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="subject">Subject A-Z</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="student">Student Name</option>
                </select>
              </div>
            </div>
          </div>

          <div class="queue-list" id="historyList" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="empty-state" id="historyEmptyNotice" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
                <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No resolved concerns found</p>
            </div>
          </div>
        </div>

        <!-- Detail Panel -->
        <div class="detail-panel" id="detailPanel" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <?php if (!empty($inquiries)): ?>
            <?php $firstInquiry = $inquiries[0]; ?>
            <?php $firstUrgency = $firstInquiry['urgency_priority'] ?? 'Needs triage'; ?>
            <?php $firstUrgencyClass = strtolower(str_replace(['/', ' '], '-', $firstUrgency)); ?>
            <div class="mobile-detail-nav" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <button class="back-to-queue" id="backToQueue" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button">
                <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
                  <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-back" />
                </svg>
                Back to Queue
              </button>
            </div>
            <div class="detail-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="detail-header-top" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="detail-title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <div class="detail-overline" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <span class="detail-id" id="detailId" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>INQ-<?= (int) $firstInquiry['inquiry_id'] ?></span>
                    <span
                      class="concern-status-badge status-<?= htmlspecialchars(str_replace(' ', '-', strtolower($firstInquiry['status'])), ENT_QUOTES, 'UTF-8') ?>"
                      id="detailStatus" <?= dev_locator_attributes(__FILE__, __LINE__ - 2) ?>><?= htmlspecialchars($firstInquiry['status'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="urgency-badge urgency-<?= htmlspecialchars($firstUrgencyClass, ENT_QUOTES, 'UTF-8') ?>"
                      id="detailUrgency" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars($firstUrgency, ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                  <h2 id="detailTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($firstInquiry['subject'], ENT_QUOTES, 'UTF-8') ?></h2>
                  <div class="duplicate-indicator" id="duplicateIndicator" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>
                    <span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>This concern was submitted as a possible repeat of</span>
                    <button type="button" id="duplicateRootLink" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></button>
                  </div>
                  <p class="urgency-reason" id="urgencyReason" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <?= htmlspecialchars($firstInquiry['priority_override'] !== null ? 'Staff-set urgency. ' : '', ENT_QUOTES, 'UTF-8') ?>  <?= htmlspecialchars($firstInquiry['ai_priority_reason'] ?? 'Automated urgency review is unavailable. Please assess this concern during triage.', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <p class="urgency-meta" id="urgencyMeta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <?= $firstInquiry['priority_override'] !== null ? 'Staff override' . (!empty($firstInquiry['priority_override_staff_name']) ? ' by ' . htmlspecialchars($firstInquiry['priority_override_staff_name'], ENT_QUOTES, 'UTF-8') : '') : ($firstInquiry['ai_priority_confidence'] !== null ? 'AI estimate · ' . (int) round((float) $firstInquiry['ai_priority_confidence'] * 100) . '% confidence' : 'Needs staff triage') ?>
                  </p>
                  <div class="detail-meta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <div class="meta-item" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-user" />
                      </svg>
                      <span class="meta-label"
                        id="detailStudent" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars($firstInquiry['student_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="meta-item" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <span id="detailStudentNumber" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>ID:
                        <?= htmlspecialchars($firstInquiry['student_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="meta-item" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><span
                        id="detailAcademic" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars(implode(' | ', array_filter([$firstInquiry['student_program'] ?? '', !empty($firstInquiry['student_year_level']) ? 'Year ' . $firstInquiry['student_year_level'] : '', $firstInquiry['student_section'] ?? '', $firstInquiry['student_academic_year'] ?? '', $firstInquiry['student_semester'] ?? ''])) ?: 'Academic details not recorded', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="meta-item" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <span id="detailDate" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Submitted
                        <?= htmlspecialchars(date('M j, Y \a\t g:i A', strtotime($firstInquiry['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                </div>
                <div class="detail-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <button class="btn btn-success" id="resolveButton" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" <?= $firstInquiry['status'] === 'Resolved' ? 'disabled' : '' ?>>
                    <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
                      <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-check" />
                    </svg>
                    <span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Mark Resolved</span>
                  </button>
                  <button class="btn btn-secondary" id="reassignButton" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button">Reassign</button>
                </div>
              </div>
            </div>

            <div class="thread" id="messageThread" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="message student" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="message-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($firstInquiry['student_name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="message-bubble" id="originalMessage" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <?= nl2br(htmlspecialchars($firstInquiry['description'], ENT_QUOTES, 'UTF-8')) ?></div>
                <div class="message-time" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <?= htmlspecialchars(date('M j, g:i A', strtotime($firstInquiry['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
            </div>

            <div class="reply-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="status-row" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="statusSelect">Status</label>
                <select id="statusSelect" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="Pending" <?= $firstInquiry['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="In Progress" <?= $firstInquiry['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress
                  </option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="On Hold" <?= $firstInquiry['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                  <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="Resolved" <?= $firstInquiry['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                </select>
                <details class="priority-disclosure" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <summary <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Urgency</span>
                    <strong
                      id="urgencyControlSummary" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>><?= htmlspecialchars($firstUrgency, ENT_QUOTES, 'UTF-8') ?></strong>
                  </summary>
                  <div class="priority-popover" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="priorityOverride">Staff urgency override</label>
                    <select id="priorityOverride" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="" <?= $firstInquiry['priority_override'] === null ? 'selected' : '' ?>>Keep AI
                        recommendation</option>
                      <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="Critical/Urgent" <?= $firstInquiry['priority_override'] === 'Critical/Urgent' ? 'selected' : '' ?>>Critical/Urgent</option>
                      <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="High" <?= $firstInquiry['priority_override'] === 'High' ? 'selected' : '' ?>>High
                      </option>
                      <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="Normal" <?= $firstInquiry['priority_override'] === 'Normal' ? 'selected' : '' ?>>Normal
                      </option>
                      <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="Low" <?= $firstInquiry['priority_override'] === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                    <button type="button" class="btn btn-secondary" id="savePriorityOverride" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Save urgency</button>
                  </div>
                </details>
              </div>
              <form id="replyForm" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="reply-box" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                  <textarea class="reply-input" id="replyText" <?= dev_locator_attributes(__FILE__, __LINE__) ?> placeholder="Compose your response..."
                    aria-label="Compose your response" required></textarea>
                  <div class="reply-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <button type="submit" class="btn btn-primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-send" />
                      </svg>
                      Send
                    </button>
                    <button type="button" class="btn btn-secondary" id="attachButton" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-paperclip" />
                      </svg>
                      Attach File
                    </button>
                  </div>
                </div>
              </form>
            </div>
          <?php else: ?>
            <div class="no-selection" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
                <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
              </svg>
              <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Select a concern to view details</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <nav class="mobile-nav" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Mobile navigation">
    <button class="mobile-nav-item active" type="button" data-mobile-view="queue" id="mobileQueueBtn" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-chat" />
      </svg><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Queue</span>
    </button>
    <button class="mobile-nav-item" type="button" data-mobile-view="history" id="mobileHistoryBtn" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-clock" />
      </svg><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>History</span>
    </button>
    <button class="mobile-nav-item" id="mobileProfileToggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="button" aria-expanded="false"
      aria-controls="mobileProfileMenu">
      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-user" />
      </svg><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Profile</span>
    </button>
  </nav>
  <div class="mobile-profile-menu" id="mobileProfileMenu" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>
    <div class="mobile-profile-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?></div>
    <div class="mobile-profile-role" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Staff Member · <?= htmlspecialchars($officeName, ENT_QUOTES, 'UTF-8') ?></div>
    <a href="logout.php" class="mobile-profile-logout student-logout-link" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true">
        <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-logout" />
      </svg>Logout
    </a>
  </div>

  <script src="assets/js/inquiry_attachments.js?v=<?= md5_file(__DIR__ . '/assets/js/inquiry_attachments.js') ?>"></script>
  <script>
    const inquiriesData = <?= json_encode($inquiries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    let currentInquiryId = inquiriesData.length ? Number(inquiriesData[0].inquiry_id) : null;
    let activeStatusFilter = 'all';
    let responseRequestId = 0;
    let staffReplySending = false;
    let staffResolving = false;
    const workspace = document.querySelector('.content-area');
    const thread = document.getElementById('messageThread');

    function statusKey(status) {
      return String(status || '').toLowerCase().replace(/\s+/g, '_');
    }

    function priorityClass(priority) {
      return String(priority || 'Needs triage').toLowerCase().replace(/[\/\s]+/g, '-');
    }

    function priorityRank(priority) {
      return {
        'needs-triage': 0,
        'critical-urgent': 1,
        high: 2,
        normal: 3,
        low: 4
      }[priorityClass(priority)] ?? 0;
    }

    function reorderConcernCards() {
      const list = document.getElementById('concernsList');
      if (!list) return;
      const cards = [...list.querySelectorAll('.concern-card')];
      cards.sort((first, second) => {
        const firstResolved = first.dataset.status === 'resolved';
        const secondResolved = second.dataset.status === 'resolved';
        if (firstResolved !== secondResolved) return firstResolved ? 1 : -1;
        const rankDifference = priorityRank(first.dataset.priority) - priorityRank(second.dataset.priority);
        if (rankDifference !== 0) return rankDifference;
        return new Date(first.dataset.createdAt).getTime() - new Date(second.dataset.createdAt).getTime();
      });
      cards.forEach(card => list.insertBefore(card, document.getElementById('queueEmptyNotice')));
    }

    function formatDate(value, options) {
      const date = new Date(value);
      return Number.isNaN(date.getTime()) ? '' : date.toLocaleString('en-US', options);
    }

    function createMessage(role, name, text, date) {
      const message = document.createElement('div');
      message.className = `message ${role}`;
      const header = document.createElement('div');
      header.className = 'message-header';
      header.textContent = name || 'Staff';
      const bubble = document.createElement('div');
      bubble.className = 'message-bubble';
      bubble.textContent = text || '';
      const time = document.createElement('time');
      time.className = 'message-time';
      time.textContent = formatDate(date, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
      message.append(header, bubble, time);
      return message;
    }

    function updateDuplicateIndicator(inquiry) {
      const indicator = document.getElementById('duplicateIndicator');
      const rootLink = document.getElementById('duplicateRootLink');
      const rootId = Number(inquiry.duplicate_of_inquiry_id);
      if (!indicator || !rootLink) return;

      indicator.hidden = !Number.isInteger(rootId) || rootId <= 0;
      if (indicator.hidden) return;

      rootLink.textContent = `INQ-${rootId}`;
      const rootConcern = inquiriesData.find(item => Number(item.inquiry_id) === rootId);
      rootLink.disabled = !rootConcern;
      rootLink.onclick = () => {
        if (!rootConcern) return;
        const rootCard = document.querySelector(`[data-inquiry-id="${rootId}"]`);
        if (rootCard) selectConcern(rootCard, rootId);
      };
    }

    function updateConcernStatus(inquiry, status) {
      inquiry.status = status;
      const card = document.querySelector(`[data-inquiry-id="${Number(inquiry.inquiry_id)}"]`);
      if (card) {
        card.dataset.status = statusKey(status);
        const badge = card.querySelector('.concern-status-badge');
        badge.textContent = status;
        badge.className = `concern-status-badge status-${statusKey(status).replace(/_/g, '-')}`;
      }
      if (Number(currentInquiryId) === Number(inquiry.inquiry_id)) {
        const detailStatus = document.getElementById('detailStatus');
        detailStatus.textContent = status;
        detailStatus.className = `concern-status-badge status-${statusKey(status).replace(/_/g, '-')}`;
        document.getElementById('statusSelect').value = status;
        document.getElementById('resolveButton').disabled = status === 'Resolved' || staffResolving;
      }
      document.getElementById('pendingCount').textContent = inquiriesData.filter(item => item.status === 'Pending').length;
      document.getElementById('inProgressCount').textContent = inquiriesData.filter(item => item.status === 'In Progress').length;
      document.getElementById('resolvedCount').textContent = inquiriesData.filter(item => item.status === 'Resolved').length;
      reorderConcernCards();
      applyQueueFilters();
    }

    function updateConcernPriority(inquiry, priority, override, actorName, confidence) {
      inquiry.urgency_priority = priority;
      inquiry.priority_override = override;
      inquiry.priority_override_staff_name = actorName || null;
      if (confidence !== undefined) inquiry.ai_priority_confidence = confidence;
      const card = document.querySelector(`[data-inquiry-id="${Number(inquiry.inquiry_id)}"]`);
      if (card) {
        card.dataset.priority = priorityClass(priority);
        const badge = card.querySelector('.urgency-badge');
        badge.textContent = priority;
        badge.className = `urgency-badge urgency-${priorityClass(priority)}`;
      }
      if (Number(currentInquiryId) === Number(inquiry.inquiry_id)) {
        const badge = document.getElementById('detailUrgency');
        badge.textContent = priority;
        badge.className = `urgency-badge urgency-${priorityClass(priority)}`;
        document.getElementById('urgencyControlSummary').textContent = priority;
        document.getElementById('priorityOverride').value = override || '';
        document.getElementById('urgencyReason').textContent =
          `${override ? 'Staff-set urgency. ' : ''}${inquiry.ai_priority_reason || 'Automated urgency review is unavailable. Please assess this concern during triage.'}`;
        document.getElementById('urgencyMeta').textContent = override
          ? `Staff override${actorName ? ` by ${actorName}` : ''}`
          : (inquiry.ai_priority_confidence !== null && inquiry.ai_priority_confidence !== undefined
            ? `AI estimate · ${Math.round(Number(inquiry.ai_priority_confidence) * 100)}% confidence`
            : 'Needs staff triage');
      }
      reorderConcernCards();
      applyQueueFilters();
    }

    function renderSelectedInquiry(inquiry) {
      document.getElementById('detailId').textContent = `INQ-${inquiry.inquiry_id}`;
      document.getElementById('detailTitle').textContent = inquiry.subject || '';
      updateDuplicateIndicator(inquiry);
      document.getElementById('detailStudent').textContent = inquiry.student_name || '';
      document.getElementById('detailStudentNumber').textContent = `ID: ${inquiry.student_number || 'N/A'}`;
      document.getElementById('detailAcademic').textContent = [inquiry.student_program, inquiry.student_year_level ? `Year ${inquiry.student_year_level}` : '', inquiry.student_section, inquiry.student_academic_year, inquiry.student_semester].filter(Boolean).join(' | ') || 'Academic details not recorded';
      document.getElementById('detailDate').textContent = `Submitted ${formatDate(inquiry.created_at, {
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
      })}`;
      const priority = inquiry.urgency_priority || 'Needs triage';
      const urgencyBadge = document.getElementById('detailUrgency');
      urgencyBadge.textContent = priority;
      urgencyBadge.className = `urgency-badge urgency-${priorityClass(priority)}`;
      document.getElementById('urgencyControlSummary').textContent = priority;
      document.getElementById('urgencyReason').textContent =
        `${inquiry.priority_override ? 'Staff-set urgency. ' : ''}${inquiry.ai_priority_reason || 'Automated urgency review is unavailable. Please assess this concern during triage.'}`;
      document.getElementById('urgencyMeta').textContent = inquiry.priority_override
        ? `Staff override${inquiry.priority_override_staff_name ? ` by ${inquiry.priority_override_staff_name}` : ''}`
        : (inquiry.ai_priority_confidence !== null && inquiry.ai_priority_confidence !== undefined
          ? `AI estimate · ${Math.round(Number(inquiry.ai_priority_confidence) * 100)}% confidence`
          : 'Needs staff triage');
      document.getElementById('priorityOverride').value = inquiry.priority_override || '';
      document.getElementById('statusSelect').value = inquiry.status;
      document.getElementById('resolveButton').disabled = inquiry.status === 'Resolved' || staffResolving;
      thread.replaceChildren(createMessage('student', inquiry.student_name, inquiry.description, inquiry.created_at));
      loadInquiryResponses(Number(inquiry.inquiry_id));
    }

    function selectConcern(element, inquiryId) {
      document.querySelectorAll('.concern-card').forEach(el => {
        const selected = el === element;
        el.classList.toggle('selected', selected);
        el.setAttribute('aria-pressed', String(selected));
      });
      element.classList.add('selected');
      currentInquiryId = Number(inquiryId);
      const inquiry = inquiriesData.find(item => Number(item.inquiry_id) === currentInquiryId);
      if (!inquiry) return;
      renderSelectedInquiry(inquiry);
      workspace.dataset.view = 'detail';
      document.body.classList.add('detail-open');
    }

    async function loadInquiryResponses(inquiryId) {
      const requestId = ++responseRequestId;
      try {
        const response = await fetch(`api/get_responses.php?inquiry_id=${inquiryId}`, {
          headers: {
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
          }
        });

        if (!response.ok) throw new Error('Failed to load responses');
        const data = await response.json();
        if (requestId !== responseRequestId || Number(currentInquiryId) !== inquiryId) return;
        const originalBubble = thread.querySelector('.message.student .message-bubble');
        if (originalBubble) {
          originalBubble.querySelector('.inquiry-attachments')?.remove();
          originalBubble.append(InquiryAttachments.create(data.attachments || []));
        }
        (data.responses || []).forEach(item => thread.appendChild(createMessage(item.sender_role === 'student' ? 'student' : 'staff', item.staff_name, item.message, item.created_at)));
        thread.scrollTop = thread.scrollHeight;
      } catch (error) {
        console.error('Error loading responses:', error);
      }
    }

    async function sendReply(event) {
      event.preventDefault();

      if (!currentInquiryId || staffReplySending) return;

      const replyText = document.getElementById('replyText');
      const statusSelect = document.getElementById('statusSelect');
      const inquiryId = Number(currentInquiryId);
      const message = replyText.value.trim();
      const status = statusSelect.value;
      const inquiry = inquiriesData.find(item => Number(item.inquiry_id) === inquiryId);
      if (!message || !inquiry) return;
      const button = document.querySelector('#replyForm button[type="submit"]');
      staffReplySending = true;
      button.disabled = true;
      try {
        if (inquiry.status !== 'Resolved' && status === 'Resolved') {
          const confirmed = await confirmImportantAction({
            title: 'Send reply and resolve concern?',
            message: 'This reply will be sent to the student and the concern will be marked as resolved.',
            confirmLabel: 'Send & resolve',
            destructive: true
          });
          if (!confirmed) return;
        }

        const response = await fetch('api/staff_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            action: 'respond',
            inquiry_id: inquiryId,
            message,
            status
          })
        });

        if (!response.ok) throw new Error('Failed to send response');

        const data = await response.json();

        if (data.success) {
          updateConcernStatus(inquiry, status);
          if (Number(currentInquiryId) === inquiryId) {
            if (replyText.value.trim() === message) replyText.value = '';
            renderSelectedInquiry(inquiry);
          }
        } else {
          alert('Failed to send response: ' + (data.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error sending reply:', error);
        alert('Failed to send response. Please try again.');
      } finally {
        staffReplySending = false;
        button.disabled = false;
      }
    }

    async function resolveConcern() {
      if (!currentInquiryId || staffResolving) return;
      const inquiryId = Number(currentInquiryId);
      const inquiry = inquiriesData.find(item => Number(item.inquiry_id) === inquiryId);
      if (!inquiry || inquiry.status === 'Resolved') return;
      const button = document.getElementById('resolveButton');
      staffResolving = true;
      button.disabled = true;
      try {
        const confirmed = await confirmImportantAction({
          title: 'Mark this concern as resolved?',
          message: 'The concern will move to History. You can still review it later, but it will no longer appear as active.',
          confirmLabel: 'Mark resolved',
          destructive: true
        });
        if (!confirmed) return;
        const response = await fetch('api/staff_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ action: 'update_status', inquiry_id: inquiryId, status: 'Resolved' })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Failed to resolve concern.');
        updateConcernStatus(inquiry, 'Resolved');
      } catch (error) {
        console.error('Error resolving concern:', error);
        alert(error.message || 'Failed to resolve concern. Please try again.');
      } finally {
        staffResolving = false;
        button.disabled = inquiriesData.find(item => Number(item.inquiry_id) === Number(currentInquiryId))?.status === 'Resolved';
      }
    }

    function reassignConcern() {
      if (!currentInquiryId) return;
      alert('Reassignment feature coming soon');
    }

    function attachFile() {
      alert('File attachment feature coming soon');
    }

    async function savePriorityOverride() {
      if (!currentInquiryId) return;
      const inquiry = inquiriesData.find(item => Number(item.inquiry_id) === Number(currentInquiryId));
      if (!inquiry) return;
      const button = document.getElementById('savePriorityOverride');
      if (button.disabled) return;
      button.disabled = true;
      try {
        const response = await fetch('api/staff_action.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({
            action: 'override_priority',
            inquiry_id: currentInquiryId,
            priority: document.getElementById('priorityOverride').value
          })
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Failed to update urgency.');
        updateConcernPriority(inquiry, data.priority, data.priority_override, data.priority_override_staff_name);
        if (Number(currentInquiryId) === Number(inquiry.inquiry_id)) {
          document.querySelector('.priority-disclosure')?.removeAttribute('open');
        }
      } catch (error) {
        console.error('Error updating concern urgency:', error);
        alert(error.message || 'Failed to update urgency. Please try again.');
      } finally {
        button.disabled = false;
      }
    }

    function applyQueueFilters() {
      const query = document.getElementById('searchInput').value.trim().toLowerCase();
      let visibleCount = 0;
      document.querySelectorAll('.concern-card').forEach(card => {
        const matchesStatus = activeStatusFilter === 'all'
          || (activeStatusFilter === 'needs_triage'
            ? card.dataset.priority === 'needs-triage'
            : card.dataset.status === activeStatusFilter);
        const matchesQuery = !query || [
          card.querySelector('.concern-subject').textContent,
          card.querySelector('.concern-student').textContent,
          card.querySelector('.concern-id').textContent
        ].some(value => value.toLowerCase().includes(query));
        card.hidden = !(matchesStatus && matchesQuery);
        if (!card.hidden) visibleCount++;
      });
      const emptyNotice = document.getElementById('queueEmptyNotice');
      if (emptyNotice) emptyNotice.hidden = visibleCount > 0;
    }

    function sortConcernCards() {
      const sortBy = document.getElementById('sortSelect').value;
      const list = document.getElementById('concernsList');
      if (!list) return;

      const cards = [...list.querySelectorAll('.concern-card')];
      const emptyNotice = document.getElementById('queueEmptyNotice');

      cards.sort((a, b) => {
        switch (sortBy) {
          case 'urgency':
            const priorityOrder = {
              'critical-urgent': 0,
              'high': 1,
              'normal': 2,
              'low': 3,
              'needs-triage': 4
            };
            const aPriority = priorityOrder[a.dataset.priority] ?? 4;
            const bPriority = priorityOrder[b.dataset.priority] ?? 4;
            return aPriority - bPriority;

          case 'time-newest':
            return new Date(b.dataset.createdAt).getTime() - new Date(a.dataset.createdAt).getTime();

          case 'time-oldest':
            return new Date(a.dataset.createdAt).getTime() - new Date(b.dataset.createdAt).getTime();

          case 'status':
            const statusOrder = {
              'pending': 0,
              'in_progress': 1,
              'resolved': 2
            };
            const aStatus = statusOrder[a.dataset.status] ?? 999;
            const bStatus = statusOrder[b.dataset.status] ?? 999;
            return aStatus - bStatus;

          default:
            return 0;
        }
      });

      // Reorder the DOM elements
      cards.forEach(card => list.appendChild(card));
      if (emptyNotice) list.appendChild(emptyNotice);
    }

    function setQueueFilter(status) {
      activeStatusFilter = status;
      document.querySelectorAll('.filter-btn').forEach(button => {
        const active = button.dataset.status === status;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      document.querySelectorAll('[data-filter-status]').forEach(button => {
        const active = button.dataset.filterStatus === status;
        button.classList.toggle('active', active);
      });
      document.querySelectorAll('[data-mobile-view="queue"], [data-filter-status]').forEach(button => {
        const active = button.dataset.filterStatus
          ? button.dataset.filterStatus === status
          : status === 'all';
        button.classList.toggle('active', active);
      });
      applyQueueFilters();
    }

    document.querySelectorAll('.concern-card').forEach(card => {
      card.addEventListener('click', () => selectConcern(card, card.dataset.inquiryId));
    });
    document.querySelectorAll('.filter-btn').forEach(button => {
      button.addEventListener('click', () => setQueueFilter(button.dataset.status));
    });
    document.getElementById('sortSelect').addEventListener('change', () => {
      sortConcernCards();
      applyQueueFilters();
    });
    document.querySelectorAll('[data-filter-status]').forEach(button => {
      button.addEventListener('click', () => {
        setQueueFilter(button.dataset.filterStatus);
        if (button.dataset.mobileView === 'list') {
          workspace.dataset.view = 'list';
          document.body.classList.remove('detail-open');
        }
      });
    });
    document.querySelectorAll('[data-mobile-view="list"]').forEach(button => {
      button.addEventListener('click', () => {
        workspace.dataset.view = 'list';
        document.body.classList.remove('detail-open');
      });
    });
    document.getElementById('mobileQueueBtn')?.addEventListener('click', () => {
      switchToQueue();
      document.querySelector('#mobileQueueBtn')?.classList.add('active');
      document.querySelector('#mobileHistoryBtn')?.classList.remove('active');
    });

    document.getElementById('mobileHistoryBtn')?.addEventListener('click', () => {
      switchToHistory();
      document.querySelector('#mobileQueueBtn')?.classList.remove('active');
      document.querySelector('#mobileHistoryBtn')?.classList.add('active');
    });
    document.getElementById('mobileProfileToggle')?.addEventListener('click', event => {
      const button = event.currentTarget;
      const menu = document.getElementById('mobileProfileMenu');
      const expanded = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!expanded));
      menu.hidden = expanded;
    });
    document.addEventListener('click', event => {
      const button = document.getElementById('mobileProfileToggle');
      const menu = document.getElementById('mobileProfileMenu');
      if (!button || !menu || button.contains(event.target) || menu.contains(event.target)) return;
      button.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
    });
    document.getElementById('searchInput').addEventListener('input', applyQueueFilters);
    document.getElementById('replyForm')?.addEventListener('submit', sendReply);
    document.getElementById('resolveButton')?.addEventListener('click', resolveConcern);
    document.getElementById('reassignButton')?.addEventListener('click', reassignConcern);
    document.getElementById('savePriorityOverride')?.addEventListener('click', savePriorityOverride);
    document.getElementById('attachButton')?.addEventListener('click', attachFile);
    document.getElementById('backToQueue')?.addEventListener('click', () => {
      workspace.dataset.view = 'list';
      document.body.classList.remove('detail-open');
    });

    // Navigation between Queue and History views
    let currentView = 'queue';
    let historyData = [];
    let filteredHistoryData = [];

    function createHistoryCard(inquiry) {
      const historyCard = document.createElement('button');
      historyCard.className = 'history-card';
      historyCard.type = 'button';
      historyCard.dataset.inquiryId = inquiry.inquiry_id;

      const resolvedDate = new Date(inquiry.resolved_at || inquiry.updated_at || inquiry.created_at);
      const formattedDate = resolvedDate.toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric'
      });

      historyCard.innerHTML = `
        <div class="history-card-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <div class="history-status-badge" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-check" />
            </svg>
            Resolved
          </div>
          <div class="history-date" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>${formattedDate}</div>
        </div>
        <div class="history-subject" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>${inquiry.subject || 'No subject'}</div>
        <div class="history-meta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <div class="history-student" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>${inquiry.student_name || 'Unknown Student'}</div>
          <div class="history-id" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>INQ-${inquiry.inquiry_id}</div>
        </div>
        <div class="history-resolved-by" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
          <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-user" />
          </svg>
          Resolved by ${inquiry.resolved_by || 'Staff'}
        </div>
      `;

      historyCard.addEventListener('click', () => selectHistoryItem(historyCard, inquiry.inquiry_id));
      return historyCard;
    }

    function selectHistoryItem(element, inquiryId) {
      document.querySelectorAll('.history-card').forEach(el => {
        el.classList.remove('selected');
      });
      element.classList.add('selected');

      currentInquiryId = Number(inquiryId);
      const inquiry = historyData.find(item => Number(item.inquiry_id) === currentInquiryId);
      if (inquiry) {
        renderSelectedInquiry(inquiry);
        workspace.dataset.view = 'detail';
        document.body.classList.add('detail-open');
      }
    }

    function populateHistoryView() {
      const historyList = document.getElementById('historyList');
      const emptyNotice = document.getElementById('historyEmptyNotice');

      // Clear existing cards
      historyList.innerHTML = '';

      if (filteredHistoryData.length === 0) {
        emptyNotice.hidden = false;
        historyList.appendChild(emptyNotice);
        return;
      }

      emptyNotice.hidden = true;
      filteredHistoryData.forEach(inquiry => {
        historyList.appendChild(createHistoryCard(inquiry));
      });
    }

    function filterHistoryData() {
      const searchTerm = document.getElementById('historySearch')?.value.toLowerCase() || '';

      filteredHistoryData = historyData.filter(inquiry => {
        const matchesSearch = !searchTerm ||
          (inquiry.subject || '').toLowerCase().includes(searchTerm) ||
          (inquiry.student_name || '').toLowerCase().includes(searchTerm) ||
          `inq-${inquiry.inquiry_id}`.includes(searchTerm);

        return matchesSearch;
      });

      sortHistoryData();
      populateHistoryView();
    }

    function sortHistoryData() {
      const sortBy = document.getElementById('historySortSelect')?.value || 'resolved-newest';

      filteredHistoryData.sort((a, b) => {
        switch (sortBy) {
          case 'resolved-newest':
            return new Date(b.resolved_at || b.updated_at || b.created_at).getTime() -
                   new Date(a.resolved_at || a.updated_at || a.created_at).getTime();

          case 'resolved-oldest':
            return new Date(a.resolved_at || a.updated_at || a.created_at).getTime() -
                   new Date(b.resolved_at || b.updated_at || b.created_at).getTime();

          case 'subject':
            return (a.subject || '').localeCompare(b.subject || '');

          case 'student':
            return (a.student_name || '').localeCompare(b.student_name || '');

          default:
            return 0;
        }
      });
    }

    function switchToQueue() {
      currentView = 'queue';
      document.getElementById('mainTitle').textContent = 'Concerns Queue';
      document.getElementById('mainSubtitle').textContent = 'Manage and respond to student inquiries';
      document.querySelector('#navConcerns')?.classList.add('active');
      document.querySelector('#navHistory')?.classList.remove('active');

      // Show queue panel, hide history panel
      document.querySelector('.queue-panel').hidden = false;
      document.getElementById('historyPanel').hidden = true;

      setQueueFilter('all');
      workspace.dataset.view = 'list';
      document.body.classList.remove('detail-open');
    }

    async function switchToHistory() {
      currentView = 'history';
      document.getElementById('mainTitle').textContent = 'Concern History';
      document.getElementById('mainSubtitle').textContent = 'View resolved and archived inquiries';
      document.querySelector('#navConcerns')?.classList.remove('active');
      document.querySelector('#navHistory')?.classList.add('active');

      // Hide queue panel, show history panel
      document.querySelector('.queue-panel').hidden = true;
      document.getElementById('historyPanel').hidden = false;

      // Load history from backend
      await loadHistoryData();

      workspace.dataset.view = 'list';
      document.body.classList.remove('detail-open');
    }

    async function loadHistoryData() {
      try {
        const response = await fetch('api/get_history.php', {
          method: 'GET',
          headers: {
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
          }
        });

        if (!response.ok) throw new Error('Failed to load history');
        const data = await response.json();

        if (data.success) {
          historyData = data.history || [];
          filterHistoryData();
        } else {
          throw new Error(data.error || 'Failed to load history');
        }
      } catch (error) {
        console.error('Error loading history:', error);
        historyData = [];
        filterHistoryData();
        // Show error in history panel
        const historyList = document.getElementById('historyList');
        const emptyNotice = document.getElementById('historyEmptyNotice');
        historyList.innerHTML = '';
        emptyNotice.innerHTML = `
          <svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> viewBox="0 0 24 24">
            <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M12 2L2 7v10c0 5.55 3.84 10 9 11 5.16-1 9-5.45 9-11V7l-10-5z"/>
            <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M9 12l2 2 4-4"/>
          </svg>
          <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Failed to load history. Please try again.</p>
        `;
        emptyNotice.hidden = false;
        historyList.appendChild(emptyNotice);
      }
    }

    document.getElementById('navConcerns')?.addEventListener('click', (e) => {
      e.preventDefault();
      switchToQueue();
    });

    document.getElementById('navHistory')?.addEventListener('click', (e) => {
      e.preventDefault();
      switchToHistory();
    });

    // History search and sort handlers
    document.getElementById('historySearch')?.addEventListener('input', filterHistoryData);
    document.getElementById('historySortSelect')?.addEventListener('change', () => {
      sortHistoryData();
      populateHistoryView();
    });

    applyQueueFilters();
    sortConcernCards();
    const notificationInquiry = Number(new URLSearchParams(window.location.search).get('inquiry_id'));
    const notificationCard = Number.isSafeInteger(notificationInquiry) && notificationInquiry > 0
      ? document.querySelector(`.concern-card[data-inquiry-id="${notificationInquiry}"]`) : null;
    if (notificationCard) selectConcern(notificationCard, notificationInquiry);
    else if (currentInquiryId) renderSelectedInquiry(inquiriesData[0]);
  </script>

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

  <script src="assets/js/student_account_actions.js?v=<?= md5_file(__DIR__ . '/assets/js/student_account_actions.js') ?>" defer></script>

</body>

</html>
