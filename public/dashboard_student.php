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

// Fetch offices from the database for the Ben Flow office picker
$dbConn = getDbConnection();
$officeResult = $dbConn->query('SELECT office_id, office_name FROM offices WHERE is_active = 1 ORDER BY office_name');
$offices = $officeResult->fetch_all(MYSQLI_ASSOC);

$inquiries = $controller->listForStudent($studentId);

function statusLabel(string $status): string
{
    return match ($status) {
        'Pending' => 'Pending',
        'In Progress' => 'In Progress',
        'Resolved' => 'Resolved',
        default => ucfirst($status),
    };
}

$statusCounts = ['Pending' => 0, 'In Progress' => 0, 'Resolved' => 0];
foreach ($inquiries as $inquiry) {
    if (isset($statusCounts[$inquiry['status']])) {
        $statusCounts[$inquiry['status']]++;
    }
}

$initials = '';
foreach (preg_split('/\s+/', trim((string) $_SESSION['name'])) as $part) {
    if ($part !== '') {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
$initials = mb_substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ask Ben - HELPDESKCRMC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/design-system.css">
<link rel="stylesheet" href="assets/css/student-dashboard.css">
<link rel="stylesheet" href="assets/css/category_ui.css">
<link rel="stylesheet" href="assets/css/conversation-embedded.css">
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>">
</head>
<body>

<!-- Ben Flow Overlay -->
<div id="benFlow">
  <div class="bf-modal">
    <!-- Step 1: Category Selection -->
    <div id="bfStep1">
      <div class="bf-header">
        <button class="bf-back" id="bfExitToDash">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Ask Ben
        </button>
        <img src="https://i.imgur.com/placeholder-logo.png" alt="HelpDesk CRMC" class="bf-logo" style="display:none;">
      </div>

      <div class="bf-step1-head">
          <h1>Hello, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?> — how can Ben help?</h1>
          <div class="bf-mascot">
            <img src="assets/images/ben_interactions%20vector/hi_bot.png" alt="Ben assistant">
          </div>
        <h2 id="bfChooseLabel">PLEASE CHOOSE WHAT YOU NEED HELP WITH</h2>
        <button id="bfBackToCategories" class="bf-back-cats" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Back to categories
        </button>
      </div>

      <div class="bf-cat-scroll">
        <div class="bf-cat-row" id="bfCatRow"></div>
      </div>
      <div class="bf-scroll-track" id="bfScrollTrack">
        <div class="bf-scroll-thumb" id="bfScrollThumb"></div>
      </div>
    </div>

    <!-- Step 2: Conversation Thread -->
    <div id="bfStep2">
      <div class="bf-step2-head">
        <button id="bfBackToStep1">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </button>
        <div class="bf-chat-identity">
          <div class="bf-chat-avatar">
            <img src="assets/images/ben_interactions%20vector/hi_bot.png" alt="BenAI">
          </div>
          <div>
            <div class="bf-chat-name">BenAI</div>
            <div class="bf-chat-status"><span></span>Online now</div>
          </div>
          <div class="bf-office-pill" id="bfOfficePill">General</div>
        </div>
      </div>
      <div class="bf-thread" id="bfThread"></div>
      <div class="bf-input-bar">
        <div class="bf-attach-chip" id="bfAttachChip">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5.5 5.5 0 0 1-7.78-7.78l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48"/></svg>
          <div class="bf-attach-info">
            <div class="bf-attach-name" id="bfAttachName"></div>
            <div class="bf-attach-size" id="bfAttachSize"></div>
          </div>
          <button class="bf-attach-remove" id="bfAttachRemove">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="bf-input-row">
          <input type="file" id="bfFileInput" style="display:none;" multiple>
          <button class="bf-attach-btn" id="bfAttachBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5.5 5.5 0 0 1-7.78-7.78l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48"/></svg>
          </button>
          <div class="bf-input-wrap">
            <textarea class="bf-input" id="bfInput" placeholder="Type your concern..." rows="1"></textarea>
          </div>
          <button class="bf-mic-btn" type="button" aria-label="Use microphone">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/></svg>
          </button>
          <button class="bf-send" id="bfSend">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4 20-7z"/></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Ask Ben workspace -->
<div id="dashboardView" class="app app-three-col">
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/helpdeskcrmc_logo.png" alt="Helpdesk CRMC" class="brand-logo">
      <div class="s">Student Portal</div>
    </div>

    <div class="nav-group">
      <div class="nav-label">Main</div>
      <nav>
        <a class="nav-item active" href="#" id="navDashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>
          Ask Ben
        </a>
        <a class="nav-item" href="#" id="navMyConcerns">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>
          My Concerns
        </a>
      </nav>
    </div>

    <div class="nav-group">
      <div class="nav-label">Other</div>
      <nav>
        <a class="nav-item" href="#">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6"/></svg>
          Profile
        </a>
        <a class="nav-item" href="logout.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
          Logout
        </a>
      </nav>
    </div>

    <div class="side-promo">
      <h4>Need urgent help?</h4>
      <p>Walk-in concerns are still welcome at the Student Affairs office.</p>
      <button>Visit SASO</button>
    </div>
  </aside>

  <!-- Topbar spanning main + chat history -->
  <div class="topbar topbar-wide">
    <div class="who">
      <div class="n"><?= htmlspecialchars($_SESSION['name']) ?></div>
      <div class="s">BSIT · 3rd Year</div>
    </div>
    <div class="top-actions">
      <button class="icon-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
        <span class="dot"></span>
      </button>
      <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    </div>
  </div>

  <main>
    <div id="dashHome" class="figma-dash">
      <section class="figma-welcome">
        <div class="figma-ben-avatar">
          <img src="assets/images/ben_interactions%20vector/happybot.png" alt="Ben assistant">
        </div>
        <h1>Goodmorning, <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>. I'm, <span class="ben">Ben</span></h1>
        <div class="figma-subtitle">I'm here to help you with your concern.</div>
        <div class="figma-category-instruction">Choose a category below to get started</div>
      </section>

      <div class="figma-section-label">General</div>
      <div class="figma-category-grid">
        <button class="figma-category-card" data-office="General">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
          <h3>General Inquiry</h3>
          <p>Ask concern directly to ai.</p>
        </button>
      </div>

      <div class="figma-section-label">Offices</div>
      <div class="figma-category-grid">
        <button class="figma-category-card" data-office="Registrar">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/><line x1="8" y1="18" x2="12" y2="18"/></svg></div>
          <h3>Registrar</h3>
          <p>Short details</p>
        </button>
        <button class="figma-category-card" data-office="Finance">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
          <h3>Finance</h3>
          <p>Short details</p>
        </button>
        <button class="figma-category-card" data-office="SASO">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <h3>SASO</h3>
          <p>Short details</p>
        </button>
        <button class="figma-category-card" data-office="Guidance">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg></div>
          <h3>Guidance</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="Library">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
          <h3>Library</h3>
          <p>Short details</p>
        </button>
        <button class="figma-category-card" data-office="Property Custodian">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.78 7.78 5.5 5.5 0 0 1 7.78-7.78zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></div>
          <h3>Property Custodian</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="Clinic">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
          <h3>Clinic</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="ITCD">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
          <h3>ITCD</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="Human Resources">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
          <h3>Human Resources</h3>
          <p>Ask concern directly to ai.</p>
        </button>
      </div>

      <div class="figma-section-label">Departments</div>
      <div class="figma-category-grid">
        <button class="figma-category-card" data-office="CCS">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></div>
          <h3>CCS</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="CBE">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
          <h3>CBE</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="CTE">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
          <h3>CTE</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="CCJE">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          <h3>CCJE</h3>
          <p>Ask concern directly to ai.</p>
        </button>
        <button class="figma-category-card" data-office="PSYCH">
          <div class="figma-icon"><svg viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 2.38 1.19 4.47 3 5.74V17a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-2.26c1.81-1.27 3-3.36 3-5.74a7 7 0 0 0-7-7z"/><line x1="9" y1="21" x2="15" y2="21"/></svg></div>
          <h3>PSYCH</h3>
          <p>Ask concern directly to ai.</p>
        </button>
      </div>
    </div>

    <div id="dashConcerns" style="display:none;">
      <div style="margin-bottom:22px;">
        <h1 style="font-size:24px;font-weight:800;margin:0 0 4px;">My Concerns</h1>
        <p style="margin:0;color:var(--muted);font-size:13.5px;">Everything you've submitted or asked Ben about, including replies from staff.</p>
      </div>
      <div class="faq-card">
        <div id="concernListFull"></div>
      </div>
    </div>
  </main>

  <!-- Right Sidebar: Chat History -->
  <aside class="chat-history-sidebar">
    <h2 class="chat-hist-title">Chat history</h2>
    <div class="chat-hist-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" id="chatHistSearch" placeholder="Search conversations...">
    </div>
    <div class="chat-hist-list" id="chatHistList">
      <!-- JS will populate this from concerns data -->
    </div>
    <button class="chat-hist-new" id="chatHistNewBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Create new chat
    </button>
  </aside>

</div>

<script>
/* ============================================================
   HELPDESKCRMC — Ben conversation flow
   Dashboard is home. Tapping "I Have a Concern" opens Ben:
   category picker -> interactive conversation -> escalate to inquiry
   ============================================================ */

/* ---------- Security helpers ---------- */
function escapeHtml(str) {
  if (typeof str !== 'string') return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

const knowledgeBase = {
  General: [
    { topic:"CRMC Location", keywords:["where is crmc","where is crmci","crmc located","crmc location","crmc address","school address","main campus","college campus"],
      content:"CRMC's main/college campus is located at San Vicente Street, Bogo City, Cebu 6000, Philippines." }
  ],
  Registrar: [
    { topic:"Certificate of Good Moral", keywords:["good moral","certificate","character"],
      content:"A Certificate of Good Moral Character is released 2 to 3 working days after request. Bring your validated ID to the Registrar's window to claim it." },
    { topic:"Honorable Dismissal", keywords:["honorable","dismissal","transfer","clearance form"],
      content:"An Honorable Dismissal needs a fully signed clearance form and settlement of any outstanding balance. Processing usually takes 5 working days once submitted." },
    { topic:"Enrollment Deadline", keywords:["enrollment","deadline","enroll","late enrol","schedule"],
      content:"Regular enrollment closes two weeks before classes start. Late enrollment is allowed within the first week of classes, with a late fee." }
  ],
  Finance: [
    { topic:"Refund", keywords:["refund","overpayment","reimburse","money back"],
      content:"Overpayment refunds are processed within 10 working days. Bring your official receipt and a valid ID to the Finance window to file a request." },
    { topic:"Tuition Balance", keywords:["balance","tuition","unpaid","how much","fee"],
      content:"You can check your current balance at the Finance window or on your enrollment assessment slip. Partial payment plans are available on request." }
  ],
  SASO: [
    { topic:"Missing Grade", keywords:["missing","grade","nstp","incomplete"],
      content:"A missing grade is usually caused by an unencoded requirement. Please coordinate with your subject adviser first, then follow up with SASO if it isn't corrected within a week." },
    { topic:"Scholarship", keywords:["scholarship","financial","assistance","grant"],
      content:"Scholarship applications open at the start of each semester. Requirements include a certificate of good moral character and updated grades." }
  ],
  Library: [
    { topic:"Clearance Hold", keywords:["clearance","hold","book","fine"],
      content:"A library clearance hold usually means an unreturned book or an unpaid fine under your name. Settle it at the circulation desk before requesting clearance again." }
  ],
  Guidance: [
    { topic:"Counseling", keywords:["counseling","stress","appointment","guidance"],
      content:"You can request a counseling appointment directly at the Guidance Office, or ask me to forward your request so a counselor can reach out to you." }
  ],
  Clinic: [
    { topic:"Medical Certificate", keywords:["medical","certificate","sick","excuse"],
      content:"Medical certificates for absences need a same-day or next-day visit to the Clinic. Walk-ins are accepted during clinic hours." }
  ],
  CCS: [
    { topic:"OJT / Practicum", keywords:["ojt","practicum","internship","deployment"],
      content:"OJT and practicum concerns are coordinated through the CCS OJT coordinator. Bring your endorsement letter when you visit the department office." },
    { topic:"Grade Concern", keywords:["grade","incorrect grade","re-check","recompute"],
      content:"Coordinate with your instructor first for grade concerns. If it isn't resolved, the CCS department office can help you file a formal request." }
  ],
  CCJE: [
    { topic:"Field Training (FTEP)", keywords:["field training","ftep","practicum","training log"],
      content:"Field Training Exposure Program concerns go through the CCJE department office. Bring your training log and adviser's endorsement." },
    { topic:"Board Exam / Review", keywords:["board exam","review","licensure"],
      content:"Licensure exam review schedules and requirements are posted on the CCJE bulletin board and announced by your adviser." }
  ],
  PSYCH: [
    { topic:"Practicum / Internship", keywords:["practicum","internship","clinical placement"],
      content:"Psychology practicum placements are coordinated by the department's practicum supervisor. Bring your endorsement form to the department office." },
    { topic:"Thesis / Research Adviser", keywords:["thesis","research","adviser"],
      content:"Raise thesis and research concerns with your adviser first, then escalate to the department office if it isn't resolved." }
  ],
  CBE: [
    { topic:"Grade Concern", keywords:["grade","incorrect grade","re-check","recompute"],
      content:"Coordinate with your instructor first for grade concerns. If it isn't resolved, the CBE department office can help you file a formal request." },
    { topic:"Business Practicum", keywords:["practicum","ojt","internship"],
      content:"Business practicum placement concerns are coordinated through the CBE department office." }
  ],
  CTE: [
    { topic:"Practice Teaching", keywords:["practice teaching","student teaching","deployment"],
      content:"Practice Teaching deployment and requirements are coordinated through the CTE Field Study office." },
    { topic:"LET Review", keywords:["let","licensure","board exam","review"],
      content:"LET review schedules and requirements are announced by the CTE department office and your adviser." }
  ],
  "Property Custodian": [
    { topic:"Facility & Equipment Request", keywords:["facility","equipment","borrow","room","property"],
      content:"For facility or equipment requests, submit a requisition form to the Property Custodian Office at least 3 days prior." }
  ],
  ITCD: [
    { topic:"Account & Portal Support", keywords:["account","password","wifi","portal","email","login"],
      content:"For portal password resets or institutional email assistance, visit the ITCD office with your valid Student ID." }
  ],
  "Human Resources": [
    { topic:"Employment & Staff Inquiries", keywords:["hr","human resources","staff","faculty","employment"],
      content:"For HR-related inquiries, faculty concerns, or employment verification, please visit the HR Office." }
  ]
};

const OFFICES = [
  { key:"Registrar", label:"Registrar", mode:"ask",
    icon:'<rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M7 15c0-1.4 1-2 2-2s2 .6 2 2M14 9h4M14 13h4"/>' },
  { key:"Finance", label:"Finance", mode:"ask",
    icon:'<circle cx="8" cy="9" r="4"/><circle cx="15" cy="14" r="4"/><path d="M8 9v0M15 14v0"/>' },
  { key:"SASO", label:"SASO", mode:"ask",
    icon:'<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>' },
  { key:"Library", label:"Library", mode:"ask",
    icon:'<path d="M4 5h6a2 2 0 0 1 2 2v13a2 2 0 0 0-2-2H4zM20 5h-6a2 2 0 0 0-2 2v13a2 2 0 0 1 2-2h6z"/>' },
  { key:"Guidance", label:"Guidance", mode:"ask",
    icon:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>' },
  { key:"Clinic", label:"Clinic", mode:"ask",
    icon:'<path d="M12 3v6M9 6h6M12 13v8M8 21h8"/><rect x="4" y="9" width="16" height="4" rx="1"/>' },
  { key:"Property Custodian", label:"Property Custodian", mode:"ask",
    icon:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M3 9h18"/>' },
  { key:"ITCD", label:"ITCD", mode:"ask",
    icon:'<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>' },
  { key:"Human Resources", label:"Human Resources", mode:"ask",
    icon:'<circle cx="9" cy="7" r="3"/><circle cx="15" cy="7" r="3"/><path d="M3 21v-2a4 4 0 0 1 4-4h2M15 15h2a4 4 0 0 1 4 4v2"/>' }
];

const ACADEMIC_DEPARTMENTS = [
  { key:"CCS", label:"CCS", mode:"ask",
    icon:'<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>' },
  { key:"CCJE", label:"CCJE", mode:"ask",
    icon:'<path d="M12 3l8 4-8 4-8-4 8-4z"/><path d="M4 11v4c0 1.5 3.5 3 8 3s8-1.5 8-3v-4"/>' },
  { key:"PSYCH", label:"Psych", mode:"ask",
    icon:'<circle cx="12" cy="12" r="9"/><path d="M9 10c0-2 1.5-3 3-3s3 1 3 3-1.5 2.5-1.5 4.5M12 17h.01"/>' },
  { key:"CBE", label:"CBE", mode:"ask",
    icon:'<rect x="4" y="10" width="4" height="10"/><rect x="10" y="6" width="4" height="14"/><rect x="16" y="13" width="4" height="7"/>' },
  { key:"CTE", label:"CTE", mode:"ask",
    icon:'<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12.5V17c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/>' }
];

const CATEGORIES = [
  { key:"General", label:"General Enquiry", mode:"general",
    icon:'<path d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87M15 11a3 3 0 1 0-6 0 3 3 0 0 0 6 0zM12 14a4 4 0 0 0-4 4v0M17 8a3 3 0 1 0 0-6"/>' },
  ...OFFICES,
  { key:"DeptHub", label:"Department", mode:"dept-hub",
    icon:'<path d="M4 21V7l8-4 8 4v14"/><path d="M9 21v-6h6v6M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/>' }
];

const noMatchVariants = ["I don't have a clear answer for that yet.", "I'm not fully sure about that one, so I don't want to guess."];
const resolvedThanksVariants = ["Glad that helped!", "Happy to help!"];

const BEN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="14" rx="7" ry="6"/><path d="M6 8l2-4 2 5M18 8l-2-4-2 5"/><circle cx="9.5" cy="13" r="1"/><circle cx="14.5" cy="13" r="1"/><path d="M11 16q1 1 2 0"/></svg>';

/* ---------- PHP concerns data loaded from backend ---------- */
let concerns = <?= json_encode(array_map(function($inq) {
    return [
        'subject' => $inq['subject'] ?? mb_substr($inq['description'], 0, 60),
        'office' => $inq['office_name'] ?? 'General',
        'status' => strtolower(str_replace(' ', '', $inq['status'])),
        'date' => date('M j, Y', strtotime($inq['created_at'])),
        'reply' => null
    ];
}, $inquiries)) ?>;

function statusLabel(s){ return s === 'pending' ? 'Pending' : s === 'progress' ? 'In Progress' : 'Resolved'; }

function buildConcernRow(item){
  const wrap = document.createElement('div');
  const row = document.createElement('div');
  row.className = 'faq-row';
  row.innerHTML = `
    <div class="left">
      <div class="subject">${escapeHtml(item.subject)}</div>
      <div class="meta">${escapeHtml(item.office)} · Submitted ${escapeHtml(item.date)}</div>
    </div>
    <div class="right">
      <span class="tag ${escapeHtml(item.status)}">${statusLabel(item.status)}</span>
      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
    </div>`;
  const detail = document.createElement('div');
  detail.className = 'concern-detail';
  detail.style.display = 'none';
  detail.innerHTML = item.reply
    ? `<div class="reply-box"><div class="reply-head">${escapeHtml(item.reply.from)} · ${escapeHtml(item.reply.date)}</div><div class="reply-msg">${escapeHtml(item.reply.message)}</div></div>`
    : `<div class="reply-empty">No reply yet — staff at ${escapeHtml(item.office)} typically respond within 2–3 working days.</div>`;
  row.addEventListener('click', () => {
    const open = detail.style.display === 'block';
    detail.style.display = open ? 'none' : 'block';
    row.classList.toggle('open', !open);
  });
  wrap.appendChild(row);
  wrap.appendChild(detail);
  return wrap;
}

function renderConcernList(containerId, limit){
  const container = document.getElementById(containerId);
  if(!container) return;
  container.innerHTML = '';
  const list = limit ? concerns.slice(0, limit) : concerns;
  if(list.length === 0){
    container.innerHTML = '<div class="reply-empty">No concerns submitted yet.</div>';
    return;
  }
  list.forEach(item => container.appendChild(buildConcernRow(item)));
}
function renderAllConcernLists(){
  renderConcernList('concernList', 4);
  renderConcernList('concernListFull');
}

const bfCatRow = document.getElementById('bfCatRow');
const bfScrollThumb = document.getElementById('bfScrollThumb');
const bfThread = document.getElementById('bfThread');
const bfInput = document.getElementById('bfInput');
const bfSend = document.getElementById('bfSend');
const bfFileInput = document.getElementById('bfFileInput');
const bfAttachBtn = document.getElementById('bfAttachBtn');
const bfAttachChip = document.getElementById('bfAttachChip');
const bfAttachName = document.getElementById('bfAttachName');
const bfAttachSize = document.getElementById('bfAttachSize');
const bfAttachRemove = document.getElementById('bfAttachRemove');
let pendingAttachments = [];
const bfOfficePill = document.getElementById('bfOfficePill');
let currentCategory = null;
let conversationHistory = [];
const benFlow = document.getElementById('benFlow');
const benFlowHome = benFlow.parentElement;
const dashboardMain = document.querySelector('#dashboardView > main');

function showEmbeddedConversation(){
  if (!dashboardMain || benFlow.parentElement === dashboardMain) return;
  dashboardMain.appendChild(benFlow);
  dashboardMain.classList.add('conversation-active');
  benFlow.classList.add('embedded');
  benFlow.style.display = 'flex';
}

function pick(arr){ return arr[Math.floor(Math.random()*arr.length)]; }
function scrollBottom(){ bfThread.scrollTop = bfThread.scrollHeight; }
function truncate(s, n){ n = n || 60; return s.length > n ? s.slice(0, n).trim() + '…' : s; }

/* ---------- step 1: category cards ---------- */
const bfScrollTrack = document.getElementById('bfScrollTrack');
const TRACK_WIDTH = 220;
const MIN_THUMB = 30;

function renderCategories(){
  document.getElementById('bfChooseLabel').textContent = 'PLEASE CHOOSE WHAT YOU NEED HELP WITH';
  document.getElementById('bfBackToCategories').style.display = 'none';
  bfCatRow.innerHTML = '';
  CATEGORIES.forEach(cat => {
    const card = document.createElement('button');
    card.className = 'bf-cat-card';
    card.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${cat.icon}</svg><div class="lbl">${escapeHtml(cat.label)}</div>`;
    card.addEventListener('click', () => openCategory(cat));
    bfCatRow.appendChild(card);
  });
  bfCatRow.scrollLeft = 0;
  requestAnimationFrame(updateScrollThumb);
}

function showDepartmentList(){
  document.getElementById('bfChooseLabel').textContent = 'PLEASE CHOOSE A DEPARTMENT';
  document.getElementById('bfBackToCategories').style.display = 'inline-flex';
  bfCatRow.innerHTML = '';
  ACADEMIC_DEPARTMENTS.forEach(dept => {
    const card = document.createElement('button');
    card.className = 'bf-cat-card';
    card.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${dept.icon}</svg><div class="lbl">${escapeHtml(dept.label)}</div>`;
    card.addEventListener('click', () => openCategory(dept));
    bfCatRow.appendChild(card);
  });
  bfCatRow.scrollLeft = 0;
  requestAnimationFrame(updateScrollThumb);
}

const bfBackToCategories = document.getElementById('bfBackToCategories');
if (bfBackToCategories) bfBackToCategories.addEventListener('click', renderCategories);

function updateScrollThumb(){
  const max = bfCatRow.scrollWidth - bfCatRow.clientWidth;
  if(max <= 1){
    bfScrollTrack.classList.remove('show');
    return;
  }
  bfScrollTrack.classList.add('show');
  const thumbWidth = Math.max(MIN_THUMB, TRACK_WIDTH * (bfCatRow.clientWidth / bfCatRow.scrollWidth));
  const ratio = bfCatRow.scrollLeft / max;
  bfScrollThumb.style.width = thumbWidth + 'px';
  bfScrollThumb.style.left = (ratio * (TRACK_WIDTH - thumbWidth)) + 'px';
}

bfCatRow.addEventListener('scroll', updateScrollThumb);
window.addEventListener('resize', updateScrollThumb);

let dragging = false, dragStartX = 0, dragStartScroll = 0;
function startDrag(clientX){
  dragging = true;
  dragStartX = clientX;
  dragStartScroll = bfCatRow.scrollLeft;
}
function moveDrag(clientX){
  if(!dragging) return;
  const max = bfCatRow.scrollWidth - bfCatRow.clientWidth;
  const thumbWidth = Math.max(MIN_THUMB, TRACK_WIDTH * (bfCatRow.clientWidth / bfCatRow.scrollWidth));
  const trackRange = TRACK_WIDTH - thumbWidth;
  if(trackRange <= 0) return;
  const deltaX = clientX - dragStartX;
  const scrollDelta = (deltaX / trackRange) * max;
  bfCatRow.scrollLeft = dragStartScroll + scrollDelta;
}
function endDrag(){ dragging = false; }

bfScrollThumb.addEventListener('mousedown', e => { e.preventDefault(); startDrag(e.clientX); });
window.addEventListener('mousemove', e => moveDrag(e.clientX));
window.addEventListener('mouseup', endDrag);

bfScrollThumb.addEventListener('touchstart', e => startDrag(e.touches[0].clientX), { passive:true });
window.addEventListener('touchmove', e => { if(dragging) moveDrag(e.touches[0].clientX); }, { passive:true });
window.addEventListener('touchend', endDrag);

bfScrollTrack.addEventListener('click', e => {
  if(e.target === bfScrollThumb) return;
  const rect = bfScrollTrack.getBoundingClientRect();
  const clickRatio = (e.clientX - rect.left) / rect.width;
  const max = bfCatRow.scrollWidth - bfCatRow.clientWidth;
  bfCatRow.scrollLeft = clickRatio * max;
});

/* ---------- rendering helpers for step 2 ---------- */
function parseMarkdown(text){
  if(!text) return '';
  // Escape HTML first to prevent XSS
  let safe = escapeHtml(text);
  // Parse markdown bold: **text** -> <strong>text</strong>
  safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
  // Parse markdown italic: *text* -> <em>text</em>
  safe = safe.replace(/\*(.+?)\*/g, '<em>$1</em>');
  // Parse markdown links: [text](url) -> <a>text</a>
  safe = safe.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
  // Parse line breaks: \n -> <br>
  safe = safe.replace(/\n/g, '<br>');
  return safe;
}

function addBenMessage(question, sub, chips, answerStyle){
  // Track in conversation history for AI context
  if(question && typeof question === 'string') {
    conversationHistory.push({ role: 'assistant', message: question });
  }

  const row = document.createElement('div');
  row.className = 'bf-row';
  const card = document.createElement('div');
  card.className = 'bf-msg-card' + (answerStyle ? ' bf-answer' : '');
  card.innerHTML = `<div class="q">${parseMarkdown(question)}</div>` + (sub ? `<div class="sub">${escapeHtml(sub)}</div>` : '');
  if(chips && chips.length){
    const grid = document.createElement('div');
    grid.className = 'bf-chip-grid';
    chips.forEach(c => {
      const btn = document.createElement('button');
      btn.className = 'bf-chip' + (c.cls ? ' '+c.cls : '');
      btn.textContent = c.label;
      btn.addEventListener('click', () => { grid.remove(); addStudentBubble(c.label); c.onClick(); });
      grid.appendChild(btn);
    });
    card.appendChild(grid);
  }
  row.innerHTML = `<div class="bf-ben-ic"><img src="assets/images/ben_interactions%20vector/hi_bot.png" alt="BenAI"></div>`;
  row.appendChild(card);
  bfThread.appendChild(row);
  scrollBottom();
}
function addStudentBubble(text, attachHTML){
  // Track in conversation history for AI context
  if(text) {
    conversationHistory.push({ role: 'student', message: text });
  }

  const row = document.createElement('div');
  row.className = 'bf-row student';
  const bubble = document.createElement('div');
  bubble.className = 'bf-student-bubble';
  bubble.textContent = text;
  if(attachHTML){
    bubble.insertAdjacentHTML('beforeend', attachHTML);
  }
  row.appendChild(bubble);
  bfThread.appendChild(row);
  scrollBottom();
}
function disableInput(placeholder){
  bfInput.disabled = true; bfInput.value = '';
  bfInput.placeholder = placeholder || 'Choose an option above...';
  bfSend.disabled = true; bfSend.classList.remove('active');
  bfAttachBtn.disabled = true;
  clearAttachments();
}
function enableInput(placeholder){
  bfInput.disabled = false;
  bfInput.placeholder = placeholder;
  bfSend.disabled = false; bfSend.classList.add('active');
  bfAttachBtn.disabled = false;
  bfInput.focus();
}

/* ---------- file attachment ---------- */
function formatFileSize(bytes){
  if(bytes < 1024) return bytes + ' B';
  if(bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/(1024*1024)).toFixed(1) + ' MB';
}
function renderAttachChip(){
  if(!pendingAttachments.length){
    bfAttachChip.classList.remove('show');
    return;
  }
  bfAttachChip.classList.add('show');
  if(pendingAttachments.length === 1){
    bfAttachName.textContent = pendingAttachments[0].name;
    bfAttachSize.textContent = formatFileSize(pendingAttachments[0].size);
  } else {
    bfAttachName.textContent = pendingAttachments.length + ' files attached';
    const total = pendingAttachments.reduce((sum, f) => sum + f.size, 0);
    bfAttachSize.textContent = formatFileSize(total);
  }
}
function clearAttachments(){
  pendingAttachments = [];
  bfFileInput.value = '';
  renderAttachChip();
}
bfAttachBtn.addEventListener('click', () => { if(!bfAttachBtn.disabled) bfFileInput.click(); });
bfFileInput.addEventListener('change', () => {
  pendingAttachments = Array.from(bfFileInput.files || []);
  renderAttachChip();
});
bfAttachRemove.addEventListener('click', clearAttachments);
function attachmentPillsHTML(){
  if(!pendingAttachments.length) return '';
  return pendingAttachments.map(f =>
    `<div class="attach-pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5.5 5.5 0 0 1-7.78-7.78l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48"/></svg>${f.name}</div>`
  ).join('');
}

function matchKB(office, text){
  const lower = text.toLowerCase();
  const entries = knowledgeBase[office] || [];
  let best = null, bestScore = 0;
  entries.forEach(entry => {
    let score = 0;
    entry.keywords.forEach(k => { if(lower.includes(k)) score += 1; });
    if(score > bestScore){ bestScore = score; best = entry; }
  });
  return bestScore > 0 ? best : null;
}
function matchAnyOffice(text){
  const lower = text.toLowerCase();
  let best = null, bestOffice = null, bestScore = 0;
  Object.keys(knowledgeBase).forEach(office => {
    knowledgeBase[office].forEach(entry => {
      let score = 0;
      entry.keywords.forEach(k => { if(lower.includes(k)) score += 1; });
      if(score > bestScore){ bestScore = score; best = entry; bestOffice = office; }
    });
  });
  return bestScore > 0 ? { office:bestOffice, entry:best } : null;
}

/* ---------- flow ---------- */
function openCategory(cat){
  if(cat.mode === 'dept-hub'){
    showDepartmentList();
    return;
  }

  showEmbeddedConversation();
  currentCategory = cat;
  conversationHistory = []; // Reset history for new category conversation
  document.getElementById('bfStep1').style.display = 'none';
  document.getElementById('bfStep2').style.display = 'flex';
  bfThread.innerHTML = '';
  disableInput();
  bfOfficePill.textContent = cat.label;
  bfOfficePill.style.display = 'none';
  addStudentBubble(cat.label);

  if(cat.mode === 'general'){
    addBenMessage("Sure! Type your concern below — it can be about anything school-related — and I'll do my best to help or point you to the right office.");
    enableInput('Type your concern about anything school-related...');
  } else {
    const topics = (knowledgeBase[cat.key] || []).map(e => e.topic);
    const chips = topics.map(t => ({ label:t, onClick:() => chooseConcernType(t, cat.key) }));
    chips.push({ label:'Something Else', onClick:() => chooseConcernType('Something Else', cat.key) });
    addBenMessage(`Got It, ${cat.label}. What Is Your Concern About?`, `Common concerns in ${cat.label}`, chips);
  }
}

function askGeneralAgain(){
  addBenMessage("Sure, go ahead — type your next concern below.");
  enableInput('Type your concern about anything school-related...');
}

function askWhichDepartmentToForward(text){
  const allTargets = OFFICES.concat(ACADEMIC_DEPARTMENTS);
  const chips = allTargets.map(d => ({ label:d.label, onClick:() => {
    addBenMessage(`Great, let's submit your concern to the **${d.label}** office staff:`);
    renderEscalationCard(d.label, 'General Inquiry', text);
  }}));
  addBenMessage("I couldn't confidently match this to one office — which office or department would you like to submit your concern to?", null, chips);
}

function resetToStep2General(){
  bfThread.innerHTML = '';
  bfOfficePill.textContent = 'General Enquiry';
  askGeneralAgain();
}

function chooseConcernType(label, office){
  if(label === 'Something Else'){
    addBenMessage("No problem — please fill out the concern form below to send your inquiry directly to the office.");
    renderEscalationCard(office, 'General Inquiry', '');
    return;
  }
  const entry = (knowledgeBase[office] || []).find(e => e.topic === label);
  if(!entry) {
    addBenMessage("No problem — please fill out the concern form below to send your inquiry directly to the office.");
    renderEscalationCard(office, 'General Inquiry', '');
    return;
  }
  setTimeout(() => {
    addBenMessage(entry.content, null, null, true);
    setTimeout(() => {
      addBenMessage('Did that answer your concern?', null, [
        { label:'Yes, thanks!', cls:'yes', onClick:() => handleResolved(true, office, label) },
        { label:'Not quite', cls:'no', onClick:() => handleResolved(false, office, label) }
      ]);
    }, 350);
  }, 300);
}

function handleResolved(resolved, office, subjectText){
  const isGeneral = currentCategory && currentCategory.mode === 'general';
  if(resolved){
    addBenMessage(pick(resolvedThanksVariants) + ' Anything else I can help with?', null, [
      { label:'Ask another concern', onClick:() => isGeneral ? askGeneralAgain() : backToConcernType(office) },
      { label:'Switch category', onClick:() => resetToStep1() },
      { label:'Go to Dashboard', cls:'escalate', onClick:() => showDashboard() }
    ]);
  } else {
    addBenMessage(`No problem! Fill out the short form below to submit your concern directly to the **${office || 'Office'}** staff.`);
    renderEscalationCard(office, subjectText || '', '');
  }
}

function backToConcernType(office){
  const topics = (knowledgeBase[office] || []).map(e => e.topic);
  const chips = topics.map(t => ({ label:t, onClick:() => chooseConcernType(t, office) }));
  chips.push({ label:'Something Else', onClick:() => chooseConcernType('Something Else', office) });
  addBenMessage(`Sure — what's your next concern about, still for ${office}?`, null, chips);
}

function resetToStep1(){
  showDashboard();
}

async function sendFreeText(){
  const text = bfInput.value.trim();
  if((!text && !pendingAttachments.length) || bfInput.disabled) return;
  const cat = currentCategory;
  const attachHTML = attachmentPillsHTML();
  addStudentBubble(text || '(Sent an attachment)', attachHTML);
  clearAttachments();
  disableInput();

  // Try AI service first
  try {
    const response = await fetch('api/ai_chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ message: text, history: conversationHistory })
    });

    const result = await response.json();

    // AI service returned a response
    if(result.success && result.matched){
      setTimeout(() => {
        // Just show the AI response directly - no prefix, no forced follow-up
        addBenMessage(result.answer, null, null, true);
        enableInput(bfInput.placeholder || 'Type your concern...');
      }, 300);
      return;
    }

    // AI service found no match - proceed with fallback or escalation
    if(cat.mode === 'general'){
      const found = matchAnyOffice(text);
      if(found){
        setTimeout(() => {
          addBenMessage(found.entry.content, null, null, true);
          enableInput(bfInput.placeholder || 'Type your concern...');
        }, 300);
        return;
      }
      setTimeout(() => {
        addBenMessage(pick(noMatchVariants));
        setTimeout(() => askWhichDepartmentToForward(text), 300);
      }, 300);
    } else {
      setTimeout(() => {
        addBenMessage(pick(noMatchVariants));
        setTimeout(() => offerEscalation(text, cat.key), 300);
      }, 300);
    }

  } catch(error) {
    console.error('AI service error:', error);
    // Fallback to keyword matching if AI service fails
    if(cat.mode === 'general'){
      const found = matchAnyOffice(text);
      if(found){
        setTimeout(() => {
          addBenMessage(found.entry.content, null, null, true);
          enableInput(bfInput.placeholder || 'Type your concern...');
        }, 300);
      } else {
        setTimeout(() => {
          addBenMessage(pick(noMatchVariants));
          setTimeout(() => askWhichDepartmentToForward(text), 300);
        }, 300);
      }
      return;
    }

    const match = matchKB(cat.key, text);
    if(match){
      setTimeout(() => {
        addBenMessage(match.content, null, null, true);
        enableInput(bfInput.placeholder || 'Type your concern...');
      }, 300);
    } else {
      setTimeout(() => {
        addBenMessage(pick(noMatchVariants));
        setTimeout(() => offerEscalation(text, cat.key), 300);
      }, 300);
    }
  }
}

function offerEscalation(text, office){
  addBenMessage(`I wasn't able to find an exact answer for that. You can submit your concern directly to **${escapeHtml(office)}** so staff can assist you:`);
  renderEscalationCard(office, office + " Inquiry", text);
}

function renderEscalationCard(defaultOffice, defaultSubject, defaultDetails){
  const row = document.createElement('div');
  row.className = 'bf-row';

  const allOffices = OFFICES.concat(ACADEMIC_DEPARTMENTS);
  const officeOptions = allOffices.map(o =>
    `<option value="${escapeHtml(o.label)}" ${o.label.toLowerCase() === (defaultOffice||'').toLowerCase() ? 'selected' : ''}>${escapeHtml(o.label)}</option>`
  ).join('');

  row.innerHTML = `
    <div class="bf-ben-ic"><img src="assets/images/ben-avatar.png" alt="Ben"></div>
    <div class="bf-escalation-card">
      <div class="bf-esc-header">
        <div class="bf-esc-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Escalate Concern to Office
        </div>
        <select class="bf-esc-office-select" id="escOfficeSelect">
          ${officeOptions}
        </select>
      </div>

      <div class="bf-esc-group">
        <label class="bf-esc-label">Subject / Title</label>
        <input type="text" class="bf-esc-input" id="escSubjectInput" placeholder="e.g. Inquiry regarding transcript" value="${escapeHtml(defaultSubject || '')}">
      </div>

      <div class="bf-esc-group">
        <label class="bf-esc-label">Detailed Description</label>
        <textarea class="bf-esc-textarea" id="escDetailsInput" placeholder="Describe your concern in detail so the office staff can assist you...">${escapeHtml(defaultDetails || '')}</textarea>
      </div>

      <div class="bf-esc-group">
        <div class="bf-esc-row">
          <div style="flex: 1;">
            <label class="bf-esc-label">Priority Level</label>
            <div class="bf-esc-priority-pills" id="escPriorityPills">
              <button type="button" class="bf-esc-pill-btn active" data-val="normal">Normal</button>
              <button type="button" class="bf-esc-pill-btn" data-val="urgent">Urgent</button>
            </div>
          </div>
          <div>
            <label class="bf-esc-label">Attachment (Optional)</label>
            <label class="bf-esc-file-trigger">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a5.5 5.5 0 0 1-7.78-7.78l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a1.5 1.5 0 0 1-2.12-2.12l8.49-8.48"/></svg>
              Attach File
              <input type="file" id="escFileInput" style="display:none;" multiple>
            </label>
          </div>
        </div>
        <div class="bf-esc-file-preview" id="escFilePreview"></div>
      </div>

      <div class="bf-esc-actions">
        <button type="button" class="bf-esc-submit" id="escSubmitBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Submit Concern
        </button>
        <button type="button" class="bf-esc-cancel" id="escCancelBtn">Cancel</button>
      </div>
    </div>
  `;

  bfThread.appendChild(row);
  scrollThreadBottom();

  // Priority Pills Toggle
  const priorityBtns = row.querySelectorAll('#escPriorityPills .bf-esc-pill-btn');
  let selectedPriority = 'normal';
  priorityBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      priorityBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      selectedPriority = btn.getAttribute('data-val');
    });
  });

  // Attachments Preview
  const fileInput = row.querySelector('#escFileInput');
  const filePreview = row.querySelector('#escFilePreview');
  let attachedFiles = [];

  if (fileInput) {
    fileInput.addEventListener('change', () => {
      attachedFiles = Array.from(fileInput.files || []);
      filePreview.innerHTML = attachedFiles.map((f, idx) =>
        `<div class="bf-esc-file-tag">${escapeHtml(f.name)} <span data-idx="${idx}">&times;</span></div>`
      ).join('');

      filePreview.querySelectorAll('span').forEach(sp => {
        sp.addEventListener('click', () => {
          const i = parseInt(sp.getAttribute('data-idx'), 10);
          attachedFiles.splice(i, 1);
          sp.parentElement.remove();
        });
      });
    });
  }

  // Cancel Handler
  row.querySelector('#escCancelBtn').addEventListener('click', () => {
    row.remove();
    addBenMessage("No problem! Feel free to ask me anything else whenever you're ready.");
  });

  // Submit Handler
  row.querySelector('#escSubmitBtn').addEventListener('click', async () => {
    const targetOffice = row.querySelector('#escOfficeSelect').value;
    const subject = row.querySelector('#escSubjectInput').value.trim();
    const details = row.querySelector('#escDetailsInput').value.trim();
    const submitBtn = row.querySelector('#escSubmitBtn');

    if(!subject && !details){
      alert('Please enter a subject or description for your concern.');
      return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Submitting...';

    const fullMessage = (subject ? `[${subject}] ` : '') + (details || subject) + (selectedPriority === 'urgent' ? ' (URGENT)' : '');

    try {
      await submitInquiry(fullMessage, targetOffice);
      row.remove();
      addBenMessage(`🎉 **Concern Submitted Successfully!**\n\nYour concern has been forwarded directly to the **${escapeHtml(targetOffice)}** staff. You can check for updates under **My Concerns** on your dashboard at any time.`, null, [
        { label: 'View My Concerns', cls: 'escalate', onClick: () => showDashConcerns() },
        { label: 'Ask another question', onClick: () => resetToStep1() }
      ]);
    } catch(e) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Submit Concern';
      alert('Failed to submit concern. Please try again.');
    }
  });
}

