<?php
require_once __DIR__ . '/env.php';

const HELPDESK_TERMS_VERSION = '2026-10-05';
const HELPDESK_PRIVACY_VERSION = '2026-10-05';

function helpdeskPrivacyContact(): string
{
    $email = trim(env('PRIVACY_CONTACT_EMAIL', ''));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}
