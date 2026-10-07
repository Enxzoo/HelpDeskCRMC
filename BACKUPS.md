# Local Automated Backups

Windows Task Scheduler runs `scripts/backup.php` every four hours while the
installing Windows user is signed in, and checks again at sign-in. Recent
backups are skipped; failures retry four times at 15-minute intervals. Laragon's
MySQL server must be running. No browser or website visit is required.

Default location: `C:\laragon\backups\helpdeskcrmc`, outside the web root.
The installer restricts this directory to the installing user, SYSTEM, and
Windows administrators. ZIP files are **not encrypted**. Use an encrypted disk
for sensitive records and copy backups to another device for disk-failure protection.

## Install

Run in PowerShell (Task Scheduler registration may require elevation):

```powershell
.\scripts\install-backup-task.ps1 -PhpPath 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
```

The installer starts an initial backup. An existing unrelated scheduled task
with the same name is not overwritten. `-BackupDirectory`, `-IntervalHours`,
and `-Keep` can override the defaults.

## Contents And Status

Each timestamped ZIP includes `database.sql`, `manifest.json` with SHA-256
checksums, and registered attachment files at their original relative paths.
Both linked and not-yet-linked upload records are included. Missing files
cancel the backup instead of silently creating an incomplete recovery set.
Only the latest 14 completed archives are retained after successful backups.
Other files in the backup directory are left alone.

The database uses a consistent InnoDB dump. Attachment metadata is read-locked
during capture and verification, so upload registration and concern submissions
may briefly wait. Other unrelated databases are not globally read-locked.
Do not run schema migrations or manually modify attachment files during capture.

`last-attempt.json` reports the latest result; `last-success.json` records the
last completed backup. A failure does not delete existing backups. Check the
timestamp periodically: a sleeping/offline PC or stopped MySQL can delay backups.

To create an extra backup manually:

```powershell
& 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' .\scripts\backup.php
```

## Restore Safely

1. Preserve the current system before making changes. Choose a known-good ZIP.
2. Extract it into a private temporary directory, not the website's public folder.
3. Create a new test database in phpMyAdmin and import `database.sql` there first.
4. Restore `storage/attachments/` (and legacy `uploads/`, if present) to a separate
   test installation at the same relative paths. Never upload a backup ZIP publicly.
5. With outgoing notifications disabled in that isolated installation, verify
   login, concern/reply history, office permissions, and attachment downloads.
6. Only after verification, stop application traffic and workers and perform an
   authorized production recovery. Restoring an older backup loses newer changes.

This is a database-and-attachments backup, not a complete machine image. Keep
application code/migrations separately in version control and keep `.env` and
required configuration securely recoverable. Secrets, sessions, caches, and
unregistered filesystem files are deliberately excluded from these archives.

Run the isolated backup/restore regression without changing the live database:

```powershell
& 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' .\tests\backup_regression.php
```