async function submitInquiry(subjectText, office){
  try {
    const response = await fetch('api/submit_inquiry.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ message: subjectText, office: office })
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.error || 'Submission failed');
  } catch (e) {
    console.error('submitInquiry error:', e);
    addBenMessage("Sorry, I couldn't forward that. Please try again.", null, [
      { label:'Retry', cls:'escalate', onClick:() => submitInquiry(subjectText, office) },
      { label:'Go to Dashboard', onClick:() => showDashboard() }
    ]);
    return;
  }
  // Update local state for immediate UI feedback
  const today = new Date();
  const dateStr = today.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
  concerns.unshift({ subject:truncate(subjectText), office:office, status:'pending', date:dateStr, reply:null });
  renderAllConcernLists();
  const pendingEl = document.getElementById('statPending');
  pendingEl.textContent = parseInt(pendingEl.textContent, 10) + 1;
}

/* ---------- Dashboard <-> My Concerns page switching ---------- */
function showDashHome(){
  document.getElementById('dashHome').style.display = 'block';
  document.getElementById('dashConcerns').style.display = 'none';
  document.getElementById('navDashboard').classList.add('active');
  document.getElementById('navMyConcerns').classList.remove('active');
}
function showMyConcerns(){
  document.getElementById('dashHome').style.display = 'none';
  document.getElementById('dashConcerns').style.display = 'block';
  document.getElementById('navDashboard').classList.remove('active');
  document.getElementById('navMyConcerns').classList.add('active');
  renderConcernList('concernListFull');
}
const navDashboard = document.getElementById('navDashboard');
const navMyConcerns = document.getElementById('navMyConcerns');
const viewAllLink = document.getElementById('viewAllLink');

