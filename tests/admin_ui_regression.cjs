const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

module.exports = async html => {
  const dom = new JSDOM(html, { url: 'http://helpdesk.test/dashboard_admin.php', runScripts: 'outside-only', pretendToBeVisual: true });
  const { window } = dom;
  const { document } = window;
  const $ = id => document.getElementById(id);
  let checks = 0;
  const check = (condition, message) => { assert.ok(condition, message); checks++; };
  const calls = [];
  const downloads = [];
  const turn = () => new Promise(resolve => setTimeout(resolve, 15));
  const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; };
  const fixtureStaff = [
    { user_id: 10, first_name: '<script>unsafe()</script>', last_name: 'Staff', email: 'safe@example.test', office_id: 1, office_name: 'Registrar', is_active: 1, open_count: 2, last_login_at: null },
    { user_id: 11, first_name: 'Other', last_name: 'Staff', email: 'other@example.test', office_id: 2, office_name: 'Cashier', is_active: 0, open_count: 0, last_login_at: '2026-10-01 10:00:00' },
    { user_id: 12, first_name: 'Active', last_name: 'Staff', email: 'active@example.test', office_id: 1, office_name: 'Registrar', is_active: 1, open_count: 0 }
  ];
  const knowledge = [{ entry_id: 1, title: '<img src=x onerror=unsafe()>', content: '<script>unsafe()</script> registration', status: 'Draft', office_id: null, updated_at: '2026-10-03 12:00:00', editor_name: 'Admin' }];
  const concern = { inquiry_id: 20, subject: '<script>unsafe()</script> Concern', description: '<img src=x onerror=unsafe()>', status: 'On Hold', office_id: 1, office_name: 'Registrar', student_name: 'Student', assigned_staff_id: 12, created_at: '2026-10-03 10:00:00', replies: [{ staff_name: 'Staff', sender_role: 'staff', message: '<script>unsafe()</script> Reply', created_at: '2026-10-03 12:00:00' }] };
  let postGate;
  let concernGates;
  let blobGate;
  const jsonResponse = data => ({ ok: true, headers: { get: () => 'application/json' }, json: async () => ({ success: true, ...data }) });
  window.AbortSignal = AbortSignal;
  window.matchMedia = () => ({ matches: false, addEventListener() {} });
  window.HTMLDialogElement.prototype.showModal = function () { this.setAttribute('open', ''); };
  window.HTMLDialogElement.prototype.close = function () { this.removeAttribute('open'); this.dispatchEvent(new window.Event('close')); };
  window.URL.createObjectURL = () => 'blob:test'; window.URL.revokeObjectURL = () => {};
  window.HTMLAnchorElement.prototype.click = function () { downloads.push(this.download); };
  window.open = url => { calls.push({ print: url }); };
  window.fetch = async (url, options = {}) => {
    const target = new URL(url, window.location.href);
    calls.push({ url: target, options, body: options.body ? JSON.parse(options.body) : null });
    if (options.method === 'POST') { if (postGate) await postGate.promise; return jsonResponse({}); }
    if (target.pathname.endsWith('admin_report.php')) {
      if (target.searchParams.get('format') === 'csv') return { ok: true, headers: { get: () => 'text/csv' }, blob: async () => { if (blobGate) await blobGate.promise; return new Blob(['test']); } };
      return jsonResponse({ title: 'Concerns report', period: '2026-10-01 to 2026-10-03', generated_at: '2026-10-03T12:00:00', total: 1, headers: ['Subject'], rows: [['<script>unsafe()</script>']], filters: { type: target.searchParams.get('type'), from: target.searchParams.get('from'), to: target.searchParams.get('to'), office_id: null, category: '', status: '' } });
    }
    const view = target.searchParams.get('view');
    if (view === 'staff') return jsonResponse({ items: fixtureStaff });
    if (view === 'knowledge') return jsonResponse({ items: knowledge });
    if (view === 'concern') return jsonResponse({ item: concern });
    if (view === 'concerns') {
      if (concernGates?.length) return concernGates.shift().promise;
      return jsonResponse({ items: [concern], total: 1, page: 1, pages: 1 });
    }
    return jsonResponse(window.ADMIN_BOOTSTRAP.overview);
  };
  try {
    for (const script of document.querySelectorAll('script:not([src])')) window.eval(script.textContent);
    const source = fs.readFileSync(path.resolve(__dirname, '../public/assets/js/dashboard_admin.js'), 'utf8');
    window.eval(source.replace(/\}\)\(\);\s*$/, 'window.__adminTest = {state, openStaff, openKnowledge, openConcern, loadView, renderStaff, renderKnowledge, confirmDeletion, reportType, generateReport, invalidateReport, downloadReport};})();'));
    const api = window.__adminTest;
    await turn();
    check($('officeWorkload').children.length > 0, 'Overview office data did not render.');
    window.location.hash = 'staff'; await turn();
    check($('view-staff').hidden === false && $('view-overview').hidden, 'Navigation did not switch views.');
    check($('breadcrumbView').textContent === 'Staff Accounts', 'Breadcrumb did not follow navigation.');
    check($('staffRows').querySelectorAll('tr').length === 3, 'Staff rows did not render.');
    check(!$('staffRows').querySelector('script') && $('staffRows').textContent.includes('<script>'), 'Staff names were not escaped.');
    $('staffSearch').value = 'Active'; $('staffSearch').dispatchEvent(new window.Event('input'));
    check($('staffRows').querySelectorAll('tr').length === 1 && $('staffRows').textContent.includes('Active Staff'), 'Staff search failed.');
    $('staffSearch').value = ''; $('staffOffice').value = '2'; $('staffStatus').value = '0'; api.renderStaff();
    check($('staffRows').textContent.includes('Other Staff') && !$('staffRows').textContent.includes('Active Staff'), 'Staff office/status filter failed.');
    $('staffOffice').value = ''; $('staffStatus').value = ''; api.renderStaff();
    api.openStaff(10);
    check($('staffDialog').open && $('staffForm').elements.first_name.value.includes('<script>'), 'Edit staff did not load the correct record.');
    check(!$('staffForm').elements.password.required && $('staffForm').elements.password.value === '', 'Edit exposed or required an existing password.');
    $('staffDialog').close(); api.openStaff();
    check($('staffForm').elements.password.required && $('staffForm').elements.user_id.value === '', 'New staff form reused the previous account.');
    for (const [key, value] of Object.entries({ first_name: 'New', last_name: 'Staff', email: 'new@example.test', office_id: '1', password: 'strong-password' })) $('staffForm').elements[key].value = value;
    postGate = deferred();
    $('staffForm').dispatchEvent(new window.Event('submit', { cancelable: true }));
    const pendingCount = calls.filter(call => call.body?.action === 'create_staff').length;
    $('staffForm').dispatchEvent(new window.Event('submit', { cancelable: true }));
    check(calls.filter(call => call.body?.action === 'create_staff').length === pendingCount, 'Repeated submit created duplicate requests.');
    document.querySelector('[data-close="staffDialog"]').click();
    check($('staffDialog').open, 'Pending form closed during a write.');
    check(calls.find(call => call.body?.action === 'create_staff').options.headers['X-CSRF-Token'].length > 0, 'Mutation omitted CSRF token.');
    postGate.resolve(); postGate = null; await turn();
    check(!$('staffDialog').open && $('staffForm').elements.password.value === '', 'Completed staff save did not clear credentials.');
    check($('adminToast').textContent === 'Staff account saved.', 'Successful save gave no feedback.');
    window.location.hash = 'knowledge'; await turn();
    check($('knowledgeEntries').querySelectorAll('article').length === 1, 'Knowledge entries did not render.');
    check(!$('knowledgeEntries').querySelector('script') && !$('knowledgeEntries').querySelector('[onerror]'), 'Knowledge entries executed markup.');
    api.openKnowledge(1);
    check($('knowledgeForm').elements.content.value.includes('<script>') && $('knowledgeForm').elements.status.value === 'Draft', 'Knowledge editor lost content/status.');
    $('knowledgeDialog').close(); api.confirmDeletion('staff', 10);
    check($('deleteMessage').textContent.includes('past replies') && api.state.deletion.user_id === 10, 'Staff delete warning/target was incorrect.');
    $('deleteDialog').close();
    await api.openConcern(20);
    check($('concernDialog').open && $('assignmentStaff').options.length === 3, 'Assignment did not include same-office active staff.');
    check(!$('assignmentStaff').querySelector('option[value="11"]') && $('assignmentStaff').value === '12', 'Assignment included inactive/wrong-office staff or lost the selection.');
    check(!$('concernDetail').querySelector('script') && !$('concernDetail').querySelector('[onerror]'), 'Concern text/replies were not escaped.');
    $('concernDialog').close(); concern.status = 'Resolved'; await api.openConcern(20);
    check($('saveAssignment').disabled && $('assignmentStaff').disabled, 'Resolved concern was editable.');
    $('concernDialog').close(); concern.status = 'On Hold';
    window.location.hash = 'concerns'; await turn();
    const first = deferred(); const second = deferred(); concernGates = [first, second];
    const oldRequest = api.loadView('concerns'); const newRequest = api.loadView('concerns');
    second.resolve(jsonResponse({ items: [{ ...concern, subject: 'Newest result' }], total: 1, page: 1, pages: 1 })); await newRequest;
    first.resolve(jsonResponse({ items: [{ ...concern, subject: 'Stale result' }], total: 1, page: 1, pages: 1 })); await oldRequest;
    check($('concernRows').textContent.includes('Newest result') && !$('concernRows').textContent.includes('Stale result'), 'Stale request overwrote new filters.');
    window.location.hash = 'reports'; await turn();
    const form = $('reportForm'); form.elements.from.value = '2026-10-01'; form.elements.to.value = '2026-10-03';
    form.elements.type.value = 'inquiries'; api.reportType();
    check($('reportOfficeField').hidden && form.elements.office_id.disabled && !$('reportCategoryField').hidden, 'Inquiry report fields were not scoped correctly.');
    form.elements.type.value = 'concerns'; api.reportType();
    await api.generateReport();
    check(!$('reportPreview').hidden && $('reportEmpty').hidden, 'Generated report preview was hidden.');
    check(!$('reportRows').querySelector('script') && $('reportRows').textContent.includes('<script>'), 'Report preview executed markup.');
    blobGate = deferred(); const exporting = api.downloadReport(); await turn();
    api.invalidateReport(); blobGate.resolve(); await exporting;
    check(downloads[0] === 'helpdesk-concerns-2026-10-01-2026-10-03.csv', 'Download lost generated filters when form changed.');
    check($('reportPreview').hidden && api.state.report === null, 'Changed filters retained stale download controls.');
    let loggedOut = false; $('logoutForm').requestSubmit = () => { loggedOut = true; };
    $('logoutForm').dispatchEvent(new window.Event('submit', { cancelable: true }));
    check($('logoutConfirm').open && !loggedOut, 'Logout bypassed confirmation.');
    $('confirmLogout').click(); check(loggedOut && !$('logoutConfirm').open, 'Confirmed logout did not submit.');
    console.log('Admin DOM regression checks passed: ' + checks);
    return checks;
  } finally { window.close(); }
};
