<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/controllers/InquiryController.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

session_start();
requireRole('student');

$studentId = (int) $_SESSION['user_id'];
$controller = new InquiryController();

$dbConn = getDbConnection();
$officeResult = $dbConn->query('SELECT office_id, office_name FROM offices WHERE is_active = 1 ORDER BY office_name');
$offices = $officeResult->fetch_all(MYSQLI_ASSOC);

$inquiries = $controller->listForStudent($studentId);

// Fetch recent staff replies grouped by office
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
    LIMIT 10
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Ask Ben - HELPDESKCRMC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
<link rel="stylesheet" href="assets/css/dashboard_student.css?v=1">
</head>
<body>

<svg style="display:none" aria-hidden="true">
<defs>
  <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1v-9"/></symbol>
  <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/><path d="M8.5 13h7M8.5 17h7"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></symbol>
  <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z"/><path d="M10 19a2 2 0 0 0 4 0"/></symbol>
  <symbol id="i-folder" viewBox="0 0 24 24"><path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1Z"/></symbol>
  <symbol id="i-dollar" viewBox="0 0 24 24"><path d="M12 2v20"/><path d="M17 6.5c0-1.8-2-3-5-3s-5 1.4-5 3.2 2 2.8 5 3.3 5 1.5 5 3.3-2 3.2-5 3.2-5-1.2-5-3"/></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c1-3 3-4.5 5.5-4.5s4.5 1.5 5.5 4.5"/><circle cx="17" cy="8.5" r="2.3"/><path d="M15.5 14.2c2.2.4 3.5 1.8 4.4 4.3"/></symbol>
  <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5v.01"/></symbol>
  <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H12v18H6.5A2.5 2.5 0 0 1 4 18.5Z"/><path d="M20 5.5A2.5 2.5 0 0 0 17.5 3H12v18h5.5a2.5 2.5 0 0 0 2.5-2.5Z"/></symbol>
  <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12 20 3"/><path d="M16 7l3 3"/><path d="M13 10l2.5 2.5"/></symbol>
  <symbol id="i-cross" viewBox="0 0 24 24"><path d="M12 4v16M4 12h16" stroke-width="3"/></symbol>
  <symbol id="i-monitor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="1.5"/><path d="M9 21h6M12 17v4"/></symbol>
  <symbol id="i-briefcase" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="12" rx="1.5"/><path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></symbol>
  <symbol id="i-code" viewBox="0 0 24 24"><path d="m9 8-4 4 4 4"/><path d="m15 8 4 4-4 4"/></symbol>
  <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M12 20V4M20 20v-7"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v6c0 5-3 8-7 9-4-1-7-4-7-9V6Z"/></symbol>
  <symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.8 1.8 0 0 0 .3 1.9l.1.1a2.1 2.1 0 1 1-3 3l-.1-.1a1.8 1.8 0 0 0-1.9-.3 1.8 1.8 0 0 0-1.1 1.6V21a2.1 2.1 0 1 1-4.2 0v-.1a1.8 1.8 0 0 0-1.1-1.6 1.8 1.8 0 0 0-1.9.3l-.1.1a2.1 2.1 0 1 1-3-3l.1-.1a1.8 1.8 0 0 0 .3-1.9 1.8 1.8 0 0 0-1.6-1.1H2.9a2.1 2.1 0 1 1 0-4.2H3a1.8 1.8 0 0 0 1.6-1.1 1.8 1.8 0 0 0-.3-1.9l-.1-.1a2.1 2.1 0 1 1 3-3l.1.1a1.8 1.8 0 0 0 1.9.3H9.3A1.8 1.8 0 0 0 10.4 3V2.9a2.1 2.1 0 1 1 4.2 0V3a1.8 1.8 0 0 0 1.1 1.6 1.8 1.8 0 0 0 1.9-.3l.1-.1a2.1 2.1 0 1 1 3 3l-.1.1a1.8 1.8 0 0 0-.3 1.9v.1a1.8 1.8 0 0 0 1.6 1.1h.1a2.1 2.1 0 1 1 0 4.2H21a1.8 1.8 0 0 0-1.6 1.1Z"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7z"/></symbol>
  <symbol id="i-back" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></symbol>
  <symbol id="i-arrow-up" viewBox="0 0 24 24"><path d="M12 19V5"/><path d="M6 11l6-6 6 6"/></symbol>
  <symbol id="i-paperclip" viewBox="0 0 24 24"><path d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7"/></symbol>
  <symbol id="i-reply" viewBox="0 0 24 24"><path d="M9 8 4 12l5 4"/><path d="M4 12h9a6 6 0 0 1 6 6v1"/></symbol>
  <symbol id="i-hash" viewBox="0 0 24 24"><path d="M9 4 7 20M17 4l-2 16M4 9h16M3.5 15h16"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></symbol>
  <symbol id="i-updown" viewBox="0 0 24 24"><path d="M8 9l4-4 4 4"/><path d="M16 15l-4 4-4-4"/></symbol>
  <symbol id="i-collapse" viewBox="0 0 24 24"><path d="M14 6l-6 6 6 6"/><path d="M19 6l-6 6 6 6"/></symbol>
