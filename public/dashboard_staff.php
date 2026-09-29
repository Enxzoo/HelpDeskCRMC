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
require_once __DIR__ . '/../app/helpers/csrf.php';

session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'staff' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$staffId = (int)$_SESSION['user_id'];
$officeId = isset($_SESSION['office_id']) ? (int)$_SESSION['office_id'] : null;

$inquiryModel = new Inquiry();
$officeModel  = new Office();

$officesList  = $officeModel->findAll();
$officeName   = 'General Support';
$officeIcon   = 'G';
if ($officeId) {
    $officeData = $officeModel->findById($officeId);
    if ($officeData) {
        $officeName = $officeData['office_name'];
        $officeIcon = strtoupper(substr($officeData['office_name'], 0, 1));
    }
}

$stats     = $inquiryModel->getStats($officeId);
$inquiries = $inquiryModel->findByOffice($officeId);
$initials  = strtoupper(substr($_SESSION['name'], 0, 1));

// Count by status
$pendingCount = 0;
$inProgressCount = 0;
$resolvedCount = 0;
foreach ($inquiries as $inquiry) {
    if ($inquiry['status'] === 'Pending') $pendingCount++;
    if ($inquiry['status'] === 'In Progress') $inProgressCount++;
    if ($inquiry['status'] === 'Resolved') $resolvedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Staff Portal - HELPDESKCRMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard_staff.css?v=<?= md5_file('assets/css/dashboard_staff.css') ?>">
</head>
<body>

<svg style="display:none" aria-hidden="true">
<defs>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1.2-3.6 4-5.5 7-5.5s5.8 1.9 7 5.5"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3"/><path d="M14 8l4 4-4 4"/><path d="M18 12H9"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.35-4.35"/></symbol>
  <symbol id="i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4 20-7z"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></symbol>
  <symbol id="i-paperclip" viewBox="0 0 24 24"><path d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7"/></symbol>
  <symbol id="i-building" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></symbol>
</defs>
</svg>

<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <img src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
      <div class="brand-name">Helpdesk<span>CRMC</span></div>
    </div>

    <div class="office-card">
      <div class="office-header">Assigned Office</div>
      <div class="office-name"><?= htmlspecialchars($officeName) ?></div>
    </div>

    <div class="nav-section">
      <div class="nav-label">Workspace</div>
      <a class="nav-item active" href="#concerns">
        <svg class="icon"><use href="#i-chat"/></svg>
        Concerns Queue
      </a>
      <a class="nav-item" href="#history">
        <svg class="icon"><use href="#i-clock"/></svg>
        History
      </a>
    </div>

    <div class="sidebar-spacer"></div>

    <div class="staff-section">
      <div class="staff-profile">
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="staff-info">
          <div class="staff-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
          <div class="staff-role">Staff Member</div>
        </div>
      </div>
      <form method="POST" action="login.php?action=logout">
        <button type="submit" class="logout-btn">
          <svg class="icon"><use href="#i-logout"/></svg>
          Logout
        </button>
      </form>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="main">
    <!-- Header -->
    <header class="header">
      <div class="header-top">
        <div class="header-title">
          <h1>Concerns Queue</h1>
          <div class="header-subtitle">Manage and respond to student inquiries</div>
        </div>
        <div class="header-stats">
          <div class="stat-box">
            <div class="stat-value pending"><?= $pendingCount ?></div>
            <div class="stat-label">Pending</div>
          </div>
          <div class="stat-box">
            <div class="stat-value progress"><?= $inProgressCount ?></div>
            <div class="stat-label">In Progress</div>
          </div>
          <div class="stat-box">
            <div class="stat-value resolved"><?= $resolvedCount ?></div>
            <div class="stat-label">Resolved</div>
          </div>
        </div>
      </div>
      <div class="search-bar">
        <svg class="icon"><use href="#i-search"/></svg>
        <input type="text" placeholder="Search by student name or inquiry ID..." id="searchInput">
      </div>
    </header>

    <!-- Content Area -->
    <div class="content-area">
      <!-- Queue Panel -->
      <div class="queue-panel">
        <div class="queue-header">
          <h3 class="queue-title">Active Concerns</h3>
          <div class="filter-group">
            <button class="filter-btn active" data-status="all">All</button>
            <button class="filter-btn" data-status="pending">Pending</button>
            <button class="filter-btn" data-status="in_progress">In Progress</button>
            <button class="filter-btn" data-status="resolved">Resolved</button>
          </div>
        </div>

        <div class="queue-list" id="concernsList">
          <?php if (empty($inquiries)): ?>
          <div class="empty-state">
            <svg class="icon" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
            <p>No concerns in queue</p>
          </div>
          <?php else: ?>
            <?php foreach ($inquiries as $index => $inquiry): ?>
            <div class="concern-card <?= $index === 0 ? 'selected' : '' ?> <?= strpos(strtolower($inquiry['subject']), 'urgent') !== false ? 'urgent' : '' ?>"
                 onclick="selectConcern(this, <?= $inquiry['inquiry_id'] ?>)"
                 data-inquiry-id="<?= $inquiry['inquiry_id'] ?>"
                 data-status="<?= htmlspecialchars(str_replace(' ', '_', strtolower($inquiry['status']))) ?>">
              <span class="concern-status-badge status-<?= str_replace(' ', '-', strtolower($inquiry['status'])) ?>">
                <?= htmlspecialchars($inquiry['status']) ?>
              </span>
              <div class="concern-subject"><?= htmlspecialchars($inquiry['subject']) ?></div>
              <div class="concern-meta">
                <span class="concern-student"><?= htmlspecialchars($inquiry['student_name']) ?></span>
                <span class="concern-time"><?= date('M j, g:i A', strtotime($inquiry['created_at'])) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Detail Panel -->
      <div class="detail-panel" id="detailPanel">
        <?php if (!empty($inquiries)): ?>
        <?php $firstInquiry = $inquiries[0]; ?>
        <div class="detail-header">
          <div class="detail-header-top">
            <div class="detail-title">
              <h2 id="detailTitle"><?= htmlspecialchars($firstInquiry['subject']) ?></h2>
              <div class="detail-meta">
                <div class="meta-item">
                  <svg class="icon"><use href="#i-user"/></svg>
                  <span class="meta-label" id="detailStudent"><?= htmlspecialchars($firstInquiry['student_name']) ?></span>
                </div>
                <div class="meta-item">
                  <span>Student ID: <?= htmlspecialchars($firstInquiry['student_id'] ?? 'N/A') ?></span>
                </div>
                <div class="meta-item">
                  <span id="detailDate">Submitted <?= date('M j, Y \a\t g:i A', strtotime($firstInquiry['created_at'])) ?></span>
                </div>
              </div>
            </div>
            <div class="detail-actions">
              <button class="btn btn-secondary" onclick="reassignConcern()">Reassign</button>
              <button class="btn btn-success" onclick="resolveConcern()">
                <svg class="icon"><use href="#i-check"/></svg>
                Mark Resolved
              </button>
            </div>
          </div>
        </div>

        <div class="thread" id="messageThread">
          <div class="message student">
            <div class="message-header"><?= htmlspecialchars($firstInquiry['student_name']) ?></div>
            <div class="message-bubble" id="originalMessage"><?= nl2br(htmlspecialchars($firstInquiry['description'])) ?></div>
            <div class="message-time"><?= date('M j, g:i A', strtotime($firstInquiry['created_at'])) ?></div>
          </div>
        </div>

        <div class="reply-section">
          <div class="status-row">
            <label for="statusSelect">Status</label>
            <select id="statusSelect">
              <option value="Open" <?= $firstInquiry['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
              <option value="In Progress" <?= $firstInquiry['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="Resolved" <?= $firstInquiry['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
              <option value="On Hold" <?= $firstInquiry['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
            </select>
          </div>
          <form id="replyForm" onsubmit="sendReply(event)">
            <div class="reply-box">
              <textarea class="reply-input" id="replyText" placeholder="Type your response here..." required></textarea>
              <div class="reply-actions">
                <button type="submit" class="btn btn-primary">
                  <svg class="icon"><use href="#i-send"/></svg>
                  Send Reply
                </button>
                <button type="button" class="btn btn-secondary" onclick="attachFile()">
                  <svg class="icon"><use href="#i-paperclip"/></svg>
                  Attach
                </button>
              </div>
            </div>
          </form>
        </div>
        <?php else: ?>
        <div class="no-selection">
          <svg class="icon" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
          <p>Select a concern to view details</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
let currentInquiryId = <?= !empty($inquiries) ? $inquiries[0]['inquiry_id'] : 'null' ?>;
let inquiriesData = <?= json_encode($inquiries) ?>;

function selectConcern(element, inquiryId) {
  document.querySelectorAll('.concern-card').forEach(el => el.classList.remove('selected'));
  element.classList.add('selected');
  currentInquiryId = inquiryId;

  const inquiry = inquiriesData.find(i => i.inquiry_id == inquiryId);
  if (!inquiry) return;

  document.getElementById('detailTitle').textContent = inquiry.subject;
  document.getElementById('detailStudent').textContent = inquiry.student_name;
  document.getElementById('detailDate').textContent = 'Submitted ' + new Date(inquiry.created_at).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: 'numeric', minute: '2-digit'
  }) + ' at ' + new Date(inquiry.created_at).toLocaleTimeString('en-US', {
    hour: 'numeric', minute: '2-digit'
  });
  document.getElementById('originalMessage').innerHTML = inquiry.description.replace(/\n/g, '<br>');

  const statusSelect = document.getElementById('statusSelect');
  statusSelect.value = inquiry.status;

  loadInquiryResponses(inquiryId);
}

async function loadInquiryResponses(inquiryId) {
  try {
    const response = await fetch(`api/get_responses.php?inquiry_id=${inquiryId}`, {
      headers: {
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
      }
    });

    if (!response.ok) throw new Error('Failed to load responses');

    const data = await response.json();
    const thread = document.getElementById('messageThread');
    const originalMessage = thread.children[0];
    thread.innerHTML = '';
    thread.appendChild(originalMessage);

    if (data.responses) {
      data.responses.forEach(resp => {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message staff';
        messageDiv.innerHTML = `
          <div class="message-header">${resp.staff_name}</div>
          <div class="message-bubble">${resp.message.replace(/\n/g, '<br>')}</div>
          <div class="message-time">${new Date(resp.created_at).toLocaleDateString('en-US', {
            month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit'
          })}</div>
        `;
        thread.appendChild(messageDiv);
      });
    }

    thread.scrollTop = thread.scrollHeight;
  } catch (error) {
    console.error('Error loading responses:', error);
  }
}

async function sendReply(event) {
  event.preventDefault();

  if (!currentInquiryId) return;

  const replyText = document.getElementById('replyText');
  const statusSelect = document.getElementById('statusSelect');

  if (!replyText.value.trim()) return;

  try {
    const response = await fetch('api/staff_action.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        action: 'respond',
        inquiry_id: currentInquiryId,
        message: replyText.value.trim(),
        status: statusSelect.value
      })
    });

    if (!response.ok) throw new Error('Failed to send response');

    const data = await response.json();

    if (data.success) {
      const thread = document.getElementById('messageThread');
      const messageDiv = document.createElement('div');
      messageDiv.className = 'message staff';
      messageDiv.innerHTML = `
        <div class="message-header">You</div>
        <div class="message-bubble">${replyText.value.replace(/\n/g, '<br>')}</div>
        <div class="message-time">Just now</div>
      `;
      thread.appendChild(messageDiv);

      replyText.value = '';

      const concernItem = document.querySelector(`[data-inquiry-id="${currentInquiryId}"]`);
      if (concernItem) {
        const statusBadge = concernItem.querySelector('.concern-status-badge');
        statusBadge.textContent = statusSelect.value;
        statusBadge.className = `concern-status-badge status-${statusSelect.value.toLowerCase().replace(' ', '-')}`;
      }

      thread.scrollTop = thread.scrollHeight;
    } else {
      alert('Failed to send response: ' + (data.error || 'Unknown error'));
    }
  } catch (error) {
    console.error('Error sending reply:', error);
    alert('Failed to send response. Please try again.');
  }
}

function resolveConcern() {
  if (!currentInquiryId) return;

  if (confirm('Mark this concern as resolved?')) {
    document.getElementById('statusSelect').value = 'Resolved';
  }
}

function reassignConcern() {
  if (!currentInquiryId) return;
  alert('Reassignment feature coming soon');
}

function attachFile() {
  alert('File attachment feature coming soon');
}

document.querySelectorAll('.filter-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

    const status = this.dataset.status;
    const items = document.querySelectorAll('.concern-card');

    items.forEach(item => {
      if (status === 'all' || item.dataset.status === status) {
        item.style.display = 'block';
      } else {
        item.style.display = 'none';
      }
    });
  });
});

document.getElementById('searchInput').addEventListener('input', function() {
  const query = this.value.toLowerCase();
  const items = document.querySelectorAll('.concern-card');

  items.forEach(item => {
    const title = item.querySelector('.concern-subject').textContent.toLowerCase();
    const student = item.querySelector('.concern-student').textContent.toLowerCase();

    if (title.includes(query) || student.includes(query)) {
      item.style.display = 'block';
    } else {
      item.style.display = 'none';
    }
  });
});
</script>

</body>
</html>