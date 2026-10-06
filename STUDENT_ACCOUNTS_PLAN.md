# Student Accounts

## Current Registration Flow

Students create an account at `public/register.php`, complete their personal and academic details, and accept the Terms of Service and Privacy Policy. Registration creates an active student account, rotates the session ID, signs the student in, and redirects to `dashboard_student.php` immediately. No administrator approval or identity-review queue is involved.

Both legal checkboxes are required and unchecked by default. The server independently requires the exact value `1` for each agreement; omitted, false, or array-valued submissions are rejected. Stale legal-document versions require renewed agreement to the current documents. Legal links open in separate tabs to preserve the form. CSRF verification, password hashing, duplicate email/student-ID constraints, and fixed student-role assignment remain enforced.

The account, academic profile, legal acceptance record, and account-created event are saved in one transaction. Failed registration must not leave a partial account or acceptance record.

## Profiles and Access

The profile stays inside the existing student dashboard shell. Students can edit their own details and optional phone number. Changing an email requires the current password. The server identifies the student from the authenticated session, not a submitted `user_id`. Submitted academic details are self-reported; account creation does not establish official enrollment.

Administrators can view student accounts, correct academic details with a recorded reason, and suspend or reinstate access. Ordinary staff cannot perform these account-management actions. Suspension is still enforced on sign-in, existing sessions, APIs, and notification streams. Role and concern-ownership restrictions remain unchanged.

Concern records retain the academic snapshot from submission time; later profile updates do not rewrite old concern context.

## Legal Documents

- `public/terms.php`: Terms of Service.
- `public/student_privacy.php`: Privacy Policy, including Republic Act No. 10173, the Philippine Data Privacy Act of 2012.
- `app/config/legal.php`: legal-document version constants and the configurable school privacy contact.
- `student_legal_acceptances`: per-account terms/privacy versions and acceptance timestamps.

Set `PRIVACY_CONTACT_EMAIL` in the deployment environment to the school's confirmed privacy/DPO contact. Until provided, the policy refers students to the Registrar's established contact channels. A fixed retention period has not been supplied and is not invented in the policy.

The documents describe the app's actual account information, academic information, concerns, attachments, AI services, cookies, and notification options. They are a draft for school/DPO review, not certification of compliance. Confirm controller/contact information, retention schedules, minors' consent procedures, provider contracts, transfer safeguards, and production security before a live rollout.

Changes to legal-document text should be accompanied by an appropriate version update and a considered notice/re-consent process. Updating a profile must not silently change the terms/privacy acceptance record. Existing accounts are not backfilled with fabricated acceptance of the new documents.

## Database Upgrade

Run with the configured PHP CLI:

```powershell
php scripts/migrate-student-profiles.php
```

This installs the academic/profile tables and applies `20261005_student_self_registration.sql`. For an existing installation, `php scripts/migrate-student-self-registration.php` applies only the self-registration upgrade.

The upgrade removes the old approval columns, foreign key, index, and approval-decision events. Historical registration/update event names become ordinary account events. Existing user IDs, password hashes, suspension flags, concerns, and replies are preserved. The CLI first backs up legacy profile/account-event metadata in the current user's temporary directory and reports that path. Protect that backup as personal data and dispose of it according to the school's retention rules after validation.

Both migrations are safe to rerun. Fresh installations do not create the retired approval columns.

## Verification

```powershell
php tests/regression.php
php tests/admin_regression.php
php tests/chat_sessions_regression.php
php tests/notification_regression.php
node tests/workflow_regression.cjs
node tests/chat_regression.cjs
```

The tests cover migration of the old schema, immediate dashboard access, required legal agreements and bypass attempts, registration atomicity, unique identities, session-owned profile editing, email re-authentication, admin permissions, suspension, historical concern context, and preservation of the original student dashboard shell.

Optional real-browser checks use Playwright. Set `HELPDESK_PROFILE_VISUAL=1`, `HELPDESK_PLAYWRIGHT_PATH` to an installed Playwright module path if it is not locally resolvable, and `HELPDESK_CHROME_PATH` to an existing Chrome executable if needed. Then run `tests/regression.php`. Screenshots are generated in the ignored `tests/.runtime/` directory at desktop, tablet, and phone sizes.