</defs>
</svg>

<div class="app">
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
      <div class="brand-name">Helpdesk<span>CRMC</span></div>
    </div>

    <a class="nav-item active" id="navAskBen"><svg class="icon"><use href="#i-home"/></svg> Ask Ben</a>
    <a class="nav-item" id="navMyConcerns"><svg class="icon"><use href="#i-file"/></svg> My Concerns</a>
    <a class="nav-item" id="navProfile"><svg class="icon"><use href="#i-user"/></svg> Profile</a>

    <div class="nav-label">
      <span>REPLIES FROM STAFF</span>
    </div>

    <?php if (!empty($repliesByOffice)): ?>
      <?php foreach (array_slice($repliesByOffice, 0, 2) as $officeName => $replies): ?>
        <?php
          $officeInitial = mb_strtoupper(mb_substr($officeName, 0, 1));
          $colors = [
            'Registrar' => ['#b8231c', '#8f1912'],
            'SASO' => ['#ecc94b', '#c98a06'],
            'Finance' => ['#1e7a8c', '#155e6d'],
          ];
          $gradient = $colors[$officeName] ?? ['#847c6e', '#5c5648'];
          $latestReply = $replies[0];
          $timeAgo = '';
          $diff = time() - strtotime($latestReply['created_at']);
          if ($diff < 3600) $timeAgo = floor($diff / 60) . 'm';
          elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . 'h';
          else $timeAgo = floor($diff / 86400) . 'd';
        ?>
        <div class="group">
          <div class="group-head">
            <div class="badge" style="background:linear-gradient(135deg,<?= $gradient[0] ?>,<?= $gradient[1] ?>);"><?= htmlspecialchars($officeInitial) ?></div>
            <span><?= htmlspecialchars($officeName) ?></span>
          </div>
          <a class="channel" data-inquiry-id="<?= $latestReply['inquiry_id'] ?>">
            <svg class="icon" style="width:15px;height:15px;"><use href="#i-chat"/></svg>
            <span class="snippet"><?= htmlspecialchars(mb_substr($latestReply['subject'], 0, 25)) ?></span>
            <span class="time"><?= htmlspecialchars($timeAgo) ?></span>
          </a>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-replies">
        <svg class="icon" style="width:20px;height:20px;color:#c4bba6;margin:0 auto 6px;"><use href="#i-chat"/></svg>
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

    <a href="logout.php" class="nav-item" style="margin-top:8px;"><svg class="icon"><use href="#i-logout"/></svg> Logout</a>

    <div class="sidebar-foot">
      <div class="sidebar-profile">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
          <div class="user-sub">BSIT · 3rd Year</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="main" id="mainView">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div id="heroView">
      <div class="hero">
        <div class="hero-avatar"><img src="assets/images/ben-model.png" alt="Ben"></div>
        <h1>Good morning, <?= htmlspecialchars($firstName) ?>. I'm <b>Ben</b></h1>
        <p>I'm here to help you with your concern.</p>
        <p class="sub">Choose a category below to get started</p>
        <div class="search-pill" id="heroSearchPill">
          <svg class="icon"><use href="#i-search"/></svg>
          <input placeholder="Type your concern, e.g. 'I lost my student ID'" id="heroSearchInput" />
        </div>
      </div>

      <div class="content">
        <div class="section-title">GENERAL</div>
        <div class="general-card" data-category="General">
          <div class="ic"><svg class="icon"><use href="#i-chat"/></svg></div>
          <div>
            <h3>General Inquiry</h3>
            <p>Ask a concern directly to Ben — no category needed.</p>
          </div>
        </div>

        <div class="section-title">OFFICES</div>
        <div class="grid">
          <div class="tile" data-category="Registrar"><div class="ic"><svg class="icon"><use href="#i-folder"/></svg></div><h4>Registrar</h4><p>Enrollment, records, IDs</p></div>
          <div class="tile alt" data-category="Finance"><div class="ic"><svg class="icon"><use href="#i-dollar"/></svg></div><h4>Finance</h4><p>Fees, payments, receipts</p></div>
          <div class="tile" data-category="SASO"><div class="ic"><svg class="icon"><use href="#i-users"/></svg></div><h4>SASO</h4><p>Student affairs & orgs</p></div>
          <div class="tile alt" data-category="Guidance"><div class="ic"><svg class="icon"><use href="#i-info"/></svg></div><h4>Guidance</h4><p>Counseling & support</p></div>
          <div class="tile" data-category="Library"><div class="ic"><svg class="icon"><use href="#i-book"/></svg></div><h4>Library</h4><p>Books, fines, access</p></div>
          <div class="tile alt" data-category="Property Custodian"><div class="ic"><svg class="icon"><use href="#i-key"/></svg></div><h4>Property Custodian</h4><p>Facilities & equipment</p></div>
          <div class="tile" data-category="Clinic"><div class="ic"><svg class="icon"><use href="#i-cross"/></svg></div><h4>Clinic</h4><p>Medical certificates</p></div>
          <div class="tile alt" data-category="ITCD"><div class="ic"><svg class="icon"><use href="#i-monitor"/></svg></div><h4>ITCD</h4><p>Portal & IT support</p></div>
          <div class="tile" data-category="Human Resources"><div class="ic"><svg class="icon"><use href="#i-briefcase"/></svg></div><h4>Human Resources</h4><p>Employment inquiries</p></div>
        </div>

        <div class="section-title">DEPARTMENTS</div>
        <div class="grid">
          <div class="tile" data-category="CCS"><div class="ic"><svg class="icon"><use href="#i-code"/></svg></div><h4>CCS</h4><p>Computer science dept</p></div>
          <div class="tile alt" data-category="CBE"><div class="ic"><svg class="icon"><use href="#i-chart"/></svg></div><h4>CBE</h4><p>Business education dept</p></div>
          <div class="tile" data-category="CTE"><div class="ic"><svg class="icon"><use href="#i-book"/></svg></div><h4>CTE</h4><p>Teacher education dept</p></div>
          <div class="tile alt" data-category="CCJE"><div class="ic"><svg class="icon"><use href="#i-shield"/></svg></div><h4>CCJE</h4><p>Criminal justice dept</p></div>
        </div>
      </div>
    </div>

    <div id="concernsView" class="concerns-header" style="display:none;">
      <h1>My Concerns</h1>
      <p>Everything you've submitted or asked Ben about, including replies from staff.</p>
      <div class="faq-card" id="concernsListContainer"></div>
    </div>

    <div id="chatView">
      <div class="chat-header">
        <button class="back-btn" id="backToDashboard"><svg class="icon"><use href="#i-back"/></svg></button>
        <div class="ic" id="chatCategoryIcon"><svg class="icon"><use href="#i-chat"/></svg></div>
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
          <button class="attach-btn" id="chatAttachBtn"><svg class="icon"><use href="#i-paperclip"/></svg></button>
          <input type="text" placeholder="Message Ben…" id="chatInput" />
          <button class="send-btn" id="chatSendBtn"><svg class="icon"><use href="#i-arrow-up"/></svg></button>
        </div>
        <div class="composer-hint">Ben can make mistakes. For urgent concerns, visit the office directly.</div>
      </div>
    </div>
  </main>

  <aside class="panel">
    <div class="panel-head">
      <h3>Chat history</h3>
      <div class="bell"><svg class="icon"><use href="#i-bell"/></svg></div>
    </div>
    <div class="search-mini">
      <svg class="icon"><use href="#i-search"/></svg>
      Search conversations…
    </div>
    <div id="chatHistoryList"></div>
  </aside>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

