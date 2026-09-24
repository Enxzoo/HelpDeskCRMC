<?php
/**
 * dashboard_admin.php
 * HELPDESKCRMC — System Control & User Management Portal
 */

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Office.php';
require_once __DIR__ . '/../app/models/Inquiry.php';
require_once __DIR__ . '/../app/helpers/csrf.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$userModel   = new User();
$officeModel = new Office();
$inquiryModel= new Inquiry();

$usersList   = $userModel->findAll();
$officesList = $officeModel->findAll();
$userStats   = $userModel->getStats();
$inqStats    = $inquiryModel->getStats();

$initials = strtoupper(substr($_SESSION['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Admin Portal - HELPDESKCRMC</title>
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
            <span class="s">System Administration</span>
        </div>

        <div class="nav-group">
            <span class="nav-label">CONTROL PANEL</span>
            <nav>
                <a href="#users" class="nav-item active" id="navUsers" onclick="switchTab('users')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>User Management</span>
                </a>
                <a href="#offices" class="nav-item" id="navOffices" onclick="switchTab('offices')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M9 8h1"/><path d="M9 12h1"/><path d="M9 16h1"/><path d="M14 8h1"/><path d="M14 12h1"/><path d="M14 16h1"/><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/></svg>
                    <span>Offices & Services</span>
                </a>
            </nav>
        </div>

        <div class="side-promo">
            <h4>MIS Control Center</h4>
            <p>Manage portal access, staff office assignments, and system users.</p>
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
                <div class="n">Administrator Portal</div>
                <div class="s">Signed in as <?= htmlspecialchars($_SESSION['name']) ?> (<?= htmlspecialchars($_SESSION['email']) ?>)</div>
            </div>
            <div class="top-actions">
                <button type="button" class="btn-sm btn-primary" onclick="openCreateUserModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Create User</span>
                </button>
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
        </div>

        <!-- Overview Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Users</div>
                    <div class="num"><?= $userStats['total'] ?></div>
                </div>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Students</div>
                    <div class="num"><?= $userStats['students'] ?></div>
                </div>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Staff Members</div>
                    <div class="num"><?= $userStats['staff'] ?></div>
                </div>
                <div class="stat-icon gold">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <div class="lbl">Total Inquiries</div>
                    <div class="num"><?= $inqStats['total'] ?></div>
                </div>
                <div class="stat-icon maroon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
            </div>
        </div>

        <!-- TAB 1: User Management -->
        <div id="tabUsers">
            <div class="filter-bar">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="userSearchInput" placeholder="Search user by name, email, or student #..." onkeyup="filterUsers()">
                </div>
                <div class="filter-group">
                    <select id="roleFilter" onchange="filterUsers()">
                        <option value="all">All Roles</option>
                        <option value="student">Students</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admins</option>
                    </select>
                </div>
            </div>

            <div class="data-card">
                <div class="data-card-head">
                    <h3>Registered Accounts</h3>
                    <span class="s" style="font-size:12.5px;color:var(--muted);"><?= count($usersList) ?> users registered</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Assigned Office / ID</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $u): ?>
                                <?php
                                $rClass = strtolower($u['role']);
                                $fullName = trim($u['first_name'] . ' ' . $u['last_name']);
                                $isActive = (int)($u['is_active'] ?? 1) === 1;
                                ?>
                                <tr data-role="<?= htmlspecialchars($rClass) ?>" data-search="<?= htmlspecialchars(strtolower($fullName . ' ' . $u['email'] . ' ' . ($u['student_number'] ?? '') . ' ' . ($u['office_name'] ?? ''))) ?>">
                                    <td>
                                        <div style="font-weight:700;"><?= htmlspecialchars($fullName) ?></div>
                                        <div style="font-size:11.5px;color:var(--muted);">Registered: <?= date('M j, Y', strtotime($u['created_at'])) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><span class="role-badge <?= $rClass ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span></td>
                                    <td>
                                        <?php if ($u['role'] === 'staff'): ?>
                                            <strong><?= htmlspecialchars($u['office_name'] ?? 'Unassigned') ?></strong>
                                        <?php elseif ($u['role'] === 'student'): ?>
                                            <span><?= htmlspecialchars($u['student_number'] ?? 'N/A') ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--muted);">System Admin</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="tag resolved">Active</span>
                                        <?php else: ?>
                                            <span class="tag progress" style="background:#FEE2E2;color:#991B1B;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn-sm <?= $isActive ? 'btn-outline' : 'btn-primary' ?>" onclick="toggleUserStatus(<?= $u['user_id'] ?>, <?= $isActive ? 'false' : 'true' ?>)">
                                            <?= $isActive ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: Office Directory -->
        <div id="tabOffices" style="display:none;">
            <div class="data-card">
                <div class="data-card-head">
                    <h3>School Offices Directory</h3>
                    <span class="s" style="font-size:12.5px;color:var(--muted);"><?= count($officesList) ?> active departments</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Office Name</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($officesList as $off): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($off['office_name']) ?></strong></td>
                                    <td><span class="role-badge staff"><?= htmlspecialchars($off['office_code'] ?? 'DEPT') ?></span></td>
                                    <td style="color:var(--muted);"><?= htmlspecialchars($off['description'] ?? 'School department office') ?></td>
                                    <td><span class="tag resolved">Active</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Create New Portal User</h3>
            <button type="button" class="modal-close" onclick="closeCreateUserModal()">&times;</button>
        </div>
        <form id="createUserForm" onsubmit="submitCreateUser(event)">
            <div class="modal-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div class="form-group">
                        <label for="firstName" style="font-size:12px;font-weight:700;color:var(--muted);">First Name</label>
                        <input type="text" id="firstName" required style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;">
                    </div>
                    <div class="form-group">
                        <label for="lastName" style="font-size:12px;font-weight:700;color:var(--muted);">Last Name</label>
                        <input type="text" id="lastName" required style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="userEmail" style="font-size:12px;font-weight:700;color:var(--muted);">Email Address</label>
                    <input type="email" id="userEmail" placeholder="user@crmc.edu.ph" required style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="userPassword" style="font-size:12px;font-weight:700;color:var(--muted);">Password</label>
                    <input type="password" id="userPassword" placeholder="••••••••" required style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;">
                </div>

                <div class="form-group" style="margin-bottom:12px;">
                    <label for="userRole" style="font-size:12px;font-weight:700;color:var(--muted);">Account Role</label>
                    <select id="userRole" onchange="toggleRoleFields()" style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;background:#fff;">
                        <option value="student">Student</option>
                        <option value="staff">Staff Member</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div class="form-group" id="officeGroup" style="margin-bottom:12px;display:none;">
                    <label for="officeId" style="font-size:12px;font-weight:700;color:var(--muted);">Assigned Office</label>
                    <select id="officeId" style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;background:#fff;">
                        <option value="">Select Office...</option>
                        <?php foreach ($officesList as $o): ?>
                            <option value="<?= $o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="studentNumGroup" style="margin-bottom:12px;">
                    <label for="studentNum" style="font-size:12px;font-weight:700;color:var(--muted);">Student Number</label>
                    <input type="text" id="studentNum" placeholder="2026-00123" style="width:100%;border:1px solid var(--line);border-radius:10px;padding:10px;font-size:13px;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-sm btn-secondary" onclick="closeCreateUserModal()">Cancel</button>
                <button type="submit" class="btn-sm btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function switchTab(tab) {
    document.getElementById('tabUsers').style.display   = (tab === 'users') ? 'block' : 'none';
    document.getElementById('tabOffices').style.display = (tab === 'offices') ? 'block' : 'none';

    document.getElementById('navUsers').classList.toggle('active', tab === 'users');
    document.getElementById('navOffices').classList.toggle('active', tab === 'offices');
}

