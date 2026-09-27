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
    <style>
        :root {
            --ink: #1C1B18;
            --ink-2: #26241F;
            --amber: #ECC94B;
            --amber-dk: #C98A06;
            --red: #B8231C;
            --teal: #1E7A8C;
            --cream: #FBF6EE;
            --card: #FFFFFF;
            --line: #ECE3D6;
            --muted: #847C6E;
            --bg: var(--cream);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(circle at 85% 15%, rgba(236, 201, 75, .16), transparent 38%),
                radial-gradient(circle at 15% 85%, rgba(30, 122, 140, .06), transparent 42%);
            pointer-events: none;
            z-index: 0;
        }

        .site-nav {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            padding: 24px 32px;
            background: transparent;
        }

        .site-brand {
            display: flex;
            align-items: center;
        }

        .site-brand img {
            height: 48px;
            width: auto;
        }

        .login-container {
            position: relative;
            z-index: 1;
            max-width: 440px;
            width: 100%;
            margin: 0 auto;
            padding: 0 24px 48px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-card {
            background: var(--card);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 4px 16px rgba(28, 27, 24, .08);
            border: 1px solid var(--line);
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 15px;
            color: var(--muted);
            margin-bottom: 32px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--cream);
            color: var(--ink);
            transition: all .2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--amber);
            box-shadow: 0 0 0 3px rgba(236, 201, 75, .12);
        }

        .error-message {
            background: rgba(184, 35, 28, .08);
            border: 1px solid rgba(184, 35, 28, .2);
            color: var(--red);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background: var(--amber);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all .2s;
        }

        .submit-btn:hover {
            background: var(--amber-dk);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(236, 201, 75, .3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--muted);
        }

        .login-footer a {
            color: var(--teal);
            text-decoration: none;
            font-weight: 600;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .site-nav {
                padding: 20px 20px;
            }

            .site-brand img {
                height: 40px;
            }

            .login-card {
                padding: 32px 24px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <nav class="site-nav">
        <a href="index.php" class="site-brand">
            <img src="assets/images/helpdesk-logo.png" alt="HelpdeskCRMC">
        </a>
    </nav>

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
