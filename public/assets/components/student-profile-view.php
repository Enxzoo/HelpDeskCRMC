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
<section id="profileView" class="student-profile-view" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="profileTitle" style="display:<?= $studentInitialView === 'profile' ? 'block' : 'none' ?>;">
    <div class="profile-content" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <div class="concerns-header profile-heading-row" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <a class="back-btn profile-mobile-back" id="profileBack" <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="dashboard_student.php" aria-label="Back to Ask Ben" title="Back to Ask Ben"><svg class="icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true"><use <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="#i-back"/></svg></a>
            <div <?= dev_locator_attributes(__FILE__, __LINE__) ?>><h1 id="profileTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Profile</h1><p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Cebu Roosevelt Memorial Colleges</p></div>
        </div>

        <div class="profile-identity" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <div class="avatar profile-avatar" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-hidden="true"><?= $escape($profileInitials) ?></div>
            <div class="profile-identity-name" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($profileName) ?></h2><p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Student ID <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($profile['student_number'] ?: 'Not provided') ?></strong></p></div>
            <?php if (!$showForm): ?><a href="student_profile.php?edit=1" class="profile-edit-link" <?= dev_locator_attributes(__FILE__, __LINE__) ?> title="Edit profile" aria-label="Edit profile"><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/pencil.svg" alt="" width="18" height="18"></a><?php endif; ?>
        </div>

        <?php if ($studentProfileView['notice']): ?><div class="account-success" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status"><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/circle-check.svg" alt="" width="18" height="18"><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($studentProfileView['notice']) ?></span></div><?php endif; ?>
        <?php if ($studentProfileView['error']): ?><div class="account-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert"><?= $escape($studentProfileView['error']) ?></div><?php endif; ?>
        <?php if ($showForm): ?>
            <?php if (!$catalog['programs'] || !$catalog['terms']): ?><div class="account-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="alert">Academic options are unavailable. Contact the Registrar for assistance.</div><?php endif; ?>
            <form method="POST" action="student_profile.php" class="account-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= csrf_field() ?><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="action" value="update">
                <?php require __DIR__ . '/student-profile-fields.php'; ?>
                <fieldset class="profile-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><legend <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account security</legend>
                    <div class="profile-grid" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><label <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Current password (required for email changes)<input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="password" name="current_password" autocomplete="current-password" maxlength="72"></label></div>
                </fieldset>
                <div class="account-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><a href="student_profile.php" class="profile-cancel-link" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Cancel</a><button type="submit" class="thread-reply-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> <?= !$catalog['programs'] || !$catalog['terms'] ? 'disabled' : '' ?>><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/circle-check.svg" alt="" width="16" height="16">Save profile</button></div>
            </form>
        <?php else: ?>
            <?php foreach ($detailGroups as $groupTitle => $fields): ?>
            <section class="profile-detail-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="<?= $escape($groupTitle) ?>">
                <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $groupTitle ?></h2>
                <dl class="account-details" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                    <?php foreach ($fields as $key => $label): ?>
                    <div <?= dev_locator_attributes(__FILE__, __LINE__) ?><?= $key === 'email' ? ' class="full"' : '' ?>><dt <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $label ?></dt><dd <?= dev_locator_attributes(__FILE__, __LINE__) ?><?= !$profile[$key] ? ' class="profile-empty-value"' : '' ?>><?= $escape($profile[$key] ?: 'Not provided') ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
            <?php endforeach; ?>
            <section class="profile-detail-section profile-contact-section" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-labelledby="profileContactTitle">
                <h2 id="profileContactTitle" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Contact details</h2>
                <form method="POST" action="student_profile.php" class="profile-contact-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-profile-contact data-original-mobile="<?= $escape($profile['mobile_number']) ?>"><?= csrf_field() ?><input <?= dev_locator_attributes(__FILE__, __LINE__) ?> type="hidden" name="action" value="contact">
                    <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="profileMobile">Mobile number (optional)<input type="tel" id="profileMobile" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="mobile_number" maxlength="25" value="<?= $escape($values['mobile_number']) ?>" autocomplete="tel"></label>
                    <div class="account-actions" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><button type="button" class="profile-reset-button" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-contact-reset aria-label="Discard contact changes" title="Discard changes" hidden><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/x.svg" alt="" width="18" height="18"></button><button type="submit" class="thread-reply-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-contact-save><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/circle-check.svg" alt="" width="16" height="16"><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Save contact</span></button></div>
                </form>
            </section>
        <?php endif; ?>

        <details class="profile-history" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
            <summary <?= dev_locator_attributes(__FILE__, __LINE__) ?>><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/history.svg" alt="" width="18" height="18"><span <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Account history</span><small <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= count($studentProfileView['history']) ?> <?= count($studentProfileView['history']) === 1 ? 'entry' : 'entries' ?></small><img class="profile-history-chevron" <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/chevron-right.svg" alt="" width="16" height="16"></summary>
            <div class="account-history" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
                <?php foreach ($studentProfileView['history'] as $event): ?>
                <article <?= dev_locator_attributes(__FILE__, __LINE__) ?>><div <?= dev_locator_attributes(__FILE__, __LINE__) ?>><strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($event['action']) ?></strong><time <?= dev_locator_attributes(__FILE__, __LINE__) ?> datetime="<?= $escape(str_replace(' ', 'T', $event['created_at'])) ?>"><?= $escape($event['created_at']) ?></time></div><?php if ($event['reason']): ?><p <?= dev_locator_attributes(__FILE__, __LINE__) ?>><?= $escape($event['reason']) ?></p><?php endif; ?></article>
                <?php endforeach; ?>
                <?php if (!$studentProfileView['history']): ?><p class="profile-history-empty" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>No account history yet.</p><?php endif; ?>
            </div>
        </details>
        <nav class="profile-legal-links" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Legal information"><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="terms.php" target="_blank" rel="noopener">Terms of Service</a><a <?= dev_locator_attributes(__FILE__, __LINE__) ?> href="student_privacy.php" target="_blank" rel="noopener">Privacy Policy</a></nav>
    </div>
</section>
