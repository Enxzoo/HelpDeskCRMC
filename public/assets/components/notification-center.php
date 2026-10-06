<div class="notification-center" id="notificationCenter">
  <button type="button" class="notification-icon-button" id="notificationToggle" title="Notifications" aria-label="Notifications" aria-expanded="false" aria-controls="notificationPanel">
    <img src="assets/icons/bell.svg" alt="" width="20" height="20">
    <span class="notification-badge" id="notificationBadge" hidden>0</span>
  </button>
  <section class="notification-panel" id="notificationPanel" aria-label="Notifications" hidden>
    <div class="notification-heading">
      <h2>Notifications</h2>
      <button type="button" class="notification-icon-button" id="notificationReadAll" title="Mark all as read" aria-label="Mark all notifications as read"><img src="assets/icons/check-check.svg" alt="" width="18" height="18"></button>
      <button type="button" class="notification-icon-button" id="notificationClose" title="Close notifications" aria-label="Close notifications"><img src="assets/icons/x.svg" alt="" width="18" height="18"></button>
    </div>
    <div class="notification-settings">
      <label><input type="checkbox" id="notificationEmail"> Email alerts <span id="notificationEmailStatus"></span></label>
      <label><input type="checkbox" id="notificationPush"> Browser alerts <span id="notificationPushStatus"></span></label>
    </div>
    <p class="notification-status" id="notificationStatus" role="status">Connecting...</p>
    <div class="notification-list" id="notificationList"><p class="notification-empty">Loading notifications...</p></div>
  </section>
  <div class="notification-toast" id="notificationToast" role="status" aria-live="polite" hidden></div>
</div>
