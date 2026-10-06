# Page dependency trees

## `/dashboard_student.php` — Student dashboard
- Entry: `public/dashboard_student.php`
- Styles: `public/assets/css/dashboard_student.css`
- Escalation form: `public/assets/components/escalation-form.html`
- Student concern/reply data: `public/api/get_student_concerns_with_replies.php`
- Student reply submission: `public/api/submit_student_reply.php`
- Feedback submission: `public/api/submit_feedback.php`
- Relevant UI: the staff-reply thread markup is in the page; the thread layout and responsive rules are in the dashboard stylesheet.

## `/dashboard_staff.php` — Staff operations
- Entry: `public/dashboard_staff.php`
- Styles: `public/assets/css/dashboard_staff.css`
- Data access: `app/models/Inquiry.php`, `app/models/Office.php`, `app/models/User.php`

## `/dashboard_admin.php` — Administration
- Entry: `public/dashboard_admin.php`
- Data access: `app/models/User.php`, `app/models/Office.php`, `app/models/Inquiry.php`
