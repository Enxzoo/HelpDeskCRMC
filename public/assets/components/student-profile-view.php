<?php
$profile = $studentProfileView['profile'];
$values = $studentProfileView['values'];
$catalog = $studentProfileView['catalog'];
$showForm = $studentProfileView['showForm'];
$escape = static fn($value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
$profileName = trim($profile['first_name'] . ' ' . $profile['last_name']);
$profileInitials = mb_strtoupper(mb_substr($profile['first_name'], 0, 1) . mb_substr($profile['last_name'], 0, 1));
$detailGroups = [
    'Personal details' => ['first_name' => 'First name', 'last_name' => 'Last name', 'middle_name' => 'Middle name', 'suffix' => 'Suffix', 'email' => 'Email address'],
    'Academic details' => ['college_name' => 'College', 'program_name' => 'Program / Course', 'student_number' => 'Student ID', 'year_level' => 'Year level', 'section' => 'Section / Block', 'academic_year' => 'Academic year', 'semester' => 'Term', 'enrollment_type' => 'Enrollment classification'],
];
?>
<section id="profileView" class="student-profile-view" aria-labelledby="profileTitle" style="display:<?= $studentInitialView === 'profile' ? 'block' : 'none' ?>;"<?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <div class="profile-content">
        <div class="concerns-header profile-heading-row">
            <a class="back-btn profile-mobile-back" id="profileBack" href="dashboard_student.php" aria-label="Back to Ask Ben" title="Back to Ask Ben"><svg class="icon" aria-hidden="true"><use href="#i-back"/></svg></a>
            <div><h1 id="profileTitle">Profile</h1><p>Cebu Roosevelt Memorial Colleges</p></div>
        </div>

        <div class="profile-identity">
            <div class="avatar profile-avatar" aria-hidden="true"><?= $escape($profileInitials) ?></div>
            <div class="profile-identity-name"><h2><?= $escape($profileName) ?></h2><p>Student ID <strong><?= $escape($profile['student_number'] ?: 'Not provided') ?></strong></p></div>
            <?php if (!$showForm): ?><a href="student_profile.php?edit=1" class="profile-edit-link" title="Edit profile" aria-label="Edit profile"><img src="assets/icons/pencil.svg" alt="" width="18" height="18"></a><?php endif; ?>
        </div>

        <?php if ($studentProfileView['notice']): ?><div class="account-success" role="status"><img src="assets/icons/circle-check.svg" alt="" width="18" height="18"><span><?= $escape($studentProfileView['notice']) ?></span></div><?php endif; ?>
        <?php if ($studentProfileView['error']): ?><div class="account-error" role="alert"><?= $escape($studentProfileView['error']) ?></div><?php endif; ?>
        <?php if ($showForm): ?>
            <?php if (!$catalog['programs'] || !$catalog['terms']): ?><div class="account-error" role="alert">Academic options are unavailable. Contact the Registrar for assistance.</div><?php endif; ?>
            <form method="POST" action="student_profile.php" class="account-form"><?= csrf_field() ?><input type="hidden" name="action" value="update">
                <?php require __DIR__ . '/student-profile-fields.php'; ?>
                <fieldset class="profile-section"><legend>Account security</legend>
                    <div class="profile-grid"><label>Current password (required for email changes)<input type="password" name="current_password" autocomplete="current-password" maxlength="72"></label></div>
                </fieldset>
                <div class="account-actions"><a href="student_profile.php" class="profile-cancel-link">Cancel</a><button type="submit" class="thread-reply-btn" <?= !$catalog['programs'] || !$catalog['terms'] ? 'disabled' : '' ?>><img src="assets/icons/circle-check.svg" alt="" width="16" height="16">Save profile</button></div>
            </form>
        <?php else: ?>
            <?php foreach ($detailGroups as $groupTitle => $fields): ?>
            <section class="profile-detail-section" aria-label="<?= $escape($groupTitle) ?>">
                <h2><?= $groupTitle ?></h2>
                <dl class="account-details">
                    <?php foreach ($fields as $key => $label): ?>
                    <div<?= $key === 'email' ? ' class="full"' : '' ?>><dt><?= $label ?></dt><dd<?= !$profile[$key] ? ' class="profile-empty-value"' : '' ?>><?= $escape($profile[$key] ?: 'Not provided') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <?php endforeach; ?>
            <section class="profile-detail-section profile-contact-section" aria-labelledby="profileContactTitle">
                <h2 id="profileContactTitle">Contact details</h2>
                <form method="POST" action="student_profile.php" class="profile-contact-form" data-profile-contact data-original-mobile="<?= $escape($profile['mobile_number']) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="contact">
                    <label for="profileMobile">Mobile number (optional)<input type="tel" id="profileMobile" name="mobile_number" maxlength="25" value="<?= $escape($values['mobile_number']) ?>" autocomplete="tel"></label>
                    <div class="account-actions"><button type="button" class="profile-reset-button" data-contact-reset aria-label="Discard contact changes" title="Discard changes" hidden><img src="assets/icons/x.svg" alt="" width="18" height="18"></button><button type="submit" class="thread-reply-btn" data-contact-save><img src="assets/icons/circle-check.svg" alt="" width="16" height="16"><span>Save contact</span></button></div>
                </form>
            </section>
        <?php endif; ?>

        <details class="profile-history">
            <summary><img src="assets/icons/history.svg" alt="" width="18" height="18"><span>Account history</span><small><?= count($studentProfileView['history']) ?> <?= count($studentProfileView['history']) === 1 ? 'entry' : 'entries' ?></small><img class="profile-history-chevron" src="assets/icons/chevron-right.svg" alt="" width="16" height="16"></summary>
            <div class="account-history">
                <?php foreach ($studentProfileView['history'] as $event): ?>
                <article><div><strong><?= $escape($event['action']) ?></strong><time datetime="<?= $escape(str_replace(' ', 'T', $event['created_at'])) ?>"><?= $escape($event['created_at']) ?></time></div><?php if ($event['reason']): ?><p><?= $escape($event['reason']) ?></p><?php endif; ?></article>
                <?php endforeach; ?>
                <?php if (!$studentProfileView['history']): ?><p class="profile-history-empty">No account history yet.</p><?php endif; ?>
            </div>
        </details>
        <nav class="profile-legal-links" aria-label="Legal information"><a href="terms.php" target="_blank" rel="noopener">Terms of Service</a><a href="student_privacy.php" target="_blank" rel="noopener">Privacy Policy</a></nav>
    </div>
</section>
