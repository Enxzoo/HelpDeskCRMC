<?php
/**
 * login.php
 * Redesigned Authentication Portal — HELPDESKCRMC
 */

require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';

session_start();
require_once __DIR__ . '/../app/helpers/csrf.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid or expired form submission. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $auth = new AuthController();
        $result = $auth->login($email, $password);

        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit;
        }

        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - CRMC Helpdesk</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(155deg, #1557A0 0%, #2171B5 50%, #0D4278 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }

        /* Decorative circles in background */
        .bg-decoration {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }

        .circle-1 { width: 80px; height: 80px; top: 10%; left: 5%; }
        .circle-2 { width: 40px; height: 40px; top: 25%; right: 15%; }
        .circle-3 { width: 60px; height: 60px; bottom: 30%; left: 10%; }
        .circle-4 { width: 30px; height: 30px; top: 60%; right: 8%; }
        .circle-5 { width: 50px; height: 50px; bottom: 15%; right: 25%; }
        .circle-6 { width: 25px; height: 25px; top: 35%; left: 25%; }

        /* Wave decoration at bottom */
        .wave-container {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .wave path {
            fill: rgba(255, 255, 255, 0.05);
        }

        /* Login container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
        }

        /* Logo */
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-image {
            width: 120px;
            height: auto;
            margin-bottom: 20px;
        }

        /* Login card */
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-header h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 32px;
            font-weight: 800;
            color: #1A171E;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 15px;
            color: #7A7485;
        }

        /* Error alert */
        .error-alert {
            background: #FEE;
            border: 1px solid #FCC;
            color: #C33;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Form groups */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1A171E;
            margin-bottom: 8px;
        }

        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .label-row label {
            margin-bottom: 0;
        }

        .forgot-link {
            font-size: 13px;
            color: #2171B5;
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px;
            font-size: 15px;
            border: 1px solid #E6E3EB;
            border-radius: 12px;
            background: #F9F9FB;
            color: #1A171E;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2171B5;
            background: #FFF;
            box-shadow: 0 0 0 3px rgba(33, 113, 181, 0.1);
        }

        .form-group input::placeholder {
            color: #A9A5B3;
        }

        /* Remember me checkbox */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .remember-row input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .remember-row label {
            font-size: 14px;
            color: #7A7485;
            cursor: pointer;
        }

        /* Submit button */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(155deg, #2171B5 0%, #1557A0 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 12px rgba(33, 113, 181, 0.3);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(33, 113, 181, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Responsive */
        @media (max-width: 600px) {
            .login-card {
                padding: 36px 24px;
            }

            .login-header h1 {
                font-size: 26px;
            }

            .circle-1, .circle-2, .circle-3, .circle-4, .circle-5, .circle-6 {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <header class="site-nav">
        <a class="site-brand" href="index_new.php">
            <img src="assets/images/helpdesk-logo.png" alt="HelpdeskCRMC">
        </a>
        <nav class="site-links" aria-label="Primary navigation">
            <a href="index_new.php#features">Features</a>
            <a href="index_new.php#ben">Meet BenAI</a>
            <a href="index_new.php#how-it-works">How it works</a>
            <a href="index_new.php#faq">FAQ</a>
        </nav>
        <div class="site-actions">
            <a href="login.php">Log in</a>
            <a class="get-started" href="login.php">Get Started</a>
        </div>
    </header>

    <!-- Background decorative circles -->
    <div class="bg-decoration circle-1"></div>
    <div class="bg-decoration circle-2"></div>
    <div class="bg-decoration circle-3"></div>
    <div class="bg-decoration circle-4"></div>
    <div class="bg-decoration circle-5"></div>
    <div class="bg-decoration circle-6"></div>

    <!-- Wave decoration -->
    <div class="wave-container">
        <svg class="wave" viewBox="0 0 1440 200" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M0,100 C300,150 600,50 900,100 C1200,150 1440,100 1440,100 L1440,200 L0,200 Z" />
        </svg>
    </div>

    <!-- Login container -->
    <div class="login-container">
        <!-- Login card -->
        <div class="login-card">
            <div class="logo-container">
                <img src="assets/images/helpdesk-logo.png" alt="CRMC Helpdesk" class="logo-image">
            </div>
            <div class="login-header">
                <h1>Sign in</h1>
            </div>

            <form method="post" action="login.php">
                <?= csrf_field() ?>

                <?php if ($error): ?>
                    <div class="error-alert">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">Login</label>
                    <input type="email" id="email" name="email" placeholder="name@crmc.edu.ph" required autofocus>
                </div>

                <div class="form-group">
                    <div class="label-row">
                        <label for="password">Password</label>
                        <a href="#" class="forgot-link" onclick="alert('Please contact the MIS / IT Office to reset your password.'); return false;">Forgot password?</a>
                    </div>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <div class="remember-row">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <button type="submit" class="submit-btn">Login</button>
            </form>
        </div>

        <!-- Footer -->
        <div class="login-footer">
            © <?= date('Y') ?> Cebu Roosevelt Memorial Colleges. All rights reserved.
        </div>
    </div>

</body>
</html>
