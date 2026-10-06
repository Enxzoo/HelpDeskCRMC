<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/controllers/InquiryController.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
require_once __DIR__ . '/../app/helpers/office_logo.php';
require_once __DIR__ . '/../app/models/ChatSession.php';
require_once __DIR__ . '/../app/helpers/student_profile_view.php';

if (session_status() !== PHP_SESSION_ACTIVE)
  session_start();
requireRole('student');
$studentInitialView = $studentInitialView ?? 'home';

$studentId = (int) $_SESSION['user_id'];
$dbConn = getDbConnection();
$controller = new InquiryController();
$officeResult = $dbConn->query('SELECT office_id, office_name FROM offices WHERE is_active = 1 ORDER BY office_name');
$offices = $officeResult->fetch_all(MYSQLI_ASSOC);
$inquiries = $controller->listForStudent($studentId);
$savedChatSessions = (new ChatSession())->recent($studentId);

// Fetch all staff replies for this student, newest first, grouped by office below.
$repliesQuery = "
        SELECT
            ir.response_id,
            ir.inquiry_id,
            ir.message,
            ir.created_at,
            i.subject,
            o.office_name,
            CONCAT(u.first_name, ' ', u.last_name) AS staff_name
        FROM inquiry_responses ir
        JOIN inquiries i ON ir.inquiry_id = i.inquiry_id
        JOIN offices o ON i.office_id = o.office_id
        JOIN users u ON ir.staff_id = u.user_id
        WHERE i.student_id = ?
        ORDER BY ir.created_at DESC
    ";
$stmt = $dbConn->prepare($repliesQuery);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$repliesResult = $stmt->get_result();
$recentReplies = $repliesResult->fetch_all(MYSQLI_ASSOC);

// Group replies by office
$repliesByOffice = [];
foreach ($recentReplies as $reply) {
  $office = $reply['office_name'];
  if (!isset($repliesByOffice[$office])) {
    $repliesByOffice[$office] = [];
  }
  $repliesByOffice[$office][] = $reply;
}

$initials = '';
$nameParts = preg_split('/\s+/', trim((string) $_SESSION['name']));
foreach ($nameParts as $part) {
  if ($part !== '') {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
  }
}
$initials = mb_substr($initials, 0, 2);
$firstName = $nameParts[0] ?? 'Student';

