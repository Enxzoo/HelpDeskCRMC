# Stylesheet File Map

Edit the CSS file for the page and feature you are debugging. Each rule is formatted with one declaration per line.

| Page | Folder | Main files |
| --- | --- | --- |
| Student dashboard and profile | `student/` | `sidebar.css`, `home.css`, `profile.css`, `profile-form.css`, `chat-*.css`, `thread-*.css`, `concern-*.css`, `dialogs.css` |
| Staff dashboard | `staff/` | `sidebar.css`, `header.css`, `queue.css`, `detail.css`, `thread.css`, `reply.css` |
| Shared dashboard theme | `shared/` | `tokens.css`, `base.css`, `controls.css`, `forms.css`, `dialogs.css`, `workspace.css` |
| Admin dashboard | `admin/` | `layout.css`, `overview.css`, `tables.css`, `knowledge.css`, `reports.css`, `dialogs-forms.css` |
| Landing page | `landing/` | `navigation.css`, `hero.css`, `chat-preview.css`, `sections.css`, `footer.css` |
| Registration, legal pages, and student accounts | `accounts/` | `profile-form.css`, `states.css`, `details.css`, `catalog.css`, `registration.css`, `privacy.css` |
| Escalation form | `components/` | `escalation-base.css`, `escalation-form.css` |
| Login | This folder | `login.css` |
| Notifications | This folder | `notifications.css` |

`shared/tokens.css` contains the palette used by student, staff, admin, and account pages. The other shared files provide the admin and account pages' typography, controls, forms, dialogs, and responsive navigation. Student and staff keep their existing feature styles. Files with `responsive` in their names contain screen-size rules. Student thread mobile rules also live at the end of `student/thread-feedback.css` to preserve their original order.

The student profile is a view inside the original `dashboard_student.php` layout. `student_profile.php` handles profile submissions and renders that same layout; `assets/components/student-profile-view.php` contains only the profile content. Its layout, details, and history styles are in `student/profile.css`; editable fields and contact actions are in `student/profile-form.css`. These styles are scoped to the profile, preserving the dashboard's sidebar, chat, and history panel. Student logout confirmation is shared in `assets/js/student_account_actions.js`.

The admin dashboard, student accounts, and school catalog share `assets/components/workspace-sidebar.php`. Admin account screens also use `workspace-header.php` and `assets/js/workspace.js`. Their shared fonts load through `ui-fonts.php`.

The staff dashboard has later refinements in `workspace-layout.css`, `workspace-queue.css`, `workspace-detail.css`, and `workspace-reply.css`. These load after the original feature styles and intentionally override them. When inspecting a staff rule, check the matching `workspace-*.css` file as well.

Compact suggested replies below Ben's messages are styled in `student/chat-actions.css`. Their labels and topic-based choices live in `assets/js/ben_chat.js`; they do not change Ben's prompt or the AI API response. Only the latest message has active choices. Typing remains available, and the staff button opens the existing form directly.

## Loading and Cache Versions

`app/config/stylesheets.php` lists the files for each page in load order. PHP pages call `stylesheet_bundle()` from `app/helpers/stylesheets.php` to load each file directly with a content hash. Changing a feature file updates its URL automatically on the next page load.

Keep the file order intact: later rules can override earlier rules. When adding a new file, add it to the relevant list in `app/config/stylesheets.php`. This config is the only list to maintain; the old large page stylesheets have been replaced by the feature files.

## Finding a Rule

Inspect the element in the browser's developer tools. The Styles panel links directly to the feature file and line number. Check whether the winning rule is inside a media query or a later staff workspace file.

You can also search from the project folder:

```powershell
rg -n 'thread-reply-input' public/assets/css/student
rg -n 'concern-card' public/assets/css/staff
rg -n 'metric-grid' public/assets/css/admin
```

JavaScript can still set inline styles for visibility and other interactive states. Those appear as `element.style` in developer tools and can take precedence over a stylesheet rule.

The project-root `concerns-styles.css` and the standalone `assets/components/chat-thread-view.html` are not loaded by the current PHP pages. The active student concern and thread styles are in `student/`.
