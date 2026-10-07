<div class="notification-center" id="notificationCenter" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
  <button type="button" class="notification-icon-button" id="notificationToggle" <?= dev_locator_attributes(__FILE__, __LINE__) ?> title="Notifications" aria-label="Notifications" aria-expanded="false" aria-controls="notificationPanel">
    <img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/bell.svg" alt="" width="20" height="20">
    <span class="notification-badge" id="notificationBadge" <?= dev_locator_attributes(__FILE__, __LINE__) ?> hidden>0</span>
  </button>
  <section class="notification-panel" id="notificationPanel" <?= dev_locator_attributes(__FILE__, __LINE__) ?> aria-label="Notifications" hidden>
    <div class="notification-heading" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <h2 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Notifications</h2>
      <button type="button" class="notification-icon-button" id="notificationReadAll" <?= dev_locator_attributes(__FILE__, __LINE__) ?> title="Mark all as read" aria-label="Mark all notifications as read"><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/check-check.svg" alt="" width="18" height="18"></button>
      <button type="button" class="notification-icon-button" id="notificationClose" <?= dev_locator_attributes(__FILE__, __LINE__) ?> title="Close notifications" aria-label="Close notifications"><img <?= dev_locator_attributes(__FILE__, __LINE__) ?> src="assets/icons/x.svg" alt="" width="18" height="18"></button>
    </div>
    <div class="notification-settings" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input type="checkbox" id="notificationEmail" <?= dev_locator_attributes(__FILE__, __LINE__) ?>> Email alerts <span id="notificationEmailStatus" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></span></label>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?>><input type="checkbox" id="notificationPush" <?= dev_locator_attributes(__FILE__, __LINE__) ?>> Browser alerts <span id="notificationPushStatus" <?= dev_locator_attributes(__FILE__, __LINE__) ?>></span></label>
    </div>
    <p class="notification-status" id="notificationStatus" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status">Connecting...</p>
    <div class="notification-list" id="notificationList" <?= dev_locator_attributes(__FILE__, __LINE__) ?>><p class="notification-empty" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Loading notifications...</p></div>
  </section>
  <div class="notification-toast" id="notificationToast" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status" aria-live="polite" hidden></div>
</div>
