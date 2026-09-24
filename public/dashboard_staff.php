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
if ($officeId) {
    $officeData = $officeModel->findById($officeId);
    if ($officeData) {
        $officeName = $officeData['office_name'];
    }
}

$stats     = $inquiryModel->getStats($officeId);
$inquiries = $inquiryModel->findByOffice($officeId);
$initials  = strtoupper(substr($_SESSION['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Staff Portal - HELPDESKCRMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/design-system.css">
    <link rel="stylesheet" href="assets/css/admin-staff-dashboard.css">
</head>
<body>
<div class="app">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="brand">
            <span class="n">CRMC Helpdesk</span>
            <span class="s">Staff Operations Portal</span>
        </div>

        <div class="nav-group">
            <span class="nav-label">QUEUE MANAGEMENT</span>
            <nav>
                <a href="#" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Inquiry Queue</span>
                </a>
            </nav>
        </div>

        <div class="side-promo">
            <h4><?= htmlspecialchars($officeName) ?></h4>
            <p>Assigned Office Queue. Respond to student concerns step-by-step.</p>
        </div>

        <div style="margin-top:auto;">
            <a href="logout.php" class="nav-item" style="color:#C53030;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main>
        <div class="topbar">
            <div class="who">
                <div class="n">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></div>
                <div class="s">Office Queue Manager — <?= htmlspecialchars($officeName) ?></div>
            </div>
            <div class="top-actions">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Pending</div>
                    <div class="num" id="statPending"><?= $stats['pending'] ?></div>
                </div>
                <div class="stat-icon gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">In Progress</div>
                    <div class="num" id="statProgress"><?= $stats['in_progress'] ?></div>
                </div>
                <div class="stat-icon maroon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Resolved</div>
                    <div class="num" id="statResolved"><?= $stats['resolved'] ?></div>
                </div>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Concerns</div>
                    <div class="num"><?= $stats['total'] ?></div>
                </div>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-bar">
            <div class="search-box">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Search by student name, ID, or subject..." onkeyup="filterQueue()">
            </div>
            <div class="filter-group">
                <select id="statusFilter" onchange="filterQueue()">
                    <option value="all">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Resolved">Resolved</option>
                </select>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-card">
            <div class="data-card-head">
                <h3>Assigned Student Concerns</h3>
                <span class="s" style="font-size:12.5px;color:var(--muted);"><?= count($inquiries) ?> records</span>
            </div>
            <div class="table-wrap">
                <table class="data-table" id="inquiryTable">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Student</th>
                            <th>Subject</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;padding:30px;color:var(--muted);">
                                    No student inquiries in queue for this office.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inquiries as $inq): ?>
                                <?php
                                $sClass = match($inq['status']) {
                                    'Pending'     => 'pending',
                                    'In Progress' => 'progress',
                                    'Resolved'    => 'resolved',
                                    default       => 'pending'
                                };
                                $studentFullName = trim(($inq['first_name'] ?? '') . ' ' . ($inq['last_name'] ?? ''));
                                if (empty($studentFullName)) $studentFullName = 'Student #' . $inq['student_id'];
                                ?>
                                <tr data-status="<?= htmlspecialchars($inq['status']) ?>" data-search="<?= htmlspecialchars(strtolower($studentFullName . ' ' . $inq['subject'] . ' ' . ($inq['student_number'] ?? ''))) ?>">
                                    <td><strong>#<?= $inq['inquiry_id'] ?></strong></td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($studentFullName) ?></div>
                                        <div style="font-size:11.5px;color:var(--muted);"><?= htmlspecialchars($inq['student_number'] ?? $inq['student_email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($inq['subject']) ?></div>
                                        <div style="font-size:11.5px;color:var(--muted);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($inq['description']) ?></div>
                                    </td>
                                    <td style="font-size:12px;color:var(--muted);white-space:nowrap;"><?= date('M j, Y H:i', strtotime($inq['created_at'])) ?></td>
                                    <td><span class="tag <?= $sClass ?>"><?= htmlspecialchars($inq['status']) ?></span></td>
                                    <td>
                                        <button type="button" class="btn-sm btn-outline" onclick="openRespondModal(<?= htmlspecialchars(json_encode($inq)) ?>)">
                                            View & Respond
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Respond Modal -->
<div class="modal-overlay" id="respondModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 id="modalTitle">Inquiry #--</h3>
            <button type="button" class="modal-close" onclick="closeRespondModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <strong id="modalStudentName" style="font-size:15px;">--</strong>
                    <span id="modalStatusTag" class="tag pending">--</span>
                </div>
                <div id="modalMeta" style="font-size:12px;color:var(--muted);">--</div>
            </div>

            <div style="background:#F9F8FA;border:1px solid var(--line);border-radius:12px;padding:14px 16px;margin-bottom:18px;">
                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:4px;">Original Concern</div>
                <div id="modalSubject" style="font-weight:700;font-size:14px;margin-bottom:6px;">--</div>
                <div id="modalDescription" style="font-size:13px;line-height:1.5;color:var(--ink);">--</div>
            </div>

            <!-- Existing Responses -->
            <div style="margin-bottom:18px;">
                <label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;">Staff Response History</label>
                <div id="threadList" class="thread-list">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Add Response Form -->
            <form id="replyForm" onsubmit="submitReply(event)">
                <input type="hidden" id="currentInquiryId" value="">

                <div class="form-group" style="margin-bottom:14px;">
                    <label for="statusSelect" style="font-size:12px;font-weight:700;color:var(--muted);">Update Inquiry Status</label>
                    <select id="statusSelect" style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;font-family:inherit;background:#fff;">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="replyMessage" style="font-size:12px;font-weight:700;color:var(--muted);">Official Response Message</label>
                    <textarea id="replyMessage" rows="3" placeholder="Type official response to the student..." style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;font-family:inherit;outline:none;" required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-sm btn-secondary" onclick="closeRespondModal()">Cancel</button>
            <button type="button" class="btn-sm btn-primary" onclick="submitReply(event)">Send Response</button>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function filterQueue() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#inquiryTable tbody tr');

    rows.forEach(tr => {
        const rowStatus = tr.getAttribute('data-status');
        const searchData = tr.getAttribute('data-search') || '';

        const matchesStatus = (status === 'all' || rowStatus === status);
        const matchesQuery  = (q === '' || searchData.includes(q));

        tr.style.display = (matchesStatus && matchesQuery) ? '' : 'none';
    });
}

let activeInquiry = null;

function openRespondModal(inq) {
    activeInquiry = inq;
    document.getElementById('currentInquiryId').value = inq.inquiry_id;
    document.getElementById('modalTitle').textContent = `Inquiry #${inq.inquiry_id}`;
    document.getElementById('modalStudentName').textContent = `${inq.first_name || ''} ${inq.last_name || ''}`;
    document.getElementById('modalMeta').textContent = `Student ID: ${inq.student_number || 'N/A'} • Submitted: ${inq.created_at}`;
    document.getElementById('modalSubject').textContent = inq.subject;
    document.getElementById('modalDescription').textContent = inq.description;
    document.getElementById('statusSelect').value = inq.status;

    const tag = document.getElementById('modalStatusTag');
    tag.textContent = inq.status;
    tag.className = 'tag ' + (inq.status === 'Pending' ? 'pending' : (inq.status === 'In Progress' ? 'progress' : 'resolved'));

    renderThread(inq.replies || []);

    document.getElementById('respondModal').classList.add('active');
}

function closeRespondModal() {
    document.getElementById('respondModal').classList.remove('active');
    document.getElementById('replyMessage').value = '';
}

function renderThread(replies) {
    const box = document.getElementById('threadList');
    if (!replies || replies.length === 0) {
        box.innerHTML = '<div style="font-size:12px;color:var(--muted);font-style:italic;">No staff replies yet.</div>';
        return;
    }

    box.innerHTML = replies.map(r => `
        <div class="thread-bubble staff">
            <div class="author">${escapeHtml(r.first_name || 'Staff')} ${escapeHtml(r.last_name || '')}</div>
            <div class="msg">${escapeHtml(r.message)}</div>
            <div class="time">${escapeHtml(r.created_at)}</div>
        </div>
    `).join('');
}

function escapeHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

async function submitReply(e) {
    if (e) e.preventDefault();

    const inquiryId = document.getElementById('currentInquiryId').value;
    const message   = document.getElementById('replyMessage').value.trim();
    const newStatus = document.getElementById('statusSelect').value;

    if (!message) {
        alert('Please enter a response message.');
        return;
    }

    try {
        // 1. Post reply
        const res = await fetch('api/staff_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'add_reply',
                inquiry_id: inquiryId,
                message: message
            })
        });

        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Failed to submit response.');
            return;
        }

        // 2. Update status if changed
        if (newStatus !== activeInquiry.status) {
            await fetch('api/staff_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': CSRF_TOKEN
                },
                body: JSON.stringify({
                    action: 'update_status',
                    inquiry_id: inquiryId,
                    status: newStatus
                })
            });
        }

        alert('Response saved successfully.');
        location.reload();
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
    }
}
</script>
</body>
</html>
