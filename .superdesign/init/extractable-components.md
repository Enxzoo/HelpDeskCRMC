# Extractable UI components

No independent shared components were found. The PHP dashboards render their navigation, cards, and conversation UI directly in page templates. Keep the student-reply-thread iteration local to `public/dashboard_student.php` and `public/assets/css/dashboard_student.css`; do not introduce an extracted component solely for this change.
