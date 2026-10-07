const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/dashboard_student.php'), 'utf8');
const api = fs.readFileSync(path.join(root, 'public/api/submit_inquiry.php'), 'utf8');
const php = process.argv[2] || 'php';
const template = execFileSync(php, [path.join(root, 'public/assets/components/escalation-form.php')], { encoding: 'utf8' });
const runPhp = code => execFileSync(php, ['-r', code], { encoding: 'utf8' });
const profileBlock = source.match(/\$profile = \$studentProfileView\['profile'\];[\s\S]*?\n\];/)[0];
const profile = {
  first_name: 'Ana', middle_name: 'Marie', last_name: 'Cruz', suffix: 'Jr.',
  student_number: '2026-00123', email: 'ana@gmail.com', mobile_number: '09171234567',
  program_name: 'Bachelor of Science in Information Technology', year_level: 2,
};
function profileData(record) {
  const encoded = Buffer.from(JSON.stringify({ profile: record, values: { email: 'unsaved@example.test' } })).toString('base64');
  return JSON.parse(runPhp("$studentProfileView = json_decode(base64_decode('" + encoded + "'), true); $_SESSION = ['name' => 'Legacy Student'];\n" + profileBlock + '\necho json_encode($studentEscalationProfile);'));
}
const savedProfile = profileData(profile);
assert.deepEqual(savedProfile, {
  fullName: 'Ana Marie Cruz Jr.', studentNumber: profile.student_number, email: profile.email,
  phone: profile.mobile_number, program: profile.program_name, yearLevel: 'Year 2',
});
const legacyProfile = profileData({ first_name: 'Legacy', last_name: 'Student', email: 'legacy@example.test' });
assert.equal(legacyProfile.studentNumber, '');
assert.equal(legacyProfile.phone, '');
assert.equal(legacyProfile.program, '');
assert.equal(legacyProfile.yearLevel, '');
const officeOptions = JSON.parse(runPhp(api.match(/\$officeOptions = \[[\s\S]*?\n\];/)[0] + '\necho json_encode($officeOptions);'));
const extract = name => {
  const match = source.match(new RegExp('^([ \\t]*)(?:async )?function ' + name + '\\([^]*?^\\1}', 'm'));
  assert.ok(match, 'Missing function: ' + name);
  return match[0].replace(/<\?=[\s\S]*?\?>/g, 'null');
};

(async () => {
  const dom = new JSDOM('<div id="chatThreadInner"></div>', { runScripts: 'outside-only' });
  const { window } = dom;
  window.InquiryAttachments = window.eval(fs.readFileSync(path.join(root, 'public/assets/js/inquiry_attachments.js'), 'utf8') + '\nInquiryAttachments;');
  window.HTMLElement.prototype.scrollIntoView = () => {};
  Object.assign(window, {
    chatLoadRequestId: 1, currentChatSessionKey: 'a'.repeat(32), escalationLoadingKey: null,
    conversationHistory: [], studentEscalationProfile: savedProfile, currentCategory: 'Registrar',
    CSRF_TOKEN: 'test', concerns: [], renderChatHistory() {},
    confirmImportantAction: async () => true,
    fetch: async () => ({ ok: true, text: async () => template }),
  });
  window.eval(['getOfficeName', 'escapeHtml', 'addEscalationFormMessage', 'handleEscalationSubmit'].map(extract).join('\n'));
  try {
    const defaults = {
      General: 'main', Registrar: 'registrar', Finance: 'cashier', SASO: 'saso', Guidance: 'guidance',
      Library: 'library', 'Property Custodian': 'property', Clinic: 'clinic', ITCD: 'itcd',
      'Human Resources': 'hr', CCS: 'ccs', CBE: 'cbe', CTE: 'cte', CCJE: 'cje',
      'Psychology Department': 'psychology',
    };
    let container;
    for (const [category, slug] of Object.entries(defaults)) {
      window.currentCategory = category;
      container = await window.addEscalationFormMessage();
      const form = container.querySelector('form');
      for (const [name, value] of Object.entries(savedProfile)) {
        assert.equal(form.elements.namedItem(name).value, value, category + ': profile field missing: ' + name);
      }
      assert.equal(form.elements.office.value, slug, category + ': wrong default category.');
      assert.equal(form.elements.office.disabled, false, 'Category cannot be changed.');
      for (const name of ['studentNumber', 'program', 'yearLevel']) assert.equal(form.elements.namedItem(name).readOnly, true);
      for (const label of form.querySelectorAll('label[for]')) {
        assert.ok(window.document.getElementById(label.htmlFor), 'Field label lost its input.');
      }
      for (const option of form.elements.office.options) {
        if (option.value) assert.ok(officeOptions[option.value], 'API rejects form category: ' + option.value);
      }
      assert.equal(await window.addEscalationFormMessage(), container, 'Opening again duplicated the form.');
      container.dataset.submitted = 'true';
    }
    const ids = [...window.document.querySelectorAll('[id]')].map(field => field.id);
    assert.equal(new Set(ids).size, ids.length, 'Escalation forms contain duplicate IDs.');
    window.currentCategory = 'Finance';
    container = await window.addEscalationFormMessage('registrar', 'Transcript request');
    const form = container.querySelector('form');
    assert.equal(form.elements.office.value, 'registrar', 'Explicit office override was ignored.');
    assert.equal(form.elements.subject.value, 'Transcript request');
    form.elements.office.value = 'library';
    form.elements.concern.value = 'Please help me with my library account.';
    for (const name of ['fullName', 'email', 'phone', 'office', 'subject', 'concern']) {
      Object.defineProperty(form, name, { value: form.elements.namedItem(name) });
    }
    let submitted;
    window.fetch = async (url, options) => {
      assert.equal(url, 'api/submit_inquiry.php');
      submitted = JSON.parse(options.body);
      return { json: async () => ({ success: true, inquiry_id: 42, urgency_priority: 'Low' }) };
    };
    await window.handleEscalationSubmit({ target: form, preventDefault() {}, stopPropagation() {} }, container);
    assert.equal(submitted.office, 'library', 'Submission ignored the changed category.');
    assert.equal(submitted.email, profile.email);
    assert.equal(submitted.phone, profile.mobile_number);
    assert.equal(window.concerns[0].office, 'Library');
    assert.equal(container.dataset.submitted, 'true');

    window.fetch = async () => ({ ok: true, text: async () => template });
    window.studentEscalationProfile = legacyProfile;
    container = await window.addEscalationFormMessage();
    assert.equal(container.querySelector('[name=program]').value, '');
    assert.equal(container.querySelector('[name=phone]').readOnly, false, 'Missing mobile number cannot be entered.');
    assert.equal(container.querySelector('[name=studentNumber]').required, false, 'Missing legacy academic details block escalation.');
    container.dataset.submitted = 'true';

    let finishFetch;
    window.fetch = () => new Promise(resolve => { finishFetch = resolve; });
    const pending = window.addEscalationFormMessage();
    window.chatLoadRequestId++;
    const count = window.document.querySelectorAll('.escalation-msg').length;
    finishFetch({ ok: true, text: async () => template });
    await pending;
    assert.equal(window.document.querySelectorAll('.escalation-msg').length, count, 'Stale form loaded into a different chat.');
    console.log('Escalation profile prefill, all category defaults, editable routing, legacy profiles, labels, and race checks passed.');
  } finally {
    window.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