if (navDashboard) navDashboard.addEventListener('click', e => { e.preventDefault(); showDashHome(); });
if (navMyConcerns) navMyConcerns.addEventListener('click', e => { e.preventDefault(); showMyConcerns(); });
if (viewAllLink) viewAllLink.addEventListener('click', e => { e.preventDefault(); showMyConcerns(); });

/* ---------- view switching ---------- */
function openBenFlow(){
  showDashboard();
}
function showDashboard(){
  benFlow.classList.remove('embedded');
  dashboardMain.classList.remove('conversation-active');
  benFlowHome.appendChild(benFlow);
  benFlow.style.display = 'none';
}

const bfExitToDash = document.getElementById('bfExitToDash');
const bfBackToStep1 = document.getElementById('bfBackToStep1');
const openBenBtn = document.getElementById('openBenBtn');
const allOfficesBtn = document.getElementById('allOfficesBtn');

if (bfExitToDash) bfExitToDash.addEventListener('click', showDashboard);
if (bfBackToStep1) bfBackToStep1.addEventListener('click', resetToStep1);
if (openBenBtn) openBenBtn.addEventListener('click', openBenFlow);
if (allOfficesBtn) allOfficesBtn.addEventListener('click', openBenFlow);

document.querySelectorAll('.office-shortcut').forEach(button => {
  button.addEventListener('click', () => {
    openBenFlow();
    const office = CATEGORIES.find(item => item.key === button.dataset.office);
    if (office) openCategory(office);
  });
});

