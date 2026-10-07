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
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

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
                    <a class="btn btn-dark" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="register.php" aria-current="page">Create account</a>
                </div>
            </div>
        </div>
    </header>

    <main class="login-container register-container" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="login-card" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="login-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <h1 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Create student account</h1>
                <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Enter your student details to get started</p>
            </div>

            <ol class="registration-progress" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Registration progress" hidden>
                <li <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-progress="0"><span
                        class="registration-progress-number" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>1</span><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Personal</span></li>
                <li <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-progress="1"><span
                        class="registration-progress-number" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>2</span><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Academic</span></li>
                <li <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-progress="2"><span
                        class="registration-progress-number" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?>>3</span><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Security</span></li>
            </ol>

            <?php if ($error): ?>
                <div class="error-message" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert"><?= $escape($error) ?></div>
            <?php endif; ?>

            <?php if (!$available): ?>
                <div class="account-state" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Academic options are unavailable</strong>
                    <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Contact the Registrar for assistance with the program or academic term list.</p>
                </div>
            <?php endif; ?>

            <form method="POST" class="account-form" id="studentRegistration" <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                data-initial-step="<?= $_SERVER['REQUEST_METHOD'] === 'POST' ? 2 : 0 ?>">
                <?= csrf_field() ?>
                <?php $registrationSteps = true;
                require __DIR__ . '/assets/components/student-profile-fields.php'; ?>

                <div <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-step="2">
                    <fieldset class="profile-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                        <legend <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account security</legend>
                        <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                            <label for="email" class="full" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                Email address
                                <input type="email" id="email" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="email" required maxlength="150"
                                    autocomplete="email" value="<?= $escape($values['email']) ?>">
                            </label>
                            <label for="password" class="profile-password" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                Password (8+ characters)
                                <input type="password" id="password" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="password" required minlength="8"
                                    maxlength="72" autocomplete="new-password">
                                <button type="button" class="password-toggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-password-toggle="password"
                                    title="Show password" aria-label="Show password" aria-controls="password">
                                    <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/eye.svg" alt="">
                                </button>
                            </label>
                            <label for="confirm_password" class="profile-password" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                                Confirm password
                                <input type="password" id="confirm_password" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="confirm_password" required
                                    minlength="8" maxlength="72" autocomplete="new-password">
                                <button type="button" class="password-toggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-password-toggle="confirm_password"
                                    title="Show password" aria-label="Show password" aria-controls="confirm_password">
                                    <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/eye.svg" alt="">
                                </button>
                            </label>
                        </div>
                    </fieldset>

                    <?php require __DIR__ . '/assets/components/student-profile-acknowledgement.php'; ?>
                </div>

                <div class="registration-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <button type="button" class="btn btn-ghost registration-back" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-back hidden>Back</button>
                    <button type="button" class="submit-btn registration-next" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-next hidden>Continue</button>
                    <button type="submit" class="submit-btn registration-submit" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-registration-submit <?= $available ? '' : 'disabled' ?>>
                        <span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Create account</span>
                    </button>
                </div>
            </form>

            <div class="registration-footer" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                Already have an account? <a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="login.php">Sign in</a>
            </div>
        </div>
    </main>
</body>

</html>
