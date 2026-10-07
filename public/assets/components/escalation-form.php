<?php require_once __DIR__ . '/../../../app/helpers/dev_locator.php'; ?>
<div class="escalation-card" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
  <div class="esc-header" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
    <h3 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Request staff assistance</h3>
  </div>

  <form class="esc-form" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-escalation-form>
    <div class="esc-alert esc-alert-error" <?= dev_locator_attributes(__FILE__, __LINE__) ?> style="display: none;" data-error-msg></div>
    <div class="esc-duplicate-notice" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-duplicate-notice role="status" aria-live="polite" tabindex="-1" hidden></div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-fullname">Full Name <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
      <input type="text" id="esc-fullname" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="fullName" required>
    </div>

    <div class="esc-row" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-student-number">Student ID number</label>
        <input type="text" id="esc-student-number" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="studentNumber" readonly placeholder="Not provided">
      </div>
      <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-year-level">Year level</label>
        <input type="text" id="esc-year-level" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="yearLevel" readonly placeholder="Not provided">
      </div>
    </div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-program">Program / Course</label>
      <input type="text" id="esc-program" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="program" readonly placeholder="Not provided">
    </div>

    <div class="esc-row" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-email">Email address <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
        <input type="email" id="esc-email" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="email" required>
      </div>
      <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-phone">Contact Number <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
        <input type="tel" id="esc-phone" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="phone" required>
      </div>
    </div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-office">Category / Office <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
      <select id="esc-office" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="office" required>
        <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="">Select an office...</option>
        <optgroup <?= dev_locator_attributes(__FILE__, __LINE__) ?> label="Academic Services">
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="registrar">Registrar's Office</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="cashier">Finance</option>
        </optgroup>
        <optgroup <?= dev_locator_attributes(__FILE__, __LINE__) ?> label="Student Support">
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="guidance">Guidance Office</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="saso">SASO - Student Affairs</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="library">Library</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="clinic">Clinic</option>
        </optgroup>
        <optgroup <?= dev_locator_attributes(__FILE__, __LINE__) ?> label="Departments">
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="cte">College of Teacher Education (CTE)</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="cbe">College of Business Education (CBE)</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="ccs">College of Computer Studies (CCS)</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="cje">College of Justice Education (CCJE)</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="psychology">Psychology Department</option>
        </optgroup>
        <optgroup <?= dev_locator_attributes(__FILE__, __LINE__) ?> label="Other">
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="property">Property Custodian</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="itcd">ITCD</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="hr">Human Resources</option>
          <option <?= dev_locator_attributes(__FILE__, __LINE__) ?> value="main">General / Main Office</option>
        </optgroup>
      </select>
    </div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-subject">Subject <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
      <input type="text" id="esc-subject" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="subject" placeholder="e.g., Transcript of Records Request" required>
    </div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-concern">Describe your concern <span class="esc-req" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>*</span></label>
      <textarea id="esc-concern" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="concern" rows="3" required></textarea>
    </div>

    <div class="esc-group" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <label <?= dev_locator_attributes(__FILE__, __LINE__) ?> for="esc-file">Attach files (optional)</label>
      <div class="esc-file-upload" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
        <input type="file" id="esc-file" <?= dev_locator_attributes(__FILE__, __LINE__) ?> name="attachment" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx" style="display: none;">
        <button type="button" class="esc-file-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?> onclick="document.getElementById('esc-file').click()">
          <svg <?= dev_locator_attributes(__FILE__, __LINE__) ?> width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path <?= dev_locator_attributes(__FILE__, __LINE__) ?> d="M8 12.5l6-6a3 3 0 0 1 4.2 4.2l-8 8a5 5 0 1 1-7-7l7-7"/>
          </svg>
          Choose files
        </button>
        <div class="esc-file-list" id="esc-file-list" <?= dev_locator_attributes(__FILE__, __LINE__) ?> role="status" aria-live="polite"></div>
      </div>
    </div>

    <button type="submit" class="esc-btn" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>
      <span <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-submit-label>Submit Concern</span>
      <span class="esc-spinner" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-submit-spinner aria-hidden="true" hidden></span>
    </button>
    <p class="esc-analysis-status" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-analysis-status role="status" aria-live="polite" hidden></p>
  </form>

  <div class="esc-success" <?= dev_locator_attributes(__FILE__, __LINE__) ?> style="display: none;" data-success-msg>
    <div class="esc-success-icon" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>✓</div>
    <h3 <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Concern Forwarded Successfully!</h3>
    <p <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Your concern was submitted to the selected office. You can follow staff replies in My Concerns.</p>
    <p class="esc-success-urgency" <?= dev_locator_attributes(__FILE__, __LINE__) ?> data-urgency-result></p>
    <p class="esc-success-note" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>You can track the status of your concern in the <strong <?= dev_locator_attributes(__FILE__, __LINE__) ?>>My Concerns</strong> section.</p>
  </div>
</div>
