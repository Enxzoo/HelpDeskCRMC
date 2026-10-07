<?php
require_once __DIR__ . '/../app/config/legal.php';
require_once __DIR__ . '/../app/helpers/stylesheets.php';
$privacyContact = helpdeskPrivacyContact();
$legalEscape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?> lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - HelpdeskCRMC</title>
    <?php require __DIR__ . '/assets/components/ui-fonts.php'; ?>
    <?= stylesheet_bundle('workspace_ui') ?>
    <?= stylesheet_bundle('student_accounts') ?>
</head>

<body class="student-account-page" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <header class="account-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a class="account-brand" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="index.php"><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/images/helpdesk-logo.png"
                alt="CRMC" width="30" height="30"><strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>>HELPDESK<span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>CRMC</span></strong></a>
        <nav class="account-nav" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="register.php">Create account</a><a class="button" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="login.php">Sign in</a>
        </nav>
    </header>
    <main class="account-shell privacy-copy" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <h1 class="account-title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Privacy Policy</h1>
        <p class="legal-version" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Version <?= HELPDESK_PRIVACY_VERSION ?> | Effective October 5, 2026</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>This policy describes personal data processing in HelpdeskCRMC, the school helpdesk for Cebu Roosevelt
            Memorial Colleges (CRMC). It applies to student accounts, staff and administrator access, conversations, and
            submitted concerns.</p>
        <nav class="legal-nav" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Legal documents"><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="terms.php">Terms of Service</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                href="#privacy-contact">Privacy contact</a></nav>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>1. Philippine data privacy law</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>The applicable Philippine law is <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    href="https://officialgazette.gov.ph/2012/08/15/republic-act-no-10173/" target="_blank"
                    rel="noopener">Republic Act No. 10173, the Data Privacy Act of 2012</a></strong>, together with its
            Implementing Rules and Regulations and relevant National Privacy Commission (NPC) issuances. Processing must
            have a lawful basis and follow transparency, legitimate purpose, and proportionality. <a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                href="https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/" target="_blank"
                rel="noopener">Read the Act's implementing rules</a>.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>2. Information recorded</h2>
        <ul <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <li <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account information: name, optional middle name and suffix, student ID, email, optional mobile number,
                password hash, account access state, and login timestamps.</li>
            <li <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Academic details: college, program/course, year level, section/block, academic year, term, and
                enrollment classification. Education information is sensitive personal information under the Act.</li>
            <li <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Helpdesk records: chat messages and history, concern descriptions and attachments, office routing,
                replies, status changes, urgency assessments, feedback, and relevant submission-time academic details.
            </li>
            <li <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Agreement records: the Terms of Service and Privacy Policy versions accepted and the dates of
                acceptance. Notification settings and, when enabled, browser push subscription information are also
                recorded.</li>
            <li <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Technical information: session cookies and operational logs. Hosting, email, AI, push, and font
                providers may receive network information, such as IP addresses, as part of delivering their services.
            </li>
        </ul>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>3. Purposes and legal basis</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Information is used to create and secure accounts, identify the owner of a concern, route and answer school
            support requests, maintain conversation history, deliver selected notifications, investigate misuse, and
            prepare authorized helpdesk reports. Academic details supplied by a student are not proof of official
            enrollment.</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>The required privacy checkbox requests specific consent to identity, academic, and contact processing for
            account access and school helpdesk support. Other processing may rely on an applicable legal obligation or
            another lawful basis where permitted, with the basis explained when relevant. This agreement does not
            authorize unrelated marketing or unrestricted sharing.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>4. Who may access information</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Students access their own records. Authorized staff access concerns within their permitted offices and
            responsibilities. Administrators manage accounts, concern routing, and authorized reports. Helpdesk records
            are not a public student directory. Information may also be disclosed when required by law or to address a
            valid legal claim, limited to what is necessary.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>5. AI and external service providers</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Ask Ben sends the message you enter and relevant conversation history to Google Gemini when configured.
            Automated urgency assessment and duplicate-concern matching may send concern text and relevant comparison
            text to Groq when configured. Personal information included in those messages may therefore reach these
            providers, including processing outside the Philippines. These features do not automatically send your
            entire account profile to the AI service.</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>AI responses and urgency classifications can be incorrect. Staff can assess concerns and override priority;
            an AI suggestion is not an official academic decision. Do not include passwords, payment credentials,
            unnecessary medical information, or another person's private records in chat or attachments. School
            operators must confirm suitable provider arrangements and safeguards before enabling external processing of
            sensitive data.</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Configured email and browser push providers process the information needed to deliver notifications. Google
            Fonts is requested to display the app's typefaces. External providers have their own privacy practices;
            using this app does not make their services school-operated.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>6. Cookies and notification choices</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Session cookies maintain sign-in and protect form submissions. Browser push requires browser permission; push
            and email preferences are available in the notification controls. Refusing browser notifications does not
            prevent account creation. This signup agreement does not itself enable browser push or subscribe you to
            marketing.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>7. Retention and security</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account and helpdesk records remain stored until an authorized retention or deletion process is carried out.
            Closing a concern or suspending account access does not delete its records. CRMC must establish and publish
            the applicable retention schedule; a fixed retention period for this app has not yet been provided. Ask the
            privacy contact for the applicable period and deletion procedure.</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>The app uses password hashes, authenticated role-based access, and form security tokens. These controls do
            not guarantee absolute security. Live deployment requires appropriately configured HTTPS, restricted
            database and file access, and school procedures for backups, incident response, and lawful disposal.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>8. Your rights and consent withdrawal</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Subject to legal conditions and exceptions, you may request information about processing, access, correction,
            objection, erasure or blocking, and data portability, and pursue available remedies or an NPC complaint. You
            may withdraw consent for consent-based processing through the privacy contact. Withdrawal may prevent
            services that require that processing; records may still need to be retained where a separate lawful basis
            applies.</p>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>You can edit your profile and optional contact number in the app. Requests affecting official school records,
            deletion, or other privacy rights should use the school's established channels. Identity checks for a
            privacy request protect records from disclosure to the wrong person.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>9. Students who are minors</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>If you are a minor, ask your parent or guardian and the school for assistance before sharing sensitive
            information. CRMC must apply the appropriate age and guardian-consent procedures where required; the signup
            checkbox is not a substitute for those procedures.</p>

        <h2 id="privacy-contact" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>10. Privacy contact and complaints</h2>
        <?php if ($privacyContact): ?>
            <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Contact the school's designated privacy representative at <a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                    href="mailto:<?= $legalEscape($privacyContact) ?>"><?= $legalEscape($privacyContact) ?></a>.</p>
        <?php else: ?>
            <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Contact the CRMC Registrar through the school's established contact channels and request referral to the
                school's Data Protection Officer or designated privacy representative. A dedicated app privacy email has not
                yet been provided.</p><?php endif; ?>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>You may also contact the <a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="https://privacy.gov.ph/" target="_blank" rel="noopener">National Privacy
                Commission</a> about your rights or a privacy complaint. Do not post sensitive records in public
            channels when requesting assistance.</p>

        <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>11. Policy changes</h2>
        <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>The version and effective date identify this policy. Registration records the version accepted, not a blanket
            agreement to future changes. Material changes in consent-based processing require appropriate notice and
            renewed consent where applicable.</p>
        <div class="legal-footer" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a class="button primary" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="register.php">Back to registration</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?>
                href="terms.php">Terms of Service</a></div>
    </main>
</body>

</html>