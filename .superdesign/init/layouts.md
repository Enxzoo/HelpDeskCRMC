# Shared layouts

## Student dashboard shell
- Source: `public/dashboard_student.php`
- Description: Three-column desktop dashboard with a left navigation sidebar, central page/chat area, and right chat-history panel. Thread details render inside the central area.
- Styles: `public/assets/css/dashboard_student.css`
- Responsive behavior: At widths up to 960px, sidebar and history panel are hidden and the main view expands.
- The staff-reply thread is a full central-area overlay with header, scrollable message list, and bottom composer.

## Other application shells
- Staff: `public/dashboard_staff.php`, styled by `public/assets/css/dashboard_staff.css`.
- Admin: `public/dashboard_admin.php`.
