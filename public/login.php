<?php
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
    <title>Sign In — HelpdeskCRMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    <link rel="stylesheet" href="assets/css/login.css?v=2">
</head>
<body>
    <header>
      <div class="wrap">
        <div class="navbar">
          <div class="brand"><img class="brand-mark" src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC"><span class="brand-text">Helpdesk<span>CRMC</span></span></div>
          <nav class="links">
            <a href="index.php#about">About</a><a href="index.php#how">How it works</a><a href="index.php#offices">Offices</a><a href="index.php#faq">FAQs</a>
          </nav>
          <div class="navbar-cta">
            <a class="btn btn-ghost" href="login.php">Sign in</a>
            <a class="btn btn-dark" href="login.php">Ask Ben</a>
          </div>
        </div>
      </div>
    </header>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Welcome back</h1>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autofocus
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                    >
                </div>

                <button type="submit" class="submit-btn">Sign in</button>
            </form>

            <div class="login-footer">
                Don't have an account? <a href="index.php">Learn more</a>
            </div>
        </div>
    </div>
</body>
</html>
