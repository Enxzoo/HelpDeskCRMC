<?php
/**
 * logout.php
 * Destroys the session and sends the user back to login.
 */

require_once __DIR__ . '/../app/controllers/AuthController.php';

session_start();

$auth = new AuthController();
$auth->logout();

header('Location: login.php');
exit;