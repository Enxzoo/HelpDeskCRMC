<?php
/**
 * index.php
 * Front controller — every request into the public/ folder should
 * ultimately be routed through here (or a dedicated router later on).
 * Keep this file thin: no business logic, just wiring.
 */

require_once __DIR__ . '/../app/config/env.php';

session_start();

// Inquiry submission is now handled directly in dashboard_student.php,
// since it needs the logged-in student's session. This file just
// routes logged-in users to their dashboard.
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../app/controllers/AuthController.php';
    $auth = new AuthController();
    header('Location: ' . $auth->dashboardFor($_SESSION['role']));
    exit;
}

header('Location: login.php');
exit;