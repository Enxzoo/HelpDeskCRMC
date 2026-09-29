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
</head>
<body>
    <!-- Blank slate for new Admin UI -->
    <h1>HELPDESKCRMC Admin Dashboard</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['name']) ?>.</p>
    <form method="POST" action="login.php?action=logout">
        <button type="submit">Logout</button>
    </form>
</body>
</html>
