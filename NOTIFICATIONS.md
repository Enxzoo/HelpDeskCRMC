# Notifications

New concerns notify their student, active staff in the assigned office, and admins.
Staff replies and status changes notify the student. Student replies notify office
staff and admins. A reply with a status change produces one combined alert.
Unchanged statuses do not generate extra alerts. Notification text excludes the
student's identity and the private concern/reply contents.

The bell inbox updates over server-sent events with a five-second polling fallback.
Browser push also works when a dashboard is closed, subject to browser/OS policy.
Enable Browser alerts in the bell menu on HTTPS or a localhost development URL.
HTTP custom domains such as `helpdeskcrmc.test` cannot use browser push.
The service worker does not cache authenticated pages or API responses.

## Gmail Setup

Use a dedicated helpdesk Gmail sender account with 2-Step Verification enabled.
Create an app password at https://myaccount.google.com/apppasswords and set these
values in the ignored `.env`, never in source code or chat:

```dotenv
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-sender-address@gmail.com
SMTP_PASSWORD=your-app-password-without-spaces
SMTP_FROM_EMAIL=your-sender-address@gmail.com
SMTP_FROM_NAME=Helpdesk CRMC
APP_URL=https://your-public-helpdesk-domain
```

Recipients come from the existing account email field; Gmail addresses require no
special recipient integration. No real emails are sent until SMTP is configured.
Some school/organization Google accounts prohibit app passwords. Use the school's
approved SMTP relay in that case. Email alerts can be disabled in the bell menu.
See Google's guide: https://support.google.com/accounts/answer/185833.

## Delivery Worker

Install dependencies with `composer install`, then apply
`database/migrations/20261002_add_notification_delivery.sql` to the app database.
Generate a VAPID key pair with `Minishlink\WebPush\VAPID::createVapidKeys()` and store
it in `.env` for a new deployment. This local installation already has its keys.

Run `php scripts/notification-worker.php --loop` as a supervised process in
production, or run `php scripts/notification-worker.php` from cron every minute.
On Windows, `scripts/install-notification-task.ps1 -PhpPath <absolute-php-path>`
installs a per-user logon task and starts it. The loop reloads `.env` every batch.
Use `php-win.exe` on Windows to keep the worker window hidden. Worker logs go to
`logs/notification-worker.log`.
The scheduled task restarts the worker after an unexpected failure, including a
lost database connection. It runs while the installing Windows user is signed in.

The worker leases delivery rows to avoid concurrent sends, retries transient
failures up to five attempts with backoff, and removes expired push subscriptions.
Unconfigured channels stay queued without consuming retry attempts. A sender crash
after SMTP acceptance but before acknowledging the row can cause a repeated email.
Inspect `notification_deliveries` for pending/failed jobs; logs omit credentials and
message contents. Do not rotate VAPID keys casually: existing browsers must
resubscribe after a key change. Keep the VAPID private key in the ignored `.env`.

## Verification

```powershell
php tests/regression.php
php tests/groq_regression.php
php tests/notification_regression.php
node tests/chat_regression.cjs
node tests/workflow_regression.cjs
node tests/notification_regression.cjs
```

The regression database is isolated. Email and push transports in those tests are
mocked; they must never send messages to real accounts.
