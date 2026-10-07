<?php
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
session_start();
header('Cache-Control: no-store');

$auth = new AuthController();
if (authenticatedUser() !== null) {
    header('Location: ' . $auth->dashboardFor((string) $_SESSION['role']));
    exit;
}

$error = null;
$values = [];
foreach (
    [
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'student_number',
        'email',
        'mobile_number',
        'college_id',
        'program_id',
        'year_level',
        'section',
        'term_id',
        'enrollment_type',
        'terms_accepted',
        'privacy_accepted',
    ] as $key
) {
    $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid or expired form submission. Please try again.';
    } else {
        $result = $auth->registerStudent($_POST);
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit;
        }
        $error = $result['error'];
    }
}

$catalog = (new StudentProfile())->catalog();
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (($_POST['terms_version'] ?? '') !== HELPDESK_TERMS_VERSION
        || ($_POST['privacy_version'] ?? '') !== HELPDESK_PRIVACY_VERSION)
) {
    $values['terms_accepted'] = $values['privacy_accepted'] = '';
}

$available = $catalog['programs'] !== [] && $catalog['terms'] !== [];
$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Student Account - HelpdeskCRMC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?= stylesheet_bundle('landing') ?>
    <?= stylesheet_bundle('workspace_ui') ?>
    <?= stylesheet_bundle('student_accounts') ?>
    <?= stylesheet_bundle('login') ?>
    <script src="assets/js/student_profile.js?v=<?= md5_file(__DIR__ . '/assets/js/student_profile.js') ?>"
        defer></script>
</head>

<body>
    <header>
        <div class="wrap">
            <div class="navbar">
                <a class="brand" href="index.php">
                    <img class="brand-mark" src="assets/images/helpdesk-logo.png" alt="Helpdesk CRMC">
                    <span class="brand-text">Helpdesk<span>CRMC</span></span>
                </a>
                <nav class="links">
                    <a href="index.php#about">About</a>
                    <a href="index.php#how">How it works</a>
                    <a href="index.php#offices">Offices</a>
                    <a href="index.php#faq">FAQs</a>
                </nav>
                <div class="navbar-cta">
                    <a class="btn btn-ghost" href="login.php">Sign in</a>
                    <a class="btn btn-dark" href="register.php" aria-current="page">Create account</a>
                </div>
            </div>
        </div>
    </header>

    <main class="login-container register-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Create student account</h1>
                <p>Enter your student details to get started</p>
            </div>

            <ol class="registration-progress" aria-label="Registration progress" hidden>
                <li data-registration-progress="0"><span
                        class="registration-progress-number">1</span><span>Personal</span></li>
                <li data-registration-progress="1"><span
                        class="registration-progress-number">2</span><span>Academic</span></li>
                <li data-registration-progress="2"><span
                        class="registration-progress-number">3</span><span>Security</span></li>
            </ol>

            <?php if ($error): ?>
                <div class="error-message" role="alert"><?= $escape($error) ?></div>
            <?php endif; ?>

            <?php if (!$available): ?>
                <div class="account-state">
                    <strong>Academic options are unavailable</strong>
                    <p>Contact the Registrar for assistance with the program or academic term list.</p>
                </div>
            <?php endif; ?>

            <form method="POST" class="account-form" id="studentRegistration"
                data-initial-step="<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? 2 : 0 ?>">
                <?= csrf_field() ?>
                <?php $registrationSteps = true;
                require __DIR__ . '/assets/components/student-profile-fields.php'; ?>

                <div data-registration-step="2">
                    <fieldset class="profile-section">
                        <legend>Account security</legend>
                        <div class="profile-grid">
                            <label for="email" class="full">
                                Email address
                                <input type="email" id="email" name="email" required maxlength="150"
                                    autocomplete="email" value="<?= $escape($values['email']) ?>">
                            </label>
                            <label for="password" class="profile-password">
                                Password (8+ characters)
                                <input type="password" id="password" name="password" required minlength="8"
                                    maxlength="72" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle="password"
                                    title="Show password" aria-label="Show password" aria-controls="password">
                                    <img src="assets/icons/eye.svg" alt="">
                                </button>
                            </label>
                            <label for="confirm_password" class="profile-password">
                                Confirm password
                                <input type="password" id="confirm_password" name="confirm_password" required
                                    minlength="8" maxlength="72" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle="confirm_password"
                                    title="Show password" aria-label="Show password" aria-controls="confirm_password">
                                    <img src="assets/icons/eye.svg" alt="">
                                </button>
                            </label>
                        </div>
                    </fieldset>

                    <?php require __DIR__ . '/assets/components/student-profile-acknowledgement.php'; ?>
                </div>

                <div class="registration-actions">
                    <button type="button" class="btn btn-ghost registration-back" data-registration-back hidden>Back</button>
                    <button type="button" class="submit-btn registration-next" data-registration-next hidden>Continue</button>
                    <button type="submit" class="submit-btn registration-submit" data-registration-submit <?= $available ? '' : 'disabled' ?>>
                        <span>Create account</span>
                    </button>
                </div>
            </form>

            <div class="registration-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </main>
</body>

</html>
