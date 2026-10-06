<?php
require_once __DIR__ . '/../app/config/legal.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - HelpdeskCRMC</title>
    <?php require __DIR__ . '/assets/components/ui-fonts.php'; ?>
    <?= stylesheet_bundle('workspace_ui') ?>
    <?= stylesheet_bundle('student_accounts') ?>
</head>

<body class="student-account-page">
    <header class="account-header"><a class="account-brand" href="index.php"><img src="assets/images/helpdesk-logo.png"
                alt="CRMC" width="30" height="30"><strong>HELPDESK<span>CRMC</span></strong></a>
        <nav class="account-nav"><a href="register.php">Create account</a><a class="button" href="login.php">Sign in</a>
        </nav>
    </header>
    <main class="account-shell privacy-copy">
        <h1 class="account-title">Terms of Service</h1>
        <p class="legal-version">Version <?= HELPDESK_TERMS_VERSION ?> | Effective October 5, 2026</p>
        <p>These terms govern use of HelpdeskCRMC, the school helpdesk for Cebu Roosevelt Memorial Colleges (CRMC). Read
            them together with the <a href="student_privacy.php">Privacy Policy</a> before creating an account.</p>
        <nav class="legal-nav" aria-label="Legal documents"><a href="student_privacy.php">Privacy Policy</a><a
                href="#terms-contact">Contact</a></nav>

        <h2>1. Account creation and acceptance</h2>
        <p>Students may create their own account and sign in immediately after completing registration. Accepting these
            terms and providing the specific consent described in the Privacy Policy are required for account creation.
            A helpdesk account does not establish enrollment, grant academic eligibility, or replace official school
            records.</p>

        <h2>2. Accurate details and account security</h2>
        <p>Use your own student ID and accurate identity, academic, and contact information. Do not impersonate another
            person or register with someone else's records. Keep your password confidential, do not share your account,
            and sign out on shared devices. Report suspected unauthorized access through the school's established
            channels. If you are a minor, seek parent or guardian assistance where required.</p>

        <h2>3. Acceptable use</h2>
        <ul>
            <li>Submit genuine school-related requests and communicate respectfully with staff.</li>
            <li>Do not send threats, harassment, discriminatory abuse, spam, fraudulent requests, or unlawful content.
            </li>
            <li>Do not upload malicious files, attempt unauthorized access, disrupt the service, or access another
                person's records.</li>
            <li>Share only information necessary for the request. Do not submit passwords, payment credentials, or
                private information about others without a lawful basis.</li>
        </ul>

        <h2>4. Concerns, attachments, and replies</h2>
        <p>You remain responsible for the information and files you submit and must have the right to share them.
            Submission allows authorized school personnel and configured service providers to process the material for
            helpdesk support as described in the Privacy Policy; it does not transfer ownership of your content. Staff
            may route requests to the relevant office, request clarification, change status, or close resolved concerns.
        </p>

        <h2>5. Ask Ben and automated assistance</h2>
        <p>Ben uses AI to assist with questions. Automated tools may suggest urgency or identify similar concerns. Their
            output can be incomplete or incorrect and is not an official school decision, professional advice, or a
            guarantee of an outcome. Confirm enrollment, financial, disciplinary, health, or other consequential matters
            with the responsible school office.</p>

        <h2>6. Service limits and urgent matters</h2>
        <p>HelpdeskCRMC is not an emergency service. For an emergency, contact appropriate emergency services or school
            personnel directly. Availability and response time depend on staff capacity, connectivity, maintenance, and
            configured external services. No specific response deadline or uninterrupted availability is guaranteed by
            these terms.</p>

        <h2>7. Privacy and notifications</h2>
        <p>The Privacy Policy explains information processing, recipients, AI providers, retention limitations, and
            privacy rights under Republic Act No. 10173. Browser alerts require browser permission, and notification
            preferences can be changed in the app. Accepting these terms does not waive your statutory rights or consent
            to unrelated marketing.</p>

        <h2>8. Account restrictions and requests</h2>
        <p>Authorized administrators may suspend access to address misuse, security concerns, or applicable school
            requirements. Suspension does not automatically erase helpdesk records. Contact the school to dispute a
            restriction, correct official records, or request account closure or lawful deletion. Applicable legal
            rights and record-retention obligations remain in effect.</p>

        <h2>9. Updates and applicable law</h2>
        <p>These terms are governed by applicable Philippine law. The published version and effective date identify the
            terms presented at registration. Significant changes should be communicated, with renewed agreement obtained
            where required. Nothing in these terms excludes rights or liabilities that cannot lawfully be excluded.</p>

        <h2 id="terms-contact">10. Contact</h2>
        <p>For account or service questions, contact the CRMC Registrar or responsible school office through established
            school channels. For personal-data concerns, use the contact process in the <a
                href="student_privacy.php#privacy-contact">Privacy Policy</a>.</p>
        <div class="legal-footer"><a class="button primary" href="register.php">Back to registration</a><a
                href="student_privacy.php">Privacy Policy</a></div>
    </main>
</body>

</html>