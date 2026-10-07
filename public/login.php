<?php
require_once __DIR__ . '/../app/config/env.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';


session_start();
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';

$error = null;
$emailValue = $_POST['email'] ?? '';
$passwordValue = $_POST['password'] ?? '';
$email = is_string($emailValue) ? trim($emailValue) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid or expired form submission. Please try again.';
    } elseif (!is_string($emailValue) || !is_string($passwordValue)) {
        http_response_code(400);
        $error = 'Please enter a valid email and password.';
    } else {
        try {
            $auth = new AuthController();
            $result = $auth->login($email, $passwordValue);

            if ($result['success']) {
                header('Location: ' . $result['redirect']);
                exit;
            }

            $error = $result['error'];
        } catch (Throwable $exception) {
            error_log('Login failed: ' . $exception->getMessage());
            http_response_code(503);
            $error = 'Sign in is temporarily unavailable. Please try again shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — HelpdeskCRMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= stylesheet_bundle('landing') ?>
    <?= stylesheet_bundle('login') ?>
    <script src="assets/js/student_profile.js?v=<?= md5_file(__DIR__ . '/assets/js/student_profile.js') ?>"
        defer></script>
</head>

<body <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <header <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="wrap" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="navbar" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <div class="brand" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><img class="brand-mark" <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/images/helpdesk-logo.png"
                        alt="Helpdesk CRMC"><span class="brand-text" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Helpdesk<span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>CRMC</span></span></div>
                <nav class="links" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="index.php#about">About</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="index.php#how">How it works</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                        href="index.php#offices">Offices</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="index.php#faq">FAQs</a>
                </nav>
                <div class="navbar-cta" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <a class="btn btn-ghost" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="login.php">Sign in</a>
                    <a class="btn btn-dark" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="register.php">Create account</a>
                </div>
            </div>
        </div>
    </header>

    <div class="login-container" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="login-card" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="login-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h1 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Welcome back</h1>
                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="error-message" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form <?= dev_locator_attributes(__FILE__, __LINE__) ?> method="POST" action="">
                <?= csrf_field() ?>

                <div class="form-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="email">Email address</label>
                    <input type="email" id="email" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="email" required autofocus
                        value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="password">Password</label>
                    <div class="profile-password" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <input type="password" id="password" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-password-toggle="password"
                            title="Show password" aria-label="Show password" aria-controls="password">
                            <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/eye.svg" alt="">
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Sign in</button>
            </form>

            <div class="login-footer" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Don't have an account?</p>
                <a class="btn btn-dark create-account-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="register.php">Create account</a>
            </div>
        </div>
    </div>
</body>

</html>