document.querySelectorAll('.figma-category-card').forEach(button => {
  button.addEventListener('click', () => {
    const key = button.dataset.office;
    console.log('🔵 Figma card clicked:', key);
    console.log('🔵 CATEGORIES:', CATEGORIES);
    console.log('🔵 ACADEMIC_DEPARTMENTS:', ACADEMIC_DEPARTMENTS);
    openBenFlow();
    const cat = [...CATEGORIES, ...ACADEMIC_DEPARTMENTS].find(c => c.key === key || c.label === key);
    console.log('🔵 Found category:', cat);
    if (cat) {
      console.log('🔵 Calling openCategory with:', cat);
      openCategory(cat);
    } else {
      console.error('❌ Category not found for key:', key);
    }
  });
});

if (bfSend) bfSend.addEventListener('click', sendFreeText);
if (bfInput) bfInput.addEventListener('keydown', e => { if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFreeText(); } });

renderAllConcernLists();

/* ---------- Chat History Sidebar ---------- */
const chatHistColors = ['#557fd3','#d35555','#55b89a','#d3a055','#8b55d3','#d355a8'];
function renderChatHistory(filter = '') {
  const list = document.getElementById('chatHistList');
  if (!list) return;
  const filtered = concerns.filter(c =>
    !filter || c.subject.toLowerCase().includes(filter.toLowerCase()) || c.office.toLowerCase().includes(filter.toLowerCase())
  );
  if (!filtered.length) {
    list.innerHTML = '<div class="chat-hist-empty">No conversations yet</div>';
    return;
  }
  list.innerHTML = filtered.slice(0, 20).map((c, i) => {
    const color = chatHistColors[i % chatHistColors.length];
    const initial = (c.office || 'G')[0].toUpperCase();
    return `<div class="chat-hist-item" data-office="${c.office}">
      <div class="chat-hist-avatar" style="background:${color}">${initial}</div>
      <div class="chat-hist-info">
        <div class="chat-hist-name">${c.office}</div>
        <div class="chat-hist-preview">${c.subject}</div>
      </div>
      <div class="chat-hist-time">${c.date}</div>
    </div>`;
  }).join('');
  list.querySelectorAll('.chat-hist-item').forEach(item => {
    item.addEventListener('click', () => {
      const key = item.dataset.office;
      openBenFlow();
      const cat = [...CATEGORIES, ...ACADEMIC_DEPARTMENTS].find(c => c.key === key);
      if (cat) openCategory(cat);
    });
  });
}
renderChatHistory();
const chatHistSearch = document.getElementById('chatHistSearch');
if (chatHistSearch) chatHistSearch.addEventListener('input', e => renderChatHistory(e.target.value));
const chatHistNewBtn = document.getElementById('chatHistNewBtn');
if (chatHistNewBtn) chatHistNewBtn.addEventListener('click', openBenFlow);
</script>

</body>
</html>