function filterUsers() {
    const q = document.getElementById('userSearchInput').value.toLowerCase();
    const role = document.getElementById('roleFilter').value;
    const rows = document.querySelectorAll('#usersTable tbody tr');

    rows.forEach(tr => {
        const rowRole = tr.getAttribute('data-role');
        const searchData = tr.getAttribute('data-search') || '';

        const matchesRole  = (role === 'all' || rowRole === role);
        const matchesQuery = (q === '' || searchData.includes(q));

        tr.style.display = (matchesRole && matchesQuery) ? '' : 'none';
    });
}

function openCreateUserModal() {
    document.getElementById('createUserModal').classList.add('active');
}

function closeCreateUserModal() {
    document.getElementById('createUserModal').classList.remove('active');
    document.getElementById('createUserForm').reset();
    toggleRoleFields();
}

function toggleRoleFields() {
    const role = document.getElementById('userRole').value;
    document.getElementById('officeGroup').style.display     = (role === 'staff') ? 'block' : 'none';
    document.getElementById('studentNumGroup').style.display = (role === 'student') ? 'block' : 'none';
}

async function submitCreateUser(e) {
    e.preventDefault();

    const payload = {
        action: 'create_user',
        first_name: document.getElementById('firstName').value.trim(),
        last_name:  document.getElementById('lastName').value.trim(),
        email:      document.getElementById('userEmail').value.trim(),
        password:   document.getElementById('userPassword').value,
        role:       document.getElementById('userRole').value,
        office_id:  document.getElementById('officeId').value,
        student_number: document.getElementById('studentNum').value.trim()
    };

    try {
        const res = await fetch('api/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (!data.success) {
            alert(data.error || 'Failed to create user account.');
            return;
        }

        alert('User account created successfully!');
        location.reload();
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
    }
}

async function toggleUserStatus(userId, activate) {
    if (!confirm(`Are you sure you want to ${activate ? 'enable' : 'disable'} this user account?`)) {
        return;
    }

    try {
        const res = await fetch('api/admin_action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'toggle_user_status',
                user_id: userId
            })
        });

        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Operation failed.');
        }
    } catch (err) {
        console.error(err);
        alert('Network error. Please try again.');
    }
}
</script>
</body>
</html>
