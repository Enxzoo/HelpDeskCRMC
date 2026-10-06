<div class="registration-agreements" aria-label="Required agreements">
    <input type="hidden" name="terms_version" value="<?= HELPDESK_TERMS_VERSION ?>">
    <input type="hidden" name="privacy_version" value="<?= HELPDESK_PRIVACY_VERSION ?>">
    <label class="profile-acknowledgement">
        <input
            type="checkbox"
            name="terms_accepted"
            value="1"
            required
            <?= ($values['terms_accepted'] ?? '') === '1' ? 'checked' : '' ?>
        >
        <span>
            I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms of Service</a>
            and confirm that my account details are accurate.
        </span>
    </label>
    <label class="profile-acknowledgement">
        <input
            type="checkbox"
            name="privacy_accepted"
            value="1"
            required
            <?= ($values['privacy_accepted'] ?? '') === '1' ? 'checked' : '' ?>
        >
        <span>
            I have read the <a href="student_privacy.php" target="_blank" rel="noopener">Privacy Policy</a>
            and consent to processing my identity, academic, and contact information for account access
            and school helpdesk support as described in that policy.
        </span>
    </label>
</div>