let concerns = <?= json_encode(array_map(function($inq) {
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
let conversationHistory = [];

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
  'CCJE': { icon: 'i-shield', desc: 'Criminal justice dept' }
};

// Nav switching
document.getElementById('navAskBen').addEventListener('click', e => {
  e.preventDefault();
  showHeroView();
});

document.getElementById('navMyConcerns').addEventListener('click', e => {
  e.preventDefault();
  showConcernsView();
});

function showHeroView() {
  document.getElementById('heroView').style.display = 'block';
  document.getElementById('concernsView').style.display = 'none';
  const chatView = document.getElementById('chatView');
  chatView.classList.remove('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('navAskBen').classList.add('active');
}

function showConcernsView() {
  document.getElementById('heroView').style.display = 'none';
  document.getElementById('concernsView').style.display = 'block';
  document.getElementById('chatView').classList.remove('active');
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  document.getElementById('navMyConcerns').classList.add('active');
  renderConcernsList();
}

function showChatView(category) {
  console.log('showChatView called with category:', category);

  const heroView = document.getElementById('heroView');
  const concernsView = document.getElementById('concernsView');
  const chatView = document.getElementById('chatView');

  console.log('Elements found:', {heroView, concernsView, chatView});

  heroView.style.display = 'none';
  concernsView.style.display = 'none';
  chatView.classList.add('active');

  console.log('chatView classList after add:', chatView.classList.toString());
  console.log('chatView computed display:', window.getComputedStyle(chatView).display);

  currentCategory = category;
  conversationHistory = [];

  const meta = categoryMeta[category] || { icon: 'i-chat', desc: 'Ask your concern' };
  document.getElementById('chatCategoryName').textContent = category;
  document.getElementById('chatCategoryDesc').textContent = meta.desc;
  document.getElementById('chatCategoryIcon').innerHTML = `<svg class="icon"><use href="#${meta.icon}"/></svg>`;

  const threadInner = document.getElementById('chatThreadInner');
  threadInner.innerHTML = '';

  // Day divider
  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  const dayDiv = document.createElement('div');
  dayDiv.className = 'day-divider';
  dayDiv.textContent = `Today · ${timeStr}`;
  threadInner.appendChild(dayDiv);

  // Initial Ben greeting
  const studentFirstName = '<?= htmlspecialchars($firstName) ?>';
  addBenMessage(`Hi ${studentFirstName}! I see you'd like help with <b>${escapeHtml(category)}</b> concerns. What can I help you with today?`);

  document.getElementById('chatInput').focus();
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
}

async function addEscalationFormMessage(prefillOffice = '', prefillSubject = '') {
  try {
    const response = await fetch('assets/components/escalation-form.html');
    const formHtml = await response.text();

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
          <p style="margin: 0 0 12px; font-size: 14px;">I understand you'd like direct help from staff. Fill out the form below and the right office will follow up with you soon.</p>
          ${formHtml}
        </div>
        <span class="time">${timeStr}</span>
      </div>`;

    threadInner.appendChild(msg);

    // Prefill form if values provided
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

    // Set up form submission
    const form = msg.querySelector('[data-escalation-form]');
    if (form) {
      form.addEventListener('submit', handleEscalationSubmit);
    }

    scrollChatToBottom();
  } catch (error) {
    console.error('Failed to load escalation form:', error);
    addBenMessage("I'm having trouble loading the escalation form. Please call our main office at (032) 434-8488 for direct assistance.");
  }
}

async function handleEscalationSubmit(e) {
  e.preventDefault();

  const form = e.target;
  const submitBtn = form.querySelector('.esc-btn');
  const errorAlert = form.querySelector('[data-error-msg]');
  const successDiv = form.querySelector('[data-success-msg]');

  // Clear previous messages
  errorAlert.style.display = 'none';
  successDiv.style.display = 'none';

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

  // Disable submit button
  submitBtn.disabled = true;
  const originalText = submitBtn.textContent;
  submitBtn.textContent = 'Submitting...';

  try {
    const response = await fetch('api/submit_inquiry.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN
      },
      body: JSON.stringify(formData)
    });

    const result = await response.json();

    if (result.success) {
      // Hide form, show success
      form.style.display = 'none';
      successDiv.style.display = 'block';
      successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

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
    errorAlert.textContent = 'Connection error. Please try again or call (032) 434-8488.';
    errorAlert.style.display = 'block';
    errorAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = originalText;
  }
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

function addUserMessage(text) {
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
  conversationHistory.push({ role: 'student', message: text });
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

// Back button
document.getElementById('backToDashboard').addEventListener('click', () => {
  showHeroView();
});

// Send message
document.getElementById('chatSendBtn').addEventListener('click', sendChatMessage);
document.getElementById('chatInput').addEventListener('keypress', e => {
  if (e.key === 'Enter') sendChatMessage();
});

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text = input.value.trim();
  if (!text) return;

  addUserMessage(text);
  input.value = '';

  // Check for escalation triggers
  const escalationTriggers = ['no', 'escalate', 'talk to a person', 'real person', 'human assistance', 'speak to someone', 'staff member'];
  const shouldEscalate = escalationTriggers.some(trigger => text.toLowerCase().includes(trigger.toLowerCase()));

  // Check if this is a "no" response to Ben's "Did that answer your concern?" question
  const lastBenMsg = conversationHistory.filter(msg => msg.role === 'assistant').pop();
  const isNoToDidThatAnswer = text.toLowerCase().trim() === 'no' &&
    lastBenMsg && lastBenMsg.message.includes('Did that answer your concern?');

  // Add to chat history in right panel
  const existingIdx = concerns.findIndex(c => c.office === currentCategory && c.isLocal);
  if (existingIdx !== -1) {
    concerns[existingIdx].subject = text;
    concerns[existingIdx].date = 'Just now';
  } else {
    concerns.unshift({
      id: 'local_' + Date.now(),
      subject: text,
      office: currentCategory || 'General',
      status: 'active',
      date: 'Just now',
      isLocal: true
    });
  }
  renderChatHistory();

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
        category: currentCategory,
        history: conversationHistory
      })
    });

    const data = await res.json();
    removeTypingIndicator();

    if (data.success && data.answer) {
      addBenMessage(renderMarkdown(data.answer));
      conversationHistory.push({ role: 'assistant', message: data.answer });

      // Show escalation form if user indicated need for escalation
      if (shouldEscalate || isNoToDidThatAnswer) {
        setTimeout(() => {
          addEscalationFormMessage();
        }, 500);
      }
    } else {
      addBenMessage("I'm having trouble processing that right now. Could you try rephrasing your concern?");
    }
  } catch (err) {
    console.error(err);
    removeTypingIndicator();
    addBenMessage("Sorry, I'm having connection issues. Please try again in a moment.");
  }
}

// Render concerns list
function renderConcernsList() {
  const container = document.getElementById('concernsListContainer');
  if (!concerns || concerns.length === 0) {
    container.innerHTML = '<div class="reply-empty">No concerns submitted yet.</div>';
    return;
  }

  container.innerHTML = '';
  concerns.forEach(item => {
    const wrap = document.createElement('div');
    const row = document.createElement('div');
    row.className = 'faq-row';
    const statusClass = item.status === 'pending' ? 'pending' : item.status === 'inprogress' ? 'inprogress' : 'resolved';
    const statusLabel = item.status === 'pending' ? 'Pending' : item.status === 'inprogress' ? 'In Progress' : 'Resolved';

    row.innerHTML = `
      <div class="left">
        <div class="subject">${escapeHtml(item.subject)}</div>
        <div class="meta">${escapeHtml(item.office)} · Submitted ${escapeHtml(item.date)}</div>
      </div>
      <div class="right">
        <span class="tag ${statusClass}">${statusLabel}</span>
        <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
      </div>`;

    const detail = document.createElement('div');
    detail.className = 'concern-detail';
    detail.innerHTML = item.reply
      ? `<div class="reply-box"><div class="reply-head">${escapeHtml(item.reply.from)} · ${escapeHtml(item.reply.date)}</div><div class="reply-msg">${escapeHtml(item.reply.message)}</div></div>`
      : `<div class="reply-empty">No reply yet — staff at ${escapeHtml(item.office)} typically respond within 2–3 working days.</div>`;

    row.addEventListener('click', () => {
      const open = row.classList.contains('open');
      row.classList.toggle('open');
    });

    wrap.appendChild(row);
    wrap.appendChild(detail);
    container.appendChild(wrap);
  });
}

// Render chat history in right panel
function renderChatHistory() {
  const container = document.getElementById('chatHistoryList');
  if (!concerns || concerns.length === 0) {
    container.innerHTML = '<div class="empty-state"><svg class="icon"><use href="#i-chat"/></svg><p>No conversations yet</p><span>Ask Ben something to start one</span></div>';
    return;
  }

  container.innerHTML = '';
  concerns.slice(0, 5).forEach(item => {
    const meta = categoryMeta[item.office] || { icon: 'i-chat', desc: '' };
    const row = document.createElement('a');
    row.className = 'history-item';
    row.href = '#';

    row.innerHTML = `
      <div class="ic"><svg class="icon"><use href="#${meta.icon}"/></svg></div>
      <div>
        <div class="h-title">${escapeHtml(item.office)}</div>
        <div class="h-sub">${escapeHtml(item.subject.substring(0, 25))}… · ${escapeHtml(item.date)}</div>
      </div>`;

    row.addEventListener('click', e => {
      e.preventDefault();
      showChatView(item.office);
    });

    container.appendChild(row);
  });
}

// DOM Ready initialization
document.addEventListener('DOMContentLoaded', function() {
  // Hero search
  document.getElementById('heroSearchInput').addEventListener('keypress', e => {
    if (e.key === 'Enter') {
      const text = e.target.value.trim();
      if (text) {
        showChatView('General');
        setTimeout(() => {
          document.getElementById('chatInput').value = text;
          sendChatMessage();
        }, 300);
      }
    }
  });

  document.getElementById('heroSearchPill').addEventListener('click', () => {
    document.getElementById('heroSearchInput').focus();
  });

  // Initialize
  renderChatHistory();
});

// Category tile clicks using event delegation to ensure they always trigger
document.addEventListener('click', function(e) {
  console.log('Click detected on:', e.target);
  const tile = e.target.closest('[data-category]');
  console.log('Closest tile found:', tile);
  if (tile) {
    e.preventDefault();
    const category = tile.getAttribute('data-category');
    console.log('Category:', category);
    if (category) {
      console.log('About to call showChatView with:', category);
      showChatView(category);
    }
  }
});

// Additional CSS for escalation messages
const escalationStyles = `
<style>
.escalation-msg .bubble {
  max-width: 480px;
}

.escalation-card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 16px;
  margin: 10px 0 0;
  font-size: 14px;
}

.esc-header {
  margin-bottom: 16px;
}

.esc-header h3 {
  margin: 0 0 4px;
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
}

.esc-header p {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
  line-height: 1.4;
}

.esc-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.esc-alert {
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 4px;
}

.esc-alert-error {
  background: rgba(184, 35, 28, .08);
  border: 1px solid rgba(184, 35, 28, .2);
  color: var(--red);
}

.esc-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.esc-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.esc-group label {
  font-weight: 600;
  font-size: 12.5px;
  color: var(--ink);
}

.esc-req {
  color: var(--red);
}

.esc-group input,
.esc-group select,
.esc-group textarea {
  padding: 8px 10px;
  border: 1px solid var(--line);
  border-radius: 7px;
  background: var(--cream);
  color: var(--ink);
  font-family: inherit;
  font-size: 13px;
  transition: all .2s;
}

.esc-group input:focus,
.esc-group select:focus,
.esc-group textarea:focus {
  outline: none;
  border-color: var(--amber);
  box-shadow: 0 0 0 2px rgba(236, 201, 75, .12);
  background: var(--card);
}

.esc-group textarea {
  resize: vertical;
  min-height: 60px;
  line-height: 1.4;
}

.esc-group select {
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%23847C6E' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  padding-right: 32px;
}

.esc-btn {
  padding: 10px 14px;
  background: var(--amber);
  color: var(--ink);
  border: none;
  border-radius: 7px;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  transition: all .2s;
  margin-top: 4px;
}

.esc-btn:hover:not(:disabled) {
  background: var(--amber-dk);
  transform: translateY(-1px);
}

.esc-btn:disabled {
  opacity: .6;
  cursor: not-allowed;
}

.esc-success {
  padding: 12px;
  background: rgba(30, 122, 140, .08);
  border: 1px solid rgba(30, 122, 140, .2);
  color: var(--teal);
  border-radius: 8px;
  font-size: 13px;
  text-align: center;
  font-weight: 500;
  line-height: 1.4;
}

@media (max-width: 500px) {
  .esc-row {
    grid-template-columns: 1fr;
  }
}
</style>
`;

// Inject escalation styles into head
if (!document.querySelector('#escalation-styles')) {
  const styleEl = document.createElement('div');
  styleEl.id = 'escalation-styles';
  styleEl.innerHTML = escalationStyles;
  document.head.appendChild(styleEl);
}
</script>
</body>
</html>
