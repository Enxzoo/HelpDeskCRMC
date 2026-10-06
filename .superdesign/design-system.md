# HelpdeskCRMC student thread design system

Use the existing HelpdeskCRMC visual language: warm cream canvas, white conversation surfaces, charcoal text, muted taupe metadata, gold/yellow accents, and restrained red only for important warnings. Use Inter or a system sans-serif. Keep controls rounded, readable, and compact; conversation messages should remain the visual focus.

The student dashboard uses a desktop three-column shell with a compact left navigation and right conversation history, and a responsive central content area. The staff-reply thread occupies the central workspace beneath a concise subject/office/status header. Conversation messages scroll independently; the composer stays at the bottom.

Status-dependent bottom area:
- On Hold: show an emphasized but compact student reply composer with a clear text field and gold primary send action. Indicate that staff is waiting for the student's response.
- Any status other than On Hold, including Resolved: show feedback controls instead of the reply composer. For Resolved, ask how the student experienced the resolution, with optional written feedback and a clear submit action.
- Do not show both composer and feedback simultaneously. Preserve accessibility, visible focus, responsive sizing, and existing dashboard visual tokens.

CSS tokens are defined in `public/assets/css/dashboard_student.css`: ink `#1c1b18`, amber `#ecc94b`, darker gold `#c98a06`, cream `#fbf6ee`, card `#ffffff`, border `#ece3d6`, muted `#847c6e`, red `#b8231c`, and teal `#1e7a8c`.