// User info for escalation prefill
$studentFullName = trim((string) ($_SESSION['name'] ?? ''));
$studentEmail = '';
$userRow = $dbConn->query("SELECT email FROM users WHERE user_id = {$studentId} LIMIT 1")->fetch_assoc();
if ($userRow) {
  $studentEmail = $userRow['email'];
}
if (!isset($studentProfileView)) {
  $profileModel = new StudentProfile();
  $studentProfileView = student_profile_view($profileModel, $studentId, $profileModel->find($studentId));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= $studentInitialView === 'profile' ? 'Profile' : 'Ask Ben' ?> - HELPDESKCRMC</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
  <?= stylesheet_bundle('dashboard_student') ?>
  <script src="assets/js/notifications.js?v=1" defer></script>
  <script src="assets/js/student_profile.js?v=<?= md5_file(__DIR__ . '/assets/js/student_profile.js') ?>"
    defer></script>
  <script
    src="assets/js/student_account_actions.js?v=<?= md5_file(__DIR__ . '/assets/js/student_account_actions.js') ?>"
    defer></script>
</head>

<body>

  <svg style="display:none" aria-hidden="true">
    <defs>
      <symbol id="i-home" viewBox="0 0 24 24">
        <path d="M3 11.5 12 4l9 7.5" />
        <path d="M5.5 10v9a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1v-9" />
      </symbol>
      <symbol id="i-file" viewBox="0 0 24 24">
        <path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
        <path d="M14 3v5h5" />
        <path d="M8.5 13h7M8.5 17h7" />
      </symbol>
      <symbol id="i-user" viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="3.5" />
        <path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5" />
      </symbol>
      <symbol id="i-logout" viewBox="0 0 24 24">
        <path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3" />
        <path d="M14 8l4 4-4 4" />
        <path d="M18 12H9" />
      </symbol>
      <symbol id="i-chat" viewBox="0 0 24 24">
        <path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
      </symbol>
      <symbol id="i-search" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="7" />
        <path d="m21 21-4.35-4.35" />
      </symbol>
      <symbol id="i-bell" viewBox="0 0 24 24">
        <path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z" />
        <path d="M10 19a2 2 0 0 0 4 0" />
      </symbol>
      <symbol id="i-folder" viewBox="0 0 24 24">
        <path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z" />
      </symbol>
      <symbol id="i-dollar" viewBox="0 0 24 24">
        <path d="M12 2v20" />
        <path d="M17 6.5c0-1.8-2-3-5-3s-5 1.4-5 3.2 2 2.8 5 3.3 5 1.5 5 3.3-2 3.2-5 3.2-5-1.2-5-3" />
      </symbol>
      <symbol id="i-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="3" />
        <path d="M3.5 19c1-3 3-4.5 5.5-4.5s4.5 1.5 5.5 4.5" />
        <circle cx="17" cy="8.5" r="2.3" />
        <path d="M15.5 14.2c2.2.4 3.5 1.8 4.4 4.3" />
      </symbol>
      <symbol id="i-info" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 11v6" />
        <path d="M12 7.5v.01" />
      </symbol>
      <symbol id="i-book" viewBox="0 0 24 24">
        <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5Z" />
        <path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5Z" />
      </symbol>
      <symbol id="i-key" viewBox="0 0 24 24">
        <circle cx="8" cy="15" r="4" />
        <path d="M11 12 20 3" />
        <path d="M16 7l3 3" />
        <path d="M13 10l2.5 2.5" />
      </symbol>
      <symbol id="i-cross" viewBox="0 0 24 24">
        <path d="M12 4v16M4 12h16" stroke-width="3" />
      </symbol>
      <symbol id="i-monitor" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="13" rx="1.5" />
        <path d="M9 21h6M12 17v4" />
      </symbol>
      <symbol id="i-briefcase" viewBox="0 0 24 24">
        <rect x="3" y="8" width="18" height="12" rx="1.5" />
        <path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
      </symbol>
      <symbol id="i-code" viewBox="0 0 24 24">
        <path d="m9 8-4 4 4 4" />
        <path d="m15 8 4 4-4 4" />
      </symbol>
      <symbol id="i-chart" viewBox="0 0 24 24">
        <path d="M4 20V10M12 20V4M20 20v-7" />
      </symbol>
      <symbol id="i-shield" viewBox="0 0 24 24">
        <path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6Z" />
      </symbol>
      <symbol id="i-gear" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3.2" />
        <path
          d="M19.4 15a1.8 1.8 0 0 0 .3 1.9l.1.1a2.1 2.1 0 1 1-3 3l-.1-.1a1.8 1.8 0 0 0-1.9-.3 1.8 1.8 0 0 0-1.1 1.6V21a2.1 2.1 0 1 1-4.2 0v-.1a1.8 1.8 0 0 0-1.1-1.6 1.8 1.8 0 0 0-1.9.3l-.1.1a2.1 2.1 0 1 1-3-3l.1-.1a1.8 1.8 0 0 0 .3-1.9 1.8 1.8 0 0 0-1.6-1.1H2.9a2.1 2.1 0 1 1 0-4.2H3a1.8 1.8 0 0 0 1.6-1.1 1.8 1.8 0 0 0-.3-1.9l-.1-.1a2.1 2.1 0 1 1 3-3l.1.1a1.8 1.8 0 0 0 1.9.3H9.3A1.8 1.8 0 0 0 10.4 3V2.9a2.1 2.1 0 1 1 4.2 0V3a1.8 1.8 0 0 0 1.1 1.6 1.8 1.8 0 0 0 1.9-.3l.1-.1a2.1 2.1 0 1 1 3 3l-.1.1a1.8 1.8 0 0 0-.3 1.9v.1a1.8 1.8 0 0 0 1.6 1.1h.1a2.1 2.1 0 1 1 0 4.2H21a1.8 1.8 0 0 0-1.6 1.1Z" />
      </symbol>
      <symbol id="i-x" viewBox="0 0 24 24">
        <path d="M18 6L6 18M6 6l12 12" />
      </symbol>
      <symbol id="i-send" viewBox="0 0 24 24">
        <path d="m22 2-7 20-4-9-9-4 20-7z" />
      </symbol>
      <symbol id="i-back" viewBox="0 0 24 24">
        <path d="M15 6l-6 6 6 6" />
      </symbol>
      <symbol id="i-check" viewBox="0 0 24 24">
        <path d="M5 13l4 4L19 7" />
      </symbol>
      <symbol id="i-arrow-up" viewBox="0 0 24 24">
        <path d="M12 19V5" />
        <path d="M6 11l6-6 6 6" />
      </symbol>
      <symbol id="i-paperclip" viewBox="0 0 24 24">
        <path d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7" />
      </symbol>
      <symbol id="i-reply" viewBox="0 0 24 24">
        <path d="M9 8 4 12l5 4" />
        <path d="M4 12h9a6 6 0 0 1 6 6v1" />
      </symbol>
      <symbol id="i-hash" viewBox="0 0 24 24">
        <path d="M9 4 7 20M17 4l-2 16M4 9h16M3.5 15h16" />
      </symbol>
      <symbol id="i-plus" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 8v8M8 12h8" />
      </symbol>
      <symbol id="i-updown" viewBox="0 0 24 24">
        <path d="M8 9l4-4 4 4" />
        <path d="M16 15l-4 4-4-4" />
      </symbol>
      <symbol id="i-collapse" viewBox="0 0 24 24">
        <path d="M14 6l-6 6 6 6" />
        <path d="M19 6l-6 6 6 6" />
      </symbol>
    </defs>
  </svg>

  <div class="app" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <aside class="sidebar">
      <div class="brand">
        <img src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
        <div class="brand-name">Helpdesk<span>CRMC</span></div>
      </div>

      <a class="nav-item<?= $studentInitialView === 'home' ? ' active' : '' ?>" id="navAskBen"
        href="dashboard_student.php" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><svg class="icon">
          <use href="#i-home" />
        </svg> Ask Ben</a>
      <a class="nav-item" id="navMyConcerns" href="dashboard_student.php#concerns" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><svg class="icon">
          <use href="#i-file" />
        </svg> My Concerns</a>
      <a class="nav-item<?= $studentInitialView === 'profile' ? ' active' : '' ?>" id="navProfile"
        href="student_profile.php" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><svg class="icon">
          <use href="#i-user" />
        </svg> Profile</a>

      <div class="nav-label">
        <span>REPLIES FROM STAFF</span>
      </div>

      <?php if (!empty($repliesByOffice)): ?>
        <div class="staff-replies-list" aria-label="All replies from staff">
          <?php foreach ($repliesByOffice as $officeName => $replies): ?>
            <?php
            $officeInitial = mb_strtoupper(mb_substr($officeName, 0, 1));
            $officeLogo = officeLogoPath($officeName);
            $colors = [
              'Registrar' => ['#b8231c', '#8f1912'],
              'SASO' => ['#ecc94b', '#c98a06'],
              'Finance' => ['#1e7a8c', '#155e6d'],
            ];
            $gradient = $colors[$officeName] ?? ['#847c6e', '#5c5648'];
            ?>
            <div class="group">
              <div class="group-head">
                <?php if ($officeLogo): ?>
                  <div class="badge has-office-logo"><img src="<?= htmlspecialchars($officeLogo, ENT_QUOTES, 'UTF-8') ?>"
                      alt="" loading="lazy"></div>
                <?php else: ?>
                  <div class="badge" style="background:linear-gradient(135deg,<?= $gradient[0] ?>,<?= $gradient[1] ?>);">
                    <?= htmlspecialchars($officeInitial) ?></div>
                <?php endif; ?>
                <span><?= htmlspecialchars($officeName) ?></span>
              </div>
              <?php foreach ($replies as $reply): ?>
                <?php
                $timeAgo = '';
                $diff = time() - strtotime($reply['created_at']);
                if ($diff < 3600)
                  $timeAgo = floor($diff / 60) . 'm';
                elseif ($diff < 86400)
                  $timeAgo = floor($diff / 3600) . 'h';
                else
                  $timeAgo = floor($diff / 86400) . 'd';
                ?>
                <a class="channel" href="#" data-inquiry-id="<?= (int) $reply['inquiry_id'] ?>">
                  <svg class="icon" style="width:15px;height:15px;">
                    <use href="#i-chat" />
                  </svg>
                  <span class="snippet"><?= htmlspecialchars(mb_substr($reply['subject'], 0, 25)) ?></span>
                  <span class="time"><?= htmlspecialchars($timeAgo) ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-replies">
          <svg class="icon" style="width:20px;height:20px;color:#c4bba6;margin:0 auto 6px;">
            <use href="#i-chat" />
          </svg>
          <p>No replies yet</p>
          <span>Staff will respond to your concerns within 2-3 working days</span>
        </div>
      <?php endif; ?>

      <div class="sidebar-spacer"></div>

      <div class="urgent-card">
        <h4>Need urgent help?</h4>
        <p>Walk-in concerns are still welcome at the Student Affairs office.</p>
        <button class="urgent-btn">Visit SASO</button>
      </div>

      <a href="logout.php" class="nav-item student-logout-link" style="margin-top:8px;"><svg class="icon">
          <use href="#i-logout" />
        </svg> Logout</a>

      <div class="sidebar-foot">
        <div class="sidebar-profile">
          <div class="avatar"><?= htmlspecialchars($initials) ?></div>
          <div>
            <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
            <div class="user-sub">
              <?= htmlspecialchars($studentProfileView['profile']['program_code'] ?: 'Student') ?><?= $studentProfileView['profile']['year_level'] ? ' &middot; Year ' . (int) $studentProfileView['profile']['year_level'] : '' ?>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <main class="main" id="mainView" data-initial-view="<?= $studentInitialView ?>">
      <?php require __DIR__ . '/assets/components/student-profile-view.php'; ?>
      <div class="student-notification-bar">
        <button type="button" class="notification-icon-button chat-history-toggle" id="chatHistoryToggle"
          aria-label="Chat history" title="Chat history" aria-controls="chatHistoryPanel" aria-expanded="false"><img
            src="assets/icons/history.svg" alt="" width="20" height="20"></button>
        <?php require __DIR__ . '/assets/components/notification-center.php'; ?>
      </div>
      <div class="blob blob-1"></div>

      <div id="heroView" <?= $studentInitialView === 'profile' ? ' style="display:none;"' : '' ?>>
        <div class="hero">
          <div class="hero-avatar"><img src="assets/images/ben-model.png" alt="Ben"></div>
          <h1>Good morning, <?= htmlspecialchars($firstName) ?>. I'm <b>Ben</b></h1>
          <p>I'm here to help you with your concern.</p>
          <p class="sub">Choose a category below to get started</p>
          <div class="search-pill" id="heroSearchPill">
            <svg class="icon">
              <use href="#i-search" />
            </svg>
            <input type="text" aria-label="Describe your concern to Ben"
              placeholder="Type your concern, e.g. 'I lost my student ID'" id="heroSearchInput" />
          </div>
        </div>

        <div class="content">
          <div class="section-title">GENERAL</div>
          <div class="general-card" data-category="General" role="button" tabindex="0"
            <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                loading="lazy"></div>
            <div>
              <h3>General Inquiry</h3>
              <p>Ask a concern directly to Ben — no category needed.</p>
            </div>
          </div>

          <div class="section-title">OFFICES</div>
          <div class="grid">
            <div class="tile" data-category="Registrar" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>Registrar</h4>
              <p>Enrollment, records, IDs</p>
            </div>
            <div class="tile alt" data-category="Finance" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/finance-removebg-preview.png"
                  alt="" loading="lazy"></div>
              <h4>Finance</h4>
              <p>Fees, payments, receipts</p>
            </div>
            <div class="tile" data-category="SASO" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>SASO</h4>
              <p>Student affairs & orgs</p>
            </div>
            <div class="tile alt" data-category="Guidance" role="button" tabindex="0"
              <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/guidance-removebg-preview.png"
                  alt="" loading="lazy"></div>
              <h4>Guidance</h4>
              <p>Counseling & support</p>
            </div>
            <div class="tile" data-category="Library" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>Library</h4>
              <p>Books, fines, access</p>
            </div>
            <div class="tile alt" data-category="Property Custodian" role="button" tabindex="0"
              <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>Property Custodian</h4>
              <p>Facilities & equipment</p>
            </div>
            <div class="tile" data-category="Clinic" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>Clinic</h4>
              <p>Medical certificates</p>
            </div>
            <div class="tile alt" data-category="ITCD" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/ITCD.png" alt="" loading="lazy">
              </div>
              <h4>ITCD</h4>
              <p>Portal & IT support</p>
            </div>
            <div class="tile" data-category="Human Resources" role="button" tabindex="0"
              <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CRMC LOGO.png" alt=""
                  loading="lazy"></div>
              <h4>Human Resources</h4>
              <p>Employment inquiries</p>
            </div>
          </div>

          <div class="section-title">DEPARTMENTS</div>
          <div class="grid">
            <div class="tile" data-category="CCS" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CCS.png" alt="" loading="lazy">
              </div>
              <h4>CCS</h4>
              <p>Computer science dept</p>
            </div>
            <div class="tile alt" data-category="CBE" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CBE.png" alt="" loading="lazy">
              </div>
              <h4>CBE</h4>
              <p>Business education dept</p>
            </div>
            <div class="tile" data-category="CTE" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CTE.png" alt="" loading="lazy">
              </div>
              <h4>CTE</h4>
              <p>Teacher education dept</p>
            </div>
            <div class="tile alt" data-category="CCJE" role="button" tabindex="0" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
              <div class="ic"><img class="office-logo" src="assets/images/offices_logo/CCJE.png" alt="" loading="lazy">
              </div>
              <h4>CCJE</h4>
              <p>Criminal justice dept</p>
            </div>
          </div>
        </div>
      </div>

      <div id="concernsView" class="concerns-view" style="display:none;">
        <div class="concerns-header">
          <h1>My Concerns</h1>
          <p>View the current status of your concerns. Select one to open replies from staff.</p>
        </div>
        <div class="concerns-container" id="concernsListContainer"></div>
      </div>

      <!-- Staff Reply Thread View (Ben-style chat) -->
      <div id="threadView" style="display:none;">
        <div class="thread-view-header">
          <button class="back-btn" type="button" aria-label="Back to concerns" onclick="goBackToConcerns()"><svg
              class="icon" aria-hidden="true">
              <use href="#i-back" />
            </svg></button>
          <div class="thread-view-info">
            <h2 id="threadTitle">Concern Title</h2>
            <div class="thread-view-meta">
              <img class="thread-office-logo" id="threadOfficeLogo" alt="" hidden>
              <span id="threadOffice">Office Name</span>
              <span>•</span>
              <span class="status-badge" id="threadStatus">On Hold</span>
            </div>
          </div>
        </div>

        <div class="thread-messages-container">
          <div class="thread-messages-inner" id="threadMessages"></div>
        </div>

        <div class="thread-composer-area" id="threadComposer">
          <!-- Reply section (for On Hold) -->
          <div class="thread-reply-section" id="threadReplySection">
            <div class="thread-reply-label">Staff is waiting for your response</div>
            <textarea class="thread-reply-input" id="threadReplyInput" placeholder="Type your reply here..."></textarea>
            <button class="thread-reply-btn" onclick="sendThreadReply()">Send Reply</button>
          </div>

          <!-- Feedback section (for every status except On Hold) -->
          <div class="thread-feedback-section" id="threadFeedbackSection" style="display:none;">
            <div class="thread-feedback-heading">
              <div>
                <div class="thread-feedback-label" id="threadFeedbackLabel">How is your concern going so far?</div>
                <p class="thread-feedback-description" id="threadFeedbackDescription">Your feedback helps us improve
                  student support.</p>
              </div>
              <span class="thread-feedback-optional">Optional</span>
            </div>
            <div class="thread-feedback-emojis" role="group" aria-label="Choose a feedback rating">
              <button type="button" class="thread-emoji-btn" data-rating="1" aria-label="Very dissatisfied"
                aria-pressed="false" title="Very Dissatisfied" onclick="selectRating(1)">😞</button>
              <button type="button" class="thread-emoji-btn" data-rating="2" aria-label="Dissatisfied"
                aria-pressed="false" title="Dissatisfied" onclick="selectRating(2)">😐</button>
              <button type="button" class="thread-emoji-btn" data-rating="3" aria-label="Neutral" aria-pressed="false"
                title="Neutral" onclick="selectRating(3)">😊</button>
              <button type="button" class="thread-emoji-btn" data-rating="4" aria-label="Satisfied" aria-pressed="false"
                title="Satisfied" onclick="selectRating(4)">😄</button>
              <button type="button" class="thread-emoji-btn" data-rating="5" aria-label="Very satisfied"
                aria-pressed="false" title="Very Satisfied" onclick="selectRating(5)">🤩</button>
            </div>
            <div class="thread-feedback-actions">
              <textarea class="thread-reply-input" id="threadFeedbackInput" placeholder="Add a comment (optional)"
                aria-label="Optional feedback comment" rows="1"></textarea>
              <button type="button" class="thread-reply-btn" onclick="submitThreadFeedback()">Submit Feedback</button>
            </div>
          </div>
          <p id="threadActionMessage" class="thread-action-message" role="status" aria-live="polite" hidden></p>
        </div>
      </div>

      <div id="chatView">
        <div class="chat-header">
          <button class="back-btn" id="backToDashboard"><svg class="icon">
              <use href="#i-back" />
            </svg></button>
          <div class="ic" id="chatCategoryIcon"><svg class="icon">
              <use href="#i-chat" />
            </svg></div>
          <div>
            <h2 id="chatCategoryName">General</h2>
            <p id="chatCategoryDesc">Ask your concern</p>
          </div>
        </div>

        <div class="chat-thread">
          <div class="thread-inner" id="chatThreadInner">
            <div class="day-divider" id="chatDayDivider">Today</div>
          </div>
        </div>

        <div class="composer-wrap">
          <div class="composer">
            <button class="attach-btn" id="chatAttachBtn"><svg class="icon">
                <use href="#i-paperclip" />
              </svg></button>
            <input type="text" placeholder="Message Ben…" id="chatInput" />
            <button class="send-btn" id="chatSendBtn"><svg class="icon">
                <use href="#i-arrow-up" />
              </svg></button>
          </div>
          <div class="composer-hint">Ben can make mistakes. For urgent concerns, visit the office directly.</div>
        </div>
      </div>
    </main>

    <aside class="panel" id="chatHistoryPanel" aria-labelledby="chatHistoryTitle">
      <div class="panel-head">
        <h3 id="chatHistoryTitle">Chat history</h3>
        <button type="button" class="notification-icon-button chat-history-close" id="chatHistoryClose"
          aria-label="Close chat history" title="Close chat history"><img src="assets/icons/x.svg" alt="" width="18"
            height="18"></button>
      </div>
      <div class="search-mini">
        <svg class="icon">
          <use href="#i-search" />
        </svg>
        <input type="search" id="chatHistorySearch" placeholder="Search conversations" aria-label="Search chat history">
      </div>
      <div id="chatHistoryList"></div>
    </aside>
  </div>

  <dialog class="action-confirm-dialog" id="actionConfirmDialog" aria-labelledby="actionConfirmTitle"
    aria-describedby="actionConfirmMessage" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
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

  <script src="assets/js/ben_chat.js?v=<?= md5_file(__DIR__ . '/assets/js/ben_chat.js') ?>"></script>
  <script>
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const STUDENT_INITIALS = <?= json_encode($initials) ?>;
    const defaultCategoryLogoPath = <?= json_encode(officeLogoPath(null), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
    const categoryLogoPaths = <?= json_encode(array_filter([
      'Finance' => officeLogoPath('Finance'),
      'Cashier' => officeLogoPath('Cashier'),
      'Guidance' => officeLogoPath('Guidance'),
      'Guidance Office' => officeLogoPath('Guidance Office'),
      'CCS' => officeLogoPath('CCS'),
      'College of Computer Studies' => officeLogoPath('College of Computer Studies'),
      'CBE' => officeLogoPath('CBE'),
      'College of Business Education' => officeLogoPath('College of Business Education'),
      'CTE' => officeLogoPath('CTE'),
      'College of Teacher Education' => officeLogoPath('College of Teacher Education'),
      'CCJE' => officeLogoPath('CCJE'),
      'CJE' => officeLogoPath('CJE'),
      'College of Justice Education' => officeLogoPath('College of Justice Education'),
      'Psychology' => officeLogoPath('Psychology'),
      'Psychology Department' => officeLogoPath('Psychology Department'),
      'ITCD' => officeLogoPath('ITCD')
    ]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;


    function escapeHtml(str) {
      if (typeof str !== 'string') return '';
      const d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }

    function renderMarkdown(text) {
      if (!text) return '';
      let html = escapeHtml(text);
      html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
      html = html.replace(/(?:^|\n)(\d+)\.\s+(.*?)(?=\n|$)/g, '<div style="margin-left:14px;text-indent:-14px;"><strong>$1.</strong> $2</div>');
      html = html.replace(/(?:^|\n)[-]\s+(.*?)(?=\n|$)/g, '<div style="margin-left:14px;text-indent:-10px;">• $1</div>');
      html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
      html = html.replace(/\n\n/g, '<div style="height:8px;"></div>');
      html = html.replace(/\n/g, '<br>');
      return html;
    }

    let concerns = <?= json_encode(array_map(function ($inq) {
      return [
        'id' => $inq['inquiry_id'],
        'subject' => $inq['subject'] ?? mb_substr($inq['description'], 0, 60),
        'office' => $inq['office_name'] ?? 'General',
        'status' => strtolower(str_replace(' ', '', $inq['status'])),
        'date' => date('M j, Y', strtotime($inq['created_at'])),
        'reply' => null
      ];
    }, $inquiries)) ?>;

    let currentCategory = null;
    let currentChatSessionKey = null;
    let chatSessions = <?= json_encode($savedChatSessions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    let conversationHistory = [];
    let chatLoadRequestId = 0;
    let chatSending = false;
    let escalationLoadingKey = null;
    let chatSaveQueue = Promise.resolve();
    let activeThreadInquiryId = null;
    let threadLoadRequestId = 0;

    const categoryMeta = {
      'General': { icon: 'i-chat', desc: 'Ask your concern' },
      'Registrar': { icon: 'i-folder', desc: 'Enrollment, records, IDs' },
      'Finance': { icon: 'i-dollar', desc: 'Fees, payments, receipts' },
      'SASO': { icon: 'i-users', desc: 'Student affairs & orgs' },
      'Guidance': { icon: 'i-info', desc: 'Counseling & support' },
      'Library': { icon: 'i-book', desc: 'Books, fines, access' },
      'Property Custodian': { icon: 'i-key', desc: 'Facilities & equipment' },
      'Clinic': { icon: 'i-cross', desc: 'Medical certificates' },
      'ITCD': { icon: 'i-monitor', desc: 'Portal & IT support' },
      'Human Resources': { icon: 'i-briefcase', desc: 'Employment inquiries' },
      'CCS': { icon: 'i-code', desc: 'Computer science dept' },
      'CBE': { icon: 'i-chart', desc: 'Business education dept' },
      'CTE': { icon: 'i-book', desc: 'Teacher education dept' },
      'CCJE': { icon: 'i-shield', desc: 'Criminal justice dept' },
      'Psychology': { icon: 'i-info', desc: 'Psychology department' },
      'Psychology Department': { icon: 'i-info', desc: 'Psychology department' }
    };

    function categoryIconMarkup(category) {
      const logoPath = categoryLogoPaths[category] || defaultCategoryLogoPath;
      return `<img class="category-logo" src="${escapeHtml(logoPath)}" alt="">`;
    }

    // Nav switching
    document.getElementById('navAskBen').addEventListener('click', e => {
      e.preventDefault();
      showHeroView();
    });

    document.getElementById('navMyConcerns').addEventListener('click', e => {
      e.preventDefault();
      showConcernsView();
    });

    document.getElementById('navProfile').addEventListener('click', e => {
      e.preventDefault();
      showProfileView();
    });

    document.getElementById('profileBack')?.addEventListener('click', e => {
      e.preventDefault();
      showHeroView();
    });

    function showProfileView() {
      hideThreadView();
      currentCategory = null;
      currentChatSessionKey = null;
      chatLoadRequestId++;
      document.getElementById('heroView').style.display = 'none';
      document.getElementById('concernsView').style.display = 'none';
      document.getElementById('chatView').classList.remove('active');
      document.getElementById('profileView').style.display = 'block';
      document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
      document.getElementById('navProfile').classList.add('active');
    }

    function hideThreadView() {
      activeThreadInquiryId = null;
      threadLoadRequestId++;
      document.getElementById('threadView').style.display = 'none';
    }

    function showHeroView() {
      hideThreadView();
      currentCategory = null;
      currentChatSessionKey = null;
      chatLoadRequestId++;
      document.getElementById('heroView').style.display = 'block';
      document.getElementById('profileView').style.display = 'none';
      document.getElementById('concernsView').style.display = 'none';
      const chatView = document.getElementById('chatView');
      chatView.classList.remove('active');
      document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
      document.getElementById('navAskBen').classList.add('active');
    }

    function showConcernsView() {
      hideThreadView();
      currentCategory = null;
      currentChatSessionKey = null;
      chatLoadRequestId++;
      document.getElementById('heroView').style.display = 'none';
      document.getElementById('concernsView').style.display = 'block';
      document.getElementById('profileView').style.display = 'none';
      document.getElementById('chatView').classList.remove('active');
      document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
      document.getElementById('navMyConcerns').classList.add('active');
      renderConcernsList();
    }

    function newChatSessionKey() {
      return Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    async function showChatView(category, sessionKey = null) {
      hideThreadView();

      const heroView = document.getElementById('heroView');
      const concernsView = document.getElementById('concernsView');
      const chatView = document.getElementById('chatView');


      heroView.style.display = 'none';
      concernsView.style.display = 'none';
      document.getElementById('profileView').style.display = 'none';
      chatView.classList.add('active');

      currentCategory = category;
      currentChatSessionKey = sessionKey || newChatSessionKey();
      document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
      document.getElementById('navAskBen').classList.add('active');
      const requestId = ++chatLoadRequestId;
      chatSending = false;
      conversationHistory = [];
      document.getElementById('chatInput').value = '';
      document.getElementById('chatInput').disabled = true;
      document.getElementById('chatSendBtn').disabled = true;

      const meta = categoryMeta[category] || { icon: 'i-chat', desc: 'Ask your concern' };
      document.getElementById('chatCategoryName').textContent = category;
      document.getElementById('chatCategoryDesc').textContent = meta.desc;
      const categoryIcon = document.getElementById('chatCategoryIcon');
      categoryIcon.innerHTML = categoryIconMarkup(category);
      categoryIcon.classList.add('has-office-logo');

      const threadInner = document.getElementById('chatThreadInner');
      threadInner.innerHTML = '';

      let loaded = true;
      if (sessionKey) loaded = await loadChatHistory(category, threadInner, requestId, sessionKey);
      else showChatGreeting(category, threadInner);
      renderChatHistory();

      if (loaded && requestId === chatLoadRequestId && currentCategory === category) {
        document.getElementById('chatInput').disabled = false;
        document.getElementById('chatSendBtn').disabled = false;
        document.getElementById('chatInput').focus();
      }
    }

    function addBenMessage(html, showTyping = false, expression = 'happy') {
      const threadInner = document.getElementById('chatThreadInner');
      const msg = document.createElement('div');
      msg.className = 'msg ben';

      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

      msg.innerHTML = `
    <div class="m-avatar">
      <img src="assets/images/ben-model.png" alt="Ben">
    </div>
    <div class="bubble-wrap">
      <span class="name">Ben</span>
      <div class="bubble">${html}</div>
      <span class="time">${timeStr}</span>
    </div>`;

      threadInner.appendChild(msg);
      scrollChatToBottom();

      if (showTyping) {
        addTypingIndicator();
      }
      return msg;
    }

    function showBenActions(message, answer = '', topic = '', staffOnly = false) {
      const requestId = chatLoadRequestId;
      const sessionKey = currentChatSessionKey;
      const choices = BenChatUI.suggestions(answer, currentCategory, topic);
      BenChatUI.render(message, staffOnly ? choices.filter(choice => choice.kind === 'staff') : choices, choice => {
        if (chatSending || requestId !== chatLoadRequestId || sessionKey !== currentChatSessionKey) return;
        if (choice.kind === 'staff') {
          BenChatUI.clear();
          addEscalationFormMessage();
        } else {
          sendChatMessage(choice.message);
        }
      });
      scrollChatToBottom();
    }

    async function addEscalationFormMessage(prefillOffice = '', prefillSubject = '') {
      const requestId = chatLoadRequestId;
      const sessionKey = currentChatSessionKey;
      const identity = `${sessionKey}:${requestId}`;
      const existing = document.querySelector('.escalation-msg:not([data-submitted])');
      if (existing) {
        existing.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return existing;
      }
      if (escalationLoadingKey === identity) return;
      escalationLoadingKey = identity;
      try {
        const response = await fetch('assets/components/escalation-form.html');
        if (!response.ok) throw new Error('Form unavailable');
        const formHtml = await response.text();
        if (requestId !== chatLoadRequestId || sessionKey !== currentChatSessionKey) return;

        const threadInner = document.getElementById('chatThreadInner');
        const msg = document.createElement('div');
        msg.className = 'msg ben escalation-msg';

        const now = new Date();
        const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        msg.innerHTML = `
      <div class="m-avatar">
        <img src="assets/images/ben-model.png" alt="Ben">
      </div>
      <div class="bubble-wrap">
        <span class="name">Ben</span>
        <div class="bubble">
          ${formHtml}
        </div>
        <span class="time">${timeStr}</span>
      </div>`;

        threadInner.appendChild(msg);

        if (prefillOffice) {
          const officeSelect = msg.querySelector('#esc-office');
          if (officeSelect) officeSelect.value = prefillOffice;
        }
        if (prefillSubject) {
          const subjectInput = msg.querySelector('#esc-subject');
          if (subjectInput) subjectInput.value = prefillSubject;
        }

        // Auto-prefill student info if available
        const fullNameInput = msg.querySelector('#esc-fullname');
        const emailInput = msg.querySelector('#esc-email');
        if (fullNameInput) fullNameInput.value = <?= json_encode($studentFullName) ?>;
        if (emailInput) emailInput.value = <?= json_encode($studentEmail) ?>;

        // Set up form submission - ensure it fires
        const form = msg.querySelector('[data-escalation-form]');
        if (form) {
          const suffix = `-${sessionKey}-${conversationHistory.length}`;
          msg.querySelectorAll('[id]').forEach(field => {
            const label = msg.querySelector(`label[for="${field.id}"]`);
            field.id += suffix;
            if (label) label.htmlFor = field.id;
          });
          msg.querySelector('.esc-file-btn').removeAttribute('onclick');
          msg.querySelector('.esc-file-btn').addEventListener('click', () => form.elements.attachment.click());
          form.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopPropagation();
            handleEscalationSubmit(e, msg);
          });
        } else {
          console.error('Escalation form not found with [data-escalation-form] selector');
        }

        const heading = msg.querySelector('.esc-header h3');
        heading.tabIndex = -1;
        heading.focus({ preventScroll: true });
        msg.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return msg;
      } catch (error) {
        if (requestId !== chatLoadRequestId || sessionKey !== currentChatSessionKey) return;
        const message = addBenMessage(escapeHtml("The staff form couldn't be loaded. Please try again."));
        showBenActions(message, '', '', true);
      } finally {
        if (escalationLoadingKey === identity) escalationLoadingKey = null;
      }
    }

    async function handleEscalationSubmit(e, formContainer) {
      e.preventDefault();
      e.stopPropagation();

      const form = e.target;
      const submitBtn = form.querySelector('.esc-btn');
      if (!submitBtn || submitBtn.disabled) return;
      const errorAlert = form.querySelector('[data-error-msg]');
      const duplicateNotice = form.querySelector('[data-duplicate-notice]');
      let successDiv = form.querySelector('[data-success-msg]');

      // If success div doesn't exist, create it (for older cached forms)
      if (!successDiv) {
        const card = form.closest('.escalation-card');
        if (card) {
          successDiv = document.createElement('div');
          successDiv.className = 'esc-success';
          successDiv.style.display = 'none';
          successDiv.setAttribute('data-success-msg', '');
          successDiv.innerHTML = `
        <div class="esc-success-icon">✓</div>
        <h3>Concern Forwarded Successfully!</h3>
        <p>Your concern was submitted to the selected office. You can follow staff replies in My Concerns.</p>
        <p class="esc-success-urgency" data-urgency-result></p>
        <p class="esc-success-note">You can track the status of your concern in the <strong>My Concerns</strong> section.</p>
      `;
          card.appendChild(successDiv);
        }
      }

      if (!errorAlert) {
        console.error('Missing errorAlert element');
        alert('Error: Form not properly loaded. Please refresh and try again.');
        return;
      }

      let urgencyResult = successDiv && successDiv.querySelector('[data-urgency-result]');
      if (successDiv && !urgencyResult) {
        urgencyResult = document.createElement('p');
        urgencyResult.className = 'esc-success-urgency';
        urgencyResult.dataset.urgencyResult = '';
        successDiv.appendChild(urgencyResult);
      }

      // Clear previous messages
      errorAlert.style.display = 'none';
      if (duplicateNotice) {
        duplicateNotice.hidden = true;
        duplicateNotice.replaceChildren();
      }
      if (successDiv) successDiv.style.display = 'none';

      // Get form data
      const formData = {
        fullName: form.fullName.value.trim(),
        email: form.email.value.trim(),
        phone: form.phone.value.trim(),
        office: form.office.value,
        subject: form.subject.value.trim(),
        concern: form.concern.value.trim()
      };

      // Basic validation
      if (!formData.fullName || !formData.email || !formData.phone || !formData.office || !formData.subject || !formData.concern) {
        errorAlert.textContent = 'Please fill out all required fields.';
        errorAlert.style.display = 'block';
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
      }

      const confirmedDuplicateOf = Number(form.dataset.confirmedDuplicateOf) || null;
      const confirmed = confirmedDuplicateOf !== null || await confirmImportantAction({
        title: 'Submit this concern?',
        message: 'We’ll check whether a similar concern is already open, analyze its urgency, and send it to the selected office. You can track its status in My Concerns.',
        confirmLabel: 'Continue to checks'
      });
      if (!confirmed) return;

      // Disable submit button
      submitBtn.disabled = true;
      submitBtn.setAttribute('aria-busy', 'true');
      const submitLabel = submitBtn.querySelector('[data-submit-label]');
      const submitSpinner = submitBtn.querySelector('[data-submit-spinner]');
      const analysisStatus = form.querySelector('[data-analysis-status]');
      const originalText = submitLabel ? submitLabel.textContent : submitBtn.textContent;
      if (submitLabel) {
        submitLabel.textContent = 'Checking concern...';
      } else {
        submitBtn.textContent = 'Checking concern...';
      }
      if (submitSpinner) submitSpinner.hidden = false;
      if (analysisStatus) {
        analysisStatus.textContent = 'Checking for a similar concern and reviewing urgency before submission.';
        analysisStatus.hidden = false;
      }

      try {
        const response = await fetch('api/submit_inquiry.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({
            ...formData,
            confirmed_duplicate_of: confirmedDuplicateOf
          })
        });

        let result;
        try {
          result = await response.json();
        } catch {
          throw new Error('The server returned an unexpected response. Check My Concerns before trying again.');
        }
        if (!result || typeof result !== 'object') {
          throw new Error('The server returned an unexpected response. Check My Concerns before trying again.');
        }

        if (result.duplicate_warning || result.duplicate_limit_reached) {
          showEscalationDuplicateNotice(form, result);
          return;
        }

        if (result.success) {
          formContainer.dataset.submitted = 'true';
          if (urgencyResult) {
            urgencyResult.textContent = result.urgency_review_required
              ? `Your concern was submitted with a provisional ${result.urgency_priority} priority. Staff will review it.`
              : `AI-assigned urgency: ${result.urgency_priority}`;
          }
          // Hide form, show success
          form.style.display = 'none';
          if (successDiv) {
            successDiv.style.display = 'block';
            successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }

          // Add to concerns list
          concerns.unshift({
            id: result.inquiry_id || 'escalated_' + Date.now(),
            subject: formData.subject,
            office: getOfficeName(formData.office),
            status: 'pending',
            date: 'Just now',
            isEscalated: true
          });
          renderChatHistory();

        } else {
          errorAlert.textContent = result.error || 'Failed to submit concern. Please try again.';
          errorAlert.style.display = 'block';
          errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } catch (error) {
        console.error('Escalation submission error:', error);
        errorAlert.textContent = error instanceof TypeError
          ? 'Connection error. Please try again.'
          : (error.message || 'Failed to submit concern. Please try again.');
        errorAlert.style.display = 'block';
        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      } finally {
        submitBtn.disabled = false;
        submitBtn.removeAttribute('aria-busy');
        if (submitLabel) {
          submitLabel.textContent = originalText;
        } else {
          submitBtn.textContent = originalText;
        }
        if (submitSpinner) submitSpinner.hidden = true;
        if (analysisStatus) {
          analysisStatus.textContent = '';
          analysisStatus.hidden = true;
        }
        delete form.dataset.confirmedDuplicateOf;
      }
    }

    function showEscalationDuplicateNotice(form, result) {
      let notice = form.querySelector('[data-duplicate-notice]');
      const matched = result.matched_concern;
      if (
        !matched
        || !Number.isInteger(Number(matched.inquiry_id))
        || Number(matched.inquiry_id) <= 0
      ) {
        const errorAlert = form.querySelector('[data-error-msg]');
        if (errorAlert) {
          errorAlert.textContent = result.error || 'A similar concern may already exist. Please check My Concerns before submitting again.';
          errorAlert.style.display = 'block';
        }
        return;
      }

      if (!notice) {
        notice = document.createElement('div');
        notice.className = 'esc-duplicate-notice';
        notice.dataset.duplicateNotice = '';
        notice.tabIndex = -1;
        form.querySelector('[data-error-msg]')?.after(notice);
      }

      const limitReached = Boolean(result.duplicate_limit_reached);
      const rootId = Number(result.duplicate_root_id || matched.inquiry_id);
      notice.replaceChildren();
      notice.setAttribute('role', limitReached ? 'alert' : 'status');

      const heading = document.createElement('strong');
      heading.textContent = limitReached
        ? 'This similar concern has reached the repeat-submission limit'
        : 'A similar concern may already be open';

      const description = document.createElement('p');
      description.style.margin = '0';
      description.textContent = limitReached
        ? 'A similar concern has already been submitted three times in the last 30 days. Please check its status or reply to staff there instead of opening another copy.'
        : `We found a possible match: "${String(matched.subject || 'Existing concern')}" (${String(matched.status || 'Current status unavailable')}). Review it first, or submit yours anyway if it is a separate issue.`;

      const actions = document.createElement('div');
      actions.className = 'esc-duplicate-actions';

      const openButton = document.createElement('button');
      openButton.type = 'button';
      openButton.textContent = 'Open existing concern';
      openButton.addEventListener('click', () => openThreadView(Number(matched.inquiry_id)));
      actions.appendChild(openButton);

      if (!limitReached) {
        const submitButton = document.createElement('button');
        submitButton.type = 'button';
        submitButton.className = 'esc-duplicate-submit';
        submitButton.textContent = 'Submit anyway';
        submitButton.addEventListener('click', () => {
          form.dataset.confirmedDuplicateOf = String(rootId);
          form.requestSubmit();
        });
        actions.appendChild(submitButton);
      }

      notice.append(heading, description, actions);
      notice.hidden = false;
      notice.focus();
      notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function getOfficeName(officeCode) {
      const officeMap = {
        'registrar': 'Registrar',
        'cashier': 'Cashier',
        'guidance': 'Guidance',
        'saso': 'SASO',
        'cte': 'CTE',
        'cbe': 'CBE',
        'ccs': 'CCS',
        'cje': 'CJE',
        'psychology': 'Psychology',
        'main': 'Main Office'
      };
      return officeMap[officeCode] || 'General';
    }

    function addUserMessage(text, record = true) {
      const threadInner = document.getElementById('chatThreadInner');
      const msg = document.createElement('div');
      msg.className = 'msg user';

      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

      msg.innerHTML = `
    <div class="m-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="bubble-wrap">
      <div class="bubble">${escapeHtml(text)}</div>
      <span class="time">${timeStr}</span>
    </div>`;

      threadInner.appendChild(msg);
      scrollChatToBottom();
      if (!record) return;
      conversationHistory.push({ role: 'user', message: text });

      // Auto-save after user message
      if (currentCategory) {
        saveChatHistory(currentCategory, conversationHistory, text);
      }
    }

    function addTypingIndicator() {
      const threadInner = document.getElementById('chatThreadInner');
      const msg = document.createElement('div');
      msg.className = 'msg ben';
      msg.id = 'typingIndicator';

      msg.innerHTML = `
    <div class="m-avatar">
      <img src="assets/images/ben-model.png" alt="Ben">
    </div>
    <div class="bubble-wrap">
      <div class="bubble typing"><span></span><span></span><span></span></div>
    </div>`;

      threadInner.appendChild(msg);
      scrollChatToBottom();
    }

    function removeTypingIndicator() {
      const typing = document.getElementById('typingIndicator');
      if (typing) typing.remove();
    }

    function scrollChatToBottom() {
      const chatThread = document.querySelector('.chat-thread');
      if (chatThread) {
        chatThread.scrollTop = chatThread.scrollHeight;
      }
    }

    // Save chat history to database
    async function saveChatHistory(category, history, lastMessage = null, sessionKey = currentChatSessionKey) {
      const body = JSON.stringify({ category, conversationHistory: history, lastMessage, session_key: sessionKey });
      chatSaveQueue = chatSaveQueue.catch(() => { }).then(async () => {
        const response = await fetch('api/save_chat_session.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.error || 'Failed to save chat history.');
        if (result.session) rememberChatSession(result.session);
      });
      return chatSaveQueue.catch(err => {
        console.error('Failed to save chat history:', err);
      });
    }

    // Load chat history from database
    function showChatGreeting(category, threadInner) {
      const dayDiv = document.createElement('div');
      dayDiv.className = 'day-divider';
      dayDiv.textContent = new Date().toLocaleDateString();
      threadInner.appendChild(dayDiv);
      const studentFirstName = <?= json_encode($firstName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
      const message = addBenMessage(`Hi ${escapeHtml(studentFirstName)}! I see you'd like help with <b>${escapeHtml(category)}</b> concerns. What can I help you with today?`);
      showBenActions(message);
    }

    async function loadChatHistory(category, threadInner, requestId = chatLoadRequestId, sessionKey = currentChatSessionKey) {
      try {
        await chatSaveQueue.catch(() => { });
        if (requestId !== chatLoadRequestId || currentCategory !== category || currentChatSessionKey !== sessionKey) return false;
        const res = await fetch(`api/load_chat_session.php?session_key=${encodeURIComponent(sessionKey)}`, {
          cache: 'no-store',
          headers: {
            'X-CSRF-Token': CSRF_TOKEN
          }
        });

        const data = await res.json();
        if (requestId !== chatLoadRequestId || currentCategory !== category || currentChatSessionKey !== sessionKey) return false;
        if (!res.ok || !data.success) throw new Error(data.error || 'Failed to load chat history.');
        if (data.session_key !== sessionKey || data.category !== category) throw new Error('Conversation does not match the selected history entry.');
        conversationHistory = Array.isArray(data.conversationHistory)
          ? data.conversationHistory.filter(msg => msg && typeof msg.message === 'string' && ['user', 'model'].includes(msg.role))
          : [];

        // If history exists, render it; otherwise show greeting
        if (conversationHistory.length > 0) {
          const dayDiv = document.createElement('div');
          dayDiv.className = 'day-divider';
          dayDiv.textContent = new Date().toLocaleDateString();
          threadInner.appendChild(dayDiv);
          for (let index = 0; index < conversationHistory.length; index++) {
            const msg = conversationHistory[index];
            if (msg.role === 'user') {
              addUserMessage(msg.message, false);
            } else if (msg.role === 'model') {
              if (!msg.message) continue;
              const message = addBenMessage(renderMarkdown(msg.message));
              if (index === conversationHistory.length - 1) {
                const topic = conversationHistory.filter(entry => entry.role === 'user').slice(-3).map(entry => entry.message).join(' ');
                showBenActions(message, msg.message, topic);
              }
            }
          }
        } else {
          showChatGreeting(category, threadInner);
        }
        return true;
      } catch (err) {
        if (requestId !== chatLoadRequestId || currentCategory !== category || currentChatSessionKey !== sessionKey) return false;
        console.error('Failed to load chat history:', err);
        addBenMessage('This conversation could not be loaded. Please try opening it again.');
        return false;
      }
    }

    // Back button
    document.getElementById('backToDashboard').addEventListener('click', () => {
      showHeroView();
    });

    // Send message
    document.getElementById('chatSendBtn').addEventListener('click', sendChatMessage);
    document.getElementById('chatInput').addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.isComposing) {
        e.preventDefault();
        sendChatMessage();
      }
    });

    async function sendChatMessage(messageOverride = null) {
      if (chatSending || !currentCategory || document.getElementById('chatInput').disabled) return;
      const input = document.getElementById('chatInput');
      const text = typeof messageOverride === 'string' ? messageOverride.trim() : input.value.trim();
      if (!text) return;
      const category = currentCategory;
      const sessionKey = currentChatSessionKey;
      const requestId = chatLoadRequestId;
      const history = conversationHistory;
      chatSending = true;
      document.getElementById('chatSendBtn').disabled = true;
      BenChatUI.clear();
      addUserMessage(text);
      if (typeof messageOverride !== 'string') input.value = '';
      input.focus();

      const shouldEscalate = /\b(escalate|talk to a person|real person|human assistance|speak to someone|staff member)\b/i.test(text);
      const lastBenMsg = history.filter(msg => msg.role === 'model').pop();
      const isNoToDidThatAnswer = text.toLowerCase().trim() === 'no' &&
        lastBenMsg && lastBenMsg.message.includes('Did that answer your concern?');

      rememberChatSession({ session_key: sessionKey, category, last_message: text, updated_at: Math.floor(Date.now() / 1000) });

      addTypingIndicator();

      try {
        const res = await fetch('api/ai_chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({
            message: text,
            category,
            history: history.slice(0, -1)
          })
        });

        const data = await res.json();
        const isCurrentChat = requestId === chatLoadRequestId && currentCategory === category;
        if (isCurrentChat) removeTypingIndicator();

        if (data.success && data.answer) {
          history.push({ role: 'model', message: data.answer });
          const message = isCurrentChat ? addBenMessage(renderMarkdown(data.answer)) : null;
          await saveChatHistory(category, history, data.answer, sessionKey);
          if (requestId !== chatLoadRequestId || currentChatSessionKey !== sessionKey) return;
          chatSending = false;
          if (shouldEscalate || isNoToDidThatAnswer) await addEscalationFormMessage();
          else showBenActions(message, data.answer, history.filter(entry => entry.role === 'user').slice(-3).map(entry => entry.message).join(' '));
        } else if (isCurrentChat) {
          const message = addBenMessage(escapeHtml(data.error || 'Ben is temporarily unavailable. Please try again shortly or submit your concern to staff.'));
          if (shouldEscalate || isNoToDidThatAnswer) await addEscalationFormMessage();
          else {
            showBenActions(message, '', '', true);
          }
        }
      } catch (err) {
        console.error(err);
        if (requestId === chatLoadRequestId && currentCategory === category) {
          removeTypingIndicator();
          const message = addBenMessage("Sorry, I'm having connection issues. Please try again in a moment.");
          if (shouldEscalate || isNoToDidThatAnswer) await addEscalationFormMessage();
          else {
            showBenActions(message, '', '', true);
          }
        }
      } finally {
        if (requestId === chatLoadRequestId && currentCategory === category) {
          chatSending = false;
          document.getElementById('chatSendBtn').disabled = false;
        }
      }
    }

    // Render concerns list
    async function renderConcernsList() {
      const container = document.getElementById('concernsListContainer');

      try {
        const response = await fetch('api/get_student_concerns_with_replies.php', {
          headers: {
            'X-CSRF-Token': CSRF_TOKEN
          }
        });

        if (!response.ok) throw new Error('Failed to fetch concerns');

        const data = await response.json();
        const concernsWithReplies = data.concerns || [];

        if (concernsWithReplies.length === 0) {
          container.innerHTML = `
        <div class="concerns-empty">
          <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>
          </svg>
          <h3>No Concerns Yet</h3>
          <p>You haven't submitted any concerns. Ask Ben or escalate to get started.</p>
        </div>`;
          return;
        }

        container.innerHTML = '';
        concernsWithReplies.forEach(item => {
          const card = document.createElement('div');
          card.className = 'concern-card concern-card-link';
          card.setAttribute('role', 'button');
          card.setAttribute('tabindex', '0');

          const rawStatus = String(item.status || 'Pending');
          const status = rawStatus.toLowerCase().trim().replace(/\s+/g, '');
          const statusClasses = {
            pending: 'pending',
            inprogress: 'inprogress',
            onhold: 'onhold',
            resolved: 'resolved'
          };
          const statusLabels = {
            pending: 'Pending',
            inprogress: 'In Progress',
            onhold: 'On Hold',
            resolved: 'Resolved'
          };
          const statusClass = Object.prototype.hasOwnProperty.call(statusClasses, status)
            ? statusClasses[status]
            : 'unknown';
          const statusLabel = Object.prototype.hasOwnProperty.call(statusLabels, status)
            ? statusLabels[status]
            : rawStatus;
          const subject = item.subject || String(item.message || item.description || '').slice(0, 60) || 'Concern';
          const duplicateRootId = Number(item.duplicate_of_inquiry_id);

          card.setAttribute(
            'aria-label',
            `${subject}. Current status: ${statusLabel}.${duplicateRootId > 0 ? ` Similar to concern INQ-${duplicateRootId}.` : ''} Open staff replies.`
          );
          card.innerHTML = `
        <div class="concern-card-header">
          <div class="concern-header-left">
            <h3 class="concern-subject">${escapeHtml(subject)}</h3>
            ${duplicateRootId > 0 ? `<span class="concern-duplicate-flag">Similar to INQ-${duplicateRootId}</span>` : ''}
          </div>
          <div class="concern-card-indicators">
            <span class="concern-status ${statusClass}">${escapeHtml(statusLabel)}</span>
          </div>
        </div>
      `;

          const openThread = () => openThreadView(item.inquiry_id);
          card.addEventListener('click', openThread);
          card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              openThread();
            }
          });
          container.appendChild(card);
        });

      } catch (error) {
        console.error('Error loading concerns:', error);
        container.innerHTML = `
      <div class="concerns-error">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
        </svg>
        <h3>Failed to Load Concerns</h3>
        <p>Please refresh the page to try again.</p>
      </div>`;
      }
    }

    // Render chat history in right panel
    function rememberChatSession(session) {
      chatSessions = [session, ...chatSessions.filter(item => item.session_key !== session.session_key)].slice(0, 100);
      renderChatHistory();
    }

    function renderChatHistory() {
      const container = document.getElementById('chatHistoryList');
      const search = document.getElementById('chatHistorySearch')?.value.trim().toLowerCase() || '';
      const matches = chatSessions.filter(item => `${item.category} ${item.last_message || ''}`.toLowerCase().includes(search));
      if (matches.length === 0) {
        container.innerHTML = `<div class="empty-state"><svg class="icon"><use href="#i-chat"/></svg><p>${search ? 'No matching conversations' : 'No conversations yet'}</p></div>`;
        return;
      }

      container.innerHTML = '';
      matches.forEach(item => {
        const row = document.createElement('a');
        row.className = `history-item${item.session_key === currentChatSessionKey ? ' active' : ''}`;
        row.href = '#';
        row.innerHTML = `
      <div class="ic has-office-logo">${categoryIconMarkup(item.category)}</div>
      <div>
        <div class="h-title">${escapeHtml(item.category)}</div>
        <div class="h-sub">${escapeHtml((item.last_message || '').substring(0, 50))}</div>
        <time class="h-time">${new Date(item.updated_at * 1000).toLocaleDateString()}</time>
      </div>`;

        row.addEventListener('click', e => {
          e.preventDefault();
          showChatView(item.category, item.session_key);
          closeChatHistory();
        });

        container.appendChild(row);
      });
    }

    function closeChatHistory() {
      document.getElementById('chatHistoryPanel').classList.remove('history-open');
      document.getElementById('chatHistoryToggle').setAttribute('aria-expanded', 'false');
    }

    document.getElementById('chatHistorySearch').addEventListener('input', renderChatHistory);
    document.getElementById('chatHistoryToggle').addEventListener('click', () => {
      const panel = document.getElementById('chatHistoryPanel');
      const opened = panel.classList.toggle('history-open');
      document.getElementById('chatHistoryToggle').setAttribute('aria-expanded', String(opened));
      if (opened) document.getElementById('chatHistorySearch').focus();
    });
    document.getElementById('chatHistoryClose').addEventListener('click', () => {
      closeChatHistory();
      document.getElementById('chatHistoryToggle').focus();
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && document.getElementById('chatHistoryPanel').classList.contains('history-open')) {
        closeChatHistory();
        document.getElementById('chatHistoryToggle').focus();
      }
    });
    document.addEventListener('click', event => {
      if (!document.getElementById('chatHistoryPanel').contains(event.target)
        && !document.getElementById('chatHistoryToggle').contains(event.target)) closeChatHistory();
    });

    // DOM Ready initialization
    async function sendHeroMessage(text) {
      const loading = showChatView('General');
      const requestId = chatLoadRequestId;
      await loading;
      if (requestId !== chatLoadRequestId || currentCategory !== 'General') return;
      document.getElementById('chatInput').value = text;
      await sendChatMessage();
    }

    document.addEventListener('DOMContentLoaded', function () {
      // Hero search
      document.getElementById('heroSearchInput').addEventListener('keypress', e => {
        if (e.key === 'Enter') {
          const text = e.target.value.trim();
          if (text) {
            sendHeroMessage(text);
          }
        }
      });

      document.getElementById('heroSearchPill').addEventListener('click', () => {
        document.getElementById('heroSearchInput').focus();
      });

      // Initialize
      renderChatHistory();
      if (document.getElementById('mainView').dataset.initialView === 'profile' || window.location.hash === '#profile') showProfileView();
      else if (window.location.hash === '#concerns') showConcernsView();
    });

    // Sidebar reply clicks - open Ben-style thread view
    document.addEventListener('click', function (e) {
      const replyChannel = e.target.closest('.channel[data-inquiry-id]');
      if (replyChannel) {
        e.preventDefault();
        const inquiryId = replyChannel.getAttribute('data-inquiry-id');
        openThreadView(inquiryId);
        return;
      }

      const tile = e.target.closest('[data-category]');
      if (tile) {
        e.preventDefault();
        const category = tile.getAttribute('data-category');
        if (category) {
          showChatView(category);
        }
      }
    });

    document.addEventListener('keydown', function (e) {
      if ((e.key !== 'Enter' && e.key !== ' ') || e.repeat) {
        return;
      }

      const tile = e.target.closest('[data-category][role="button"]');
      if (!tile) {
        return;
      }

      e.preventDefault();
      const category = tile.getAttribute('data-category');
      if (category) {
        showChatView(category);
      }
    });

    // Open Ben-style chat view for a specific inquiry thread
    async function openThreadView(inquiryId) {
      const requestId = ++threadLoadRequestId;
      try {
        // Fetch the full inquiry with all replies
        const res = await fetch('api/get_student_concerns_with_replies.php', {
          headers: { 'X-CSRF-Token': CSRF_TOKEN }
        });
        const data = await res.json();
        if (requestId !== threadLoadRequestId) return;

        if (!res.ok || !data.success || !Array.isArray(data.concerns)) {
          console.error('Failed to fetch concerns');
          return;
        }

        const concern = data.concerns.find(c => c.inquiry_id == inquiryId);
        if (!concern) {
          console.error('Concern not found:', inquiryId);
          return;
        }
        activeThreadInquiryId = Number(concern.inquiry_id);
        currentCategory = null;
        currentChatSessionKey = null;
        chatLoadRequestId++;

        // Hide other views and show thread view
        document.getElementById('heroView').style.display = 'none';
        document.getElementById('concernsView').style.display = 'none';
        document.getElementById('profileView').style.display = 'none';
        document.getElementById('chatView').classList.remove('active');
        document.getElementById('threadView').style.display = 'flex';

        // Update thread header
        const concernText = concern.message || concern.description || '';
        document.getElementById('threadTitle').textContent = concern.subject || String(concernText).slice(0, 60);
        document.getElementById('threadOffice').textContent = concern.office;
        const officeLogo = categoryLogoPaths[concern.office] || defaultCategoryLogoPath;
        const officeLogoImage = document.getElementById('threadOfficeLogo');
        officeLogoImage.hidden = false;
        officeLogoImage.src = officeLogo;

        const statusMap = {
          'pending': 'pending',
          'in progress': 'inprogress',
          'on hold': 'onhold',
          'resolved': 'resolved'
        };
        const statusKey = statusMap[concern.status.toLowerCase()] || concern.status.toLowerCase();
        const statusBadge = document.getElementById('threadStatus');
        statusBadge.className = `status-badge ${statusKey}`;
        statusBadge.textContent = concern.status.charAt(0).toUpperCase() + concern.status.slice(1);

        // Build thread messages
        const threadMessages = document.getElementById('threadMessages');
        threadMessages.innerHTML = '';

        // Add day divider
        const dayDiv = document.createElement('div');
        dayDiv.className = 'thread-day-divider';
        const createdDate = new Date(concern.created_at);
        dayDiv.innerHTML = `<span>${createdDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>`;
        threadMessages.appendChild(dayDiv);

        // Add student's original concern
        const studentMsg = document.createElement('div');
        studentMsg.className = 'thread-message student';
        const studentTime = createdDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
        studentMsg.innerHTML = `
      <div class="thread-avatar">${escapeHtml(STUDENT_INITIALS)}</div>
      <div class="message-content">
        <div class="message-sender">You</div>
        <div class="message-bubble">${escapeHtml(String(concernText))}</div>
        <div class="message-time">${studentTime}</div>
      </div>
    `;
        threadMessages.appendChild(studentMsg);

        // Add staff replies
        if (concern.replies && concern.replies.length > 0) {
          concern.replies.forEach(reply => {
            const staffMsg = document.createElement('div');
            const isStudentReply = reply.sender_role === 'student';
            staffMsg.className = `thread-message ${isStudentReply ? 'student' : 'staff'}`;
            const replyDate = new Date(reply.created_at);
            const replyTime = replyDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            const staffInitials = reply.staff_name ? reply.staff_name.split(' ').map(n => n[0]).join('') : 'ST';

            staffMsg.innerHTML = `
          <div class="thread-avatar">${escapeHtml(isStudentReply ? STUDENT_INITIALS : staffInitials)}</div>
          <div class="message-content">
            <div class="message-sender">${escapeHtml(isStudentReply ? 'You' : reply.staff_name || 'Staff')}</div>
            <div class="message-bubble">${escapeHtml(reply.message).replace(/\n/g, '<br>')}</div>
            <div class="message-time">${replyDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</div>
          </div>
        `;
            threadMessages.appendChild(staffMsg);
          });
        }

        // Update composer based on status
        const replySection = document.getElementById('threadReplySection');
        const feedbackSection = document.getElementById('threadFeedbackSection');

        const normalizedStatus = concern.status.toLowerCase().trim().replace(/\s+/g, '');
        const isOnHold = normalizedStatus === 'onhold';
        const isResolved = normalizedStatus === 'resolved';
        const feedbackLabel = document.getElementById('threadFeedbackLabel');
        const feedbackDescription = document.getElementById('threadFeedbackDescription');
        const replyButton = replySection.querySelector('.thread-reply-btn');
        const feedbackButton = feedbackSection.querySelector('.thread-reply-btn');
        replyButton.disabled = false;
        replyButton.textContent = 'Send Reply';
        feedbackButton.disabled = false;
        feedbackButton.textContent = 'Submit Feedback';

        if (isOnHold) {
          replySection.style.display = 'block';
          feedbackSection.style.display = 'none';
          document.getElementById('threadReplyInput').value = '';
        } else {
          replySection.style.display = 'none';
          feedbackSection.style.display = 'block';
          document.getElementById('threadFeedbackInput').value = '';
          feedbackLabel.textContent = isResolved
            ? 'How was your experience with this concern?'
            : 'How is your concern going so far?';
          feedbackDescription.textContent = isResolved
            ? 'Your feedback helps us improve support for students.'
            : 'Share a quick rating while your concern is being handled.';
          document.querySelectorAll('.thread-emoji-btn').forEach(btn => {
            btn.classList.remove('selected');
            btn.setAttribute('aria-pressed', 'false');
          });
        }
        const actionMessage = document.getElementById('threadActionMessage');
        actionMessage.hidden = true;
        actionMessage.textContent = '';

        // Scroll to bottom
        setTimeout(() => {
          const messageContainer = document.querySelector('.thread-messages-container');
          if (messageContainer) messageContainer.scrollTop = messageContainer.scrollHeight;
        }, 100);

      } catch (error) {
        console.error('Error opening thread view:', error);
      }
    }

    function goBackToConcerns() {
      showConcernsView();
    }

    async function sendThreadReply() {
      const input = document.getElementById('threadReplyInput');
      const message = input.value.trim();

      if (!message) {
        alert('Please type a reply before sending.');
        return;
      }

      const btn = document.querySelector('#threadReplySection .thread-reply-btn');
      if (!btn || btn.disabled || !activeThreadInquiryId) return;
      const inquiryId = activeThreadInquiryId;
      const requestId = threadLoadRequestId;
      const actionMessage = document.getElementById('threadActionMessage');
      btn.disabled = true;
      btn.textContent = 'Sending...';
      actionMessage.hidden = true;

      try {
        const response = await fetch('api/submit_student_reply.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({ inquiry_id: inquiryId, message })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || 'Failed to send reply.');
        }
        if (requestId !== threadLoadRequestId || activeThreadInquiryId !== inquiryId) return;

        const threadMessages = document.getElementById('threadMessages');
        const newMsg = document.createElement('div');
        newMsg.className = 'thread-message student';
        const now = new Date();
        newMsg.innerHTML = `
      <div class="thread-avatar">${escapeHtml(STUDENT_INITIALS)}</div>
      <div class="message-content">
        <div class="message-sender">You</div>
        <div class="message-bubble">${escapeHtml(message).replace(/\n/g, '<br>')}</div>
        <div class="message-time">${now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}</div>
      </div>
    `;
        threadMessages.appendChild(newMsg);
        document.querySelector('.thread-messages-container').scrollTop = threadMessages.scrollHeight;
        input.value = '';
        actionMessage.textContent = 'Reply sent.';
        actionMessage.className = 'thread-action-message success';
        actionMessage.hidden = false;
      } catch (error) {
        console.error('Error sending thread reply:', error);
        if (requestId !== threadLoadRequestId || activeThreadInquiryId !== inquiryId) return;
        actionMessage.textContent = error.message || 'Could not send your reply. Please try again.';
        actionMessage.className = 'thread-action-message error';
        actionMessage.hidden = false;
      } finally {
        if (requestId === threadLoadRequestId && activeThreadInquiryId === inquiryId) {
          btn.disabled = false;
          btn.textContent = 'Send Reply';
        }
      }
    }

    function selectRating(rating) {
      document.querySelectorAll('.thread-emoji-btn').forEach(btn => {
        const selected = Number(btn.dataset.rating) === rating;
        btn.classList.toggle('selected', selected);
        btn.setAttribute('aria-pressed', String(selected));
      });
    }

    async function submitThreadFeedback() {
      const selected = document.querySelector('.thread-emoji-btn.selected');
      if (!selected) {
        alert('Please select a rating');
        return;
      }

      const btn = document.querySelector('#threadFeedbackSection .thread-reply-btn');
      if (!btn || btn.disabled || !activeThreadInquiryId) return;
      const inquiryId = activeThreadInquiryId;
      const requestId = threadLoadRequestId;
      const actionMessage = document.getElementById('threadActionMessage');
      const comment = document.getElementById('threadFeedbackInput').value.trim();
      btn.disabled = true;
      btn.textContent = 'Submitting...';
      actionMessage.hidden = true;

      try {
        const response = await fetch('api/submit_feedback.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({
            inquiry_id: inquiryId,
            rating: Number(selected.dataset.rating),
            comment
          })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.error || 'Failed to submit feedback.');
        }
        if (requestId !== threadLoadRequestId || activeThreadInquiryId !== inquiryId) return;

        const feedbackSection = document.getElementById('threadFeedbackSection');
        feedbackSection.style.display = 'none';
        actionMessage.textContent = 'Thank you for your feedback!';
        actionMessage.className = 'thread-action-message success';
        actionMessage.hidden = false;
      } catch (error) {
        console.error('Error submitting thread feedback:', error);
        if (requestId !== threadLoadRequestId || activeThreadInquiryId !== inquiryId) return;
        actionMessage.textContent = error.message || 'Could not submit feedback. Please try again.';
        actionMessage.className = 'thread-action-message error';
        actionMessage.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Submit Feedback';
      }
    }

    function closeChatView() {
      document.getElementById('chatView').classList.remove('active');
    }

    function capitalize(str) {
      if (!str) return '';
      return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    async function submitStudentReply(inquiryId, concern) {
      const message = document.getElementById('replyInput').value.trim();
      if (!message) return;

      const btn = document.getElementById('sendReplyBtn');
      btn.disabled = true;

      try {
        const res = await fetch('api/submit_student_reply.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
          },
          body: JSON.stringify({
            inquiry_id: inquiryId,
            message: message
          })
        });

        const result = await res.json();
        if (result.success) {
          // Add the new message to the thread
          const chatThread = document.getElementById('chatThread');
          const studentMsg = document.createElement('div');
          studentMsg.className = 'thread-message student';
          const now = new Date();
          const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

          studentMsg.innerHTML = `
        <div class="thread-avatar">${initials}</div>
        <div class="thread-bubble-wrap">
          <div class="thread-message-name">You</div>
          <div class="thread-bubble">${escapeHtml(message)}</div>
          <div class="thread-message-time">${now.toLocaleDateString()} ${timeStr}</div>
        </div>
      `;
          chatThread.appendChild(studentMsg);

          document.getElementById('replyInput').value = '';
          setTimeout(() => {
            chatThread.scrollTop = chatThread.scrollHeight;
          }, 100);
        } else {
          alert('Failed to submit reply: ' + (result.error || 'Unknown error'));
        }
      } catch (error) {
        console.error('Error submitting reply:', error);
        alert('Error submitting reply');
      } finally {
        btn.disabled = false;
      }
    }

    function setupFeedbackForm(inquiryId, concern) {
      let selectedRating = 0;

      document.querySelectorAll('.emoji-btn').forEach(btn => {
        btn.classList.remove('selected');
        btn.onclick = function () {
          document.querySelectorAll('.emoji-btn').forEach(b => b.classList.remove('selected'));
          this.classList.add('selected');
          selectedRating = parseInt(this.getAttribute('data-rating'));
        };
      });

      document.getElementById('submitFeedbackBtn').onclick = async () => {
        if (!selectedRating) {
          alert('Please select a rating');
          return;
        }

        const comment = document.getElementById('feedbackComment').value.trim();
        const btn = document.getElementById('submitFeedbackBtn');
        btn.disabled = true;

        try {
          const res = await fetch('api/submit_feedback.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
              inquiry_id: inquiryId,
              rating: selectedRating,
              comment: comment
            })
          });

          const result = await res.json();
          if (result.success) {
            alert('Thank you for your feedback!');
            document.getElementById('feedbackForm').innerHTML = '<p style="text-align: center; color: var(--muted);">Feedback submitted. Thank you!</p>';
          } else {
            alert('Failed to submit feedback: ' + (result.error || 'Unknown error'));
          }
        } catch (error) {
          console.error('Error submitting feedback:', error);
          alert('Error submitting feedback');
        } finally {
          btn.disabled = false;
        }
      };
    }

  </script>
  <script>
    document.addEventListener('helpdesk:open-notification', event => {
      if (event.detail.inquiry_id) {
        event.preventDefault();
        openThreadView(event.detail.inquiry_id);
      }
    });
    document.addEventListener('helpdesk:notification', event => {
      if (document.getElementById('concernsView').style.display === 'block') renderConcernsList();
      if (Number(activeThreadInquiryId) === Number(event.detail.inquiry_id)
        && !document.getElementById('threadReplyInput').value.trim()
        && !document.getElementById('threadFeedbackInput').value.trim()
        && !document.querySelector('.thread-emoji-btn.selected')
        && !document.querySelector('#threadReplySection .thread-reply-btn').disabled
        && !document.querySelector('#threadFeedbackSection .thread-reply-btn').disabled) {
        openThreadView(activeThreadInquiryId);
      }
    });
    const notificationInquiry = Number(new URLSearchParams(window.location.search).get('inquiry_id'));
    if (Number.isSafeInteger(notificationInquiry) && notificationInquiry > 0) openThreadView(notificationInquiry);
  </script>
</body>

</html>