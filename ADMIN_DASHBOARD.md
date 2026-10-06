# Admin Workspace

Open `http://helpdeskcrmc.test/dashboard_admin.php` after signing in as an administrator.

## Views

- Overview: live concern counts, office workloads, assignment queue, staff and inquiry totals.
- Staff Accounts: search/filter, create, edit, activate/deactivate, reset password, and delete staff.
- Knowledge Base: create/edit/delete plain-text reference entries, with Draft and Published states and optional office association.
- Concerns: search/filter, read the concern and its replies, assign or unassign staff from the concern's office.
- Reports: preview concern reports or Ben inquiry reports; download CSV or open the print view and save PDF through the browser.

Staff deletion removes the account from management lists and disables sign-in. It releases open assignments but retains response authorship and resolved assignment history. The deleted email remains reserved. Deleted accounts cannot be reactivated with the legacy status-toggle API. Passwords require 12 to 72 bytes; a blank password while editing leaves it unchanged.

Published knowledge entries are searched using MySQL full-text search and provided to Ben as reference data. Drafts and deleted entries are not included. Existing static chatbot reference information remains as a fallback. No real AI request is made by the regression tests.

Inquiry reports count saved Ben conversations, not individual messages, and do not export chat transcripts. Concern reports include submitted concerns from every source. Date ranges include both selected days and filter by creation date. Preview shows up to 50 rows; CSV and print include every matching record. Downloads use the generated filters and current database records, not a frozen historical snapshot. CSV cells are protected against spreadsheet formula injection.

Admin APIs validate roles on every request; writes require CSRF tokens. Notification links now route administrators to this dashboard. Assignments enqueue an alert for the selected staff member. Gmail delivery still requires the SMTP setup described in `NOTIFICATIONS.md`.

## Setup

The migration has been applied to the local database. For another installation, apply the existing workflow/chat/notification migrations first, then run:

```powershell
php scripts/migrate-admin-workspace.php
```

The admin migration is repeatable and does not delete existing rows. It adds `users.deleted_at` and the `knowledge_entries` table. New installations use the updated base schema plus the migration.

## Tests

The DOM tests use jsdom as a test-only dependency, not an application dependency:

```powershell
npm ci --prefix tests
php tests/regression.php
php tests/admin_regression.php
php tests/chat_sessions_regression.php
php tests/notification_regression.php
php tests/groq_regression.php
node tests/chat_regression.cjs
node tests/notification_regression.cjs
node tests/workflow_regression.cjs
```

These tests create and remove isolated local databases. `tests/regression.php` includes HTTP authorization/CRUD/report tests and DOM interaction tests for the admin dashboard. It never modifies production records or sends live emails. DOM tests are not a substitute for desktop/mobile screenshot review.
