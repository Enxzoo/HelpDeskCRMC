'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
  const icon = name => `<img class="icon" src="assets/icons/${name}.svg" alt="" width="18" height="18">`;
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const state = { view: 'overview', staff: [], knowledge: [], pages: { staff: 1, knowledge: 1, concerns: 1 }, request: {}, report: null, concernRequest: 0, deletion: null };
  const viewNames = { overview: 'Overview', staff: 'Staff Accounts', knowledge: 'Knowledge Base', concerns: 'Concerns', reports: 'Reports' };
  let toastTimer;
  let searchTimer;
  let refreshTimer;

  async function request(params, body, endpoint = 'admin_workspace.php') {
    const query = new URLSearchParams(params);
    const response = await fetch(`api/${endpoint}?${query}`, {
      method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
      headers: body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf } : {},
      body: body ? JSON.stringify(body) : undefined, signal: AbortSignal.timeout(15000)
    });
    let data;
    try { data = await response.json(); } catch { throw new Error('Unable to read the server response. Please try again.'); }
    if (!response.ok || !data.success) throw new Error(data.error || 'Unable to complete this request.');
    return data;
  }

  function alertError(error) {
    $('adminAlertText').textContent = error.message || 'Something went wrong. Please try again.';
    $('adminAlert').hidden = false;
  }
  function toast(message) {
    clearTimeout(toastTimer);
    $('adminToast').textContent = message;
    $('adminToast').hidden = false;
    toastTimer = setTimeout(() => { $('adminToast').hidden = true; }, 4500);
  }
  function date(value, includeTime = false) {
    if (!value) return 'Never';
    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) return String(value);
    return parsed.toLocaleString(undefined, includeTime ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' } : { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function badge(label) {
    const classes = { 'In Progress': 'progress', 'On Hold': 'hold', Pending: 'pending', Resolved: 'resolved', Active: 'active', Inactive: 'inactive', Published: 'published', Draft: 'draft', High: 'high', 'Critical/Urgent': 'high' };
    return `<span class="badge ${classes[label] || ''}">${escape(label)}</span>`;
  }
  function emptyRow(columns, message) { return `<tr><td colspan="${columns}" class="empty-cell">${escape(message)}</td></tr>`; }
  function editActions(kind, id, name) {
    return `<div class="actions"><button type="button" class="icon-button" data-edit-${kind}="${Number(id)}" title="Edit ${escape(name)}" aria-label="Edit ${escape(name)}">${icon('pencil')}</button><button type="button" class="icon-button delete-button" data-delete-${kind}="${Number(id)}" title="Delete ${escape(name)}" aria-label="Delete ${escape(name)}">${icon('trash-2')}</button></div>`;
  }
  function pager(id, page, pages, total, size = 20) {
    $(id).innerHTML = `<span>${total ? `${(page - 1) * size + 1}-${Math.min(page * size, total)} of ${total}` : '0 records'}</span><button type="button" class="icon-button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''} aria-label="Previous page" title="Previous page">${icon('chevron-left')}</button><span>${page} / ${pages}</span><button type="button" class="icon-button" data-page="${page + 1}" ${page >= pages ? 'disabled' : ''} aria-label="Next page" title="Next page">${icon('chevron-right')}</button>`;
  }
  function renderOverview(data) {
    const s = data.stats;
    const values = [s.total - s.resolved, s.unassigned, s.resolved, s.active_staff];
    $('overviewMetrics').querySelectorAll('article>strong').forEach((node, i) => { node.textContent = values[i]; });
    $('overviewMetrics').querySelectorAll('article>small')[2].textContent = `${s.total ? Math.round(s.resolved / s.total * 100) : 0}% of submitted concerns`;
    $('navConcernCount').textContent = s.unassigned;
    $('navConcernCount').hidden = !s.unassigned;
    const max = Math.max(1, ...data.workload.map(item => Number(item.open_count)));
    $('officeWorkload').innerHTML = data.workload.map(item => `<div class="workload-row"><button type="button" data-office="${Number(item.office_id)}" title="View ${escape(item.office_name)} concerns">${escape(item.office_name)}</button><div class="workload-track" role="meter" aria-label="${escape(item.office_name)} open concerns" aria-valuemin="0" aria-valuemax="${max}" aria-valuenow="${Number(item.open_count)}"><div class="workload-fill" style="width:${Number(item.open_count) / max * 100}%"></div></div><strong>${Number(item.open_count)}</strong></div>`).join('') || '<p class="empty-state">No active offices.</p>';
    $('statusSummary').innerHTML = [['Pending', 'pending', s.pending], ['In Progress', '', s.in_progress], ['On Hold', 'hold', s.on_hold], ['Resolved', 'resolved', s.resolved]].map(([label, css, count]) => `<div class="status-row"><span class="status-label"><i class="status-dot ${css}" aria-hidden="true"></i>${label}</span><strong>${count}</strong></div>`).join('');
    $('systemTotals').innerHTML = `<a href="#reports">Ben inquiries <strong>${s.inquiries}</strong></a><a href="#knowledge">Published entries <strong>${s.published}</strong></a>`;
    $('overviewQueue').innerHTML = data.unassigned.slice(0, 5).map(item => `<tr><td><button type="button" class="row-title" data-concern="${Number(item.inquiry_id)}">${escape(item.subject)}</button><small>INQ-${Number(item.inquiry_id)} &middot; ${escape(item.student_name)}</small></td><td>${escape(item.office_name)}</td><td>${badge(item.priority)}</td><td>${escape(date(item.created_at))}</td><td class="align-right"><button type="button" class="button" data-concern="${Number(item.inquiry_id)}">Assign staff</button></td></tr>`).join('') || emptyRow(5, 'All open concerns are assigned.');
  }
  function renderStaff() {
    const search = $('staffSearch').value.toLowerCase();
    const office = $('staffOffice').value;
    const status = $('staffStatus').value;
    const all = state.staff.filter(item => `${item.first_name} ${item.last_name} ${item.email}`.toLowerCase().includes(search) && (!office || String(item.office_id) === office) && (!status || String(Number(item.is_active)) === status));
    const pages = Math.max(1, Math.ceil(all.length / 10));
    const page = state.pages.staff = Math.min(state.pages.staff, pages);
    $('staffSummary').textContent = `${state.staff.length} staff members | ${state.staff.filter(item => Number(item.is_active)).length} active`;
    $('staffRows').innerHTML = all.slice((page - 1) * 10, page * 10).map(item => `<tr><td><strong>${escape(item.first_name)} ${escape(item.last_name)}</strong><small>${escape(item.email)}</small></td><td>${escape(item.office_name || 'No office')}</td><td>${badge(Number(item.is_active) ? 'Active' : 'Inactive')}</td><td>${Number(item.open_count)}</td><td>${escape(date(item.last_login_at))}</td><td>${editActions('staff', item.user_id, 'staff account')}</td></tr>`).join('') || emptyRow(6, state.staff.length ? 'No staff match these filters.' : 'No staff accounts yet.');
    pager('staffPagination', page, pages, all.length, 10);
  }
  function renderKnowledge() {
    const search = $('knowledgeSearch').value.toLowerCase();
    const office = $('knowledgeOffice').value;
    const status = $('knowledgeStatus').value;
    const all = state.knowledge.filter(item => `${item.title} ${item.content}`.toLowerCase().includes(search) && (!office || (office === 'global' ? item.office_id === null : String(item.office_id) === office)) && (!status || item.status === status));
    const pages = Math.max(1, Math.ceil(all.length / 8));
    const page = state.pages.knowledge = Math.min(state.pages.knowledge, pages);
    $('knowledgeSummary').textContent = `${state.knowledge.length} entries | ${state.knowledge.filter(item => item.status === 'Published').length} published`;
    $('knowledgeEntries').innerHTML = all.slice((page - 1) * 8, page * 8).map(item => `<article class="knowledge-entry"><div><h2>${escape(item.title)}</h2><p>${escape(item.content)}</p><div class="knowledge-meta">${badge(item.status)}<span>${escape(item.office_name || 'All offices')}</span><span>Updated ${escape(date(item.updated_at))}</span><span>${escape(item.editor_name)}</span></div></div>${editActions('knowledge', item.entry_id, 'knowledge entry')}</article>`).join('') || '<p class="empty-state">' + (state.knowledge.length ? 'No entries match these filters.' : 'No knowledge entries yet.') + '</p>';
    pager('knowledgePagination', page, pages, all.length, 8);
  }
  function concernFilters() { return { ...Object.fromEntries(new FormData($('concernFilters'))), page: state.pages.concerns }; }
  function renderConcerns(data) {
    state.pages.concerns = data.page;
    $('concernsSummary').textContent = `${data.total} ${data.total === 1 ? 'concern' : 'concerns'}`;
    $('concernRows').innerHTML = data.items.map(item => `<tr><td><button type="button" class="row-title" data-concern="${Number(item.inquiry_id)}">${escape(item.subject)}</button><small>INQ-${Number(item.inquiry_id)} &middot; ${escape(item.student_name)}</small></td><td>${escape(item.office_name)}</td><td>${badge(item.priority)}</td><td>${badge(item.status)}</td><td>${escape(item.staff_name || 'Unassigned')}</td><td class="align-right"><button type="button" class="icon-button" data-concern="${Number(item.inquiry_id)}" title="Review INQ-${Number(item.inquiry_id)}" aria-label="Review INQ-${Number(item.inquiry_id)}">${icon('eye')}</button></td></tr>`).join('') || emptyRow(6, 'No concerns match these filters.');
    pager('concernPagination', data.page, data.pages, data.total);
  }
  async function loadView(view = state.view) {
    if (view === 'reports') return;
    const sequence = state.request[view] = (state.request[view] || 0) + 1;
    const section = $(`view-${view}`);
    section.setAttribute('aria-busy', 'true');
    try {
      const data = await request({ view, ...(view === 'concerns' ? concernFilters() : {}) });
      if (state.request[view] !== sequence) return;
      if (view === 'overview') renderOverview(data);
      if (view === 'staff') { state.staff = data.items; renderStaff(); }
      if (view === 'knowledge') { state.knowledge = data.items; renderKnowledge(); }
      if (view === 'concerns') renderConcerns(data);
    } catch (error) { if (state.request[view] === sequence && state.view === view) alertError(error); }
    finally { if (state.request[view] === sequence) section.setAttribute('aria-busy', 'false'); }
  }
  function sidebar(open) {
    $('adminSidebar').classList.toggle('open', open);
    $('sidebarBackdrop').hidden = !open;
    $('menuToggle').setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('navigation-open', open);
    $('adminSidebar').inert = window.matchMedia('(max-width: 900px)').matches && !open;
  }
  function switchView() {
    state.view = Object.hasOwn(viewNames, location.hash.slice(1)) ? location.hash.slice(1) : 'overview';
    document.querySelectorAll('.admin-view').forEach(node => { node.hidden = node.id !== `view-${state.view}`; });
    document.querySelectorAll('.admin-nav a').forEach(node => { if (node.dataset.view === state.view) node.setAttribute('aria-current', 'page'); else node.removeAttribute('aria-current'); });
    $('breadcrumbView').textContent = viewNames[state.view];
    $('adminAlert').hidden = true;
    sidebar(false);
    void loadView();
  }
  function formError(form, error = null) {
    const node = form.querySelector('.form-error');
    node.hidden = !error;
    node.textContent = error ? error.message : '';
  }
  function openStaff(id) {
    const form = $('staffForm'); form.reset(); formError(form);
    const item = state.staff.find(person => Number(person.user_id) === Number(id));
    form.elements.user_id.value = item ? item.user_id : '';
    for (const key of ['first_name', 'last_name', 'email', 'office_id']) form.elements[key].value = item ? item[key] ?? '' : '';
    form.elements.is_active.checked = item ? Boolean(Number(item.is_active)) : true;
    form.elements.password.required = !item;
    $('staffPasswordLabel').textContent = item ? 'New password (optional)' : 'Password (12+ characters)';
    $('staffDialogTitle').textContent = item ? 'Edit staff account' : 'Add staff account';
    $('staffDialog').showModal();
  }
  function openKnowledge(id) {
    const form = $('knowledgeForm'); form.reset(); formError(form);
    const item = state.knowledge.find(entry => Number(entry.entry_id) === Number(id));
    form.elements.entry_id.value = item ? item.entry_id : '';
    for (const key of ['title', 'content', 'office_id', 'status']) form.elements[key].value = item ? item[key] ?? '' : key === 'status' ? 'Draft' : '';
    $('knowledgeDialogTitle').textContent = item ? 'Edit knowledge entry' : 'Add knowledge entry';
    $('knowledgeDialog').showModal();
  }
  async function openConcern(id) {
    const sequence = ++state.concernRequest;
    try {
      const data = await request({ view: 'concern', inquiry_id: id });
      const staff = await request({ view: 'staff' });
      if (state.concernRequest !== sequence) return;
      state.staff = staff.items;
      const item = data.item;
      $('concernDialogId').textContent = `INQ-${Number(item.inquiry_id)}`;
      $('concernDialogTitle').textContent = item.subject;
      $('concernDetail').innerHTML = `<div class="concern-meta">${badge(item.status)}${badge(item.priority_override || item.ai_priority || 'Needs triage')}<span>${escape(item.office_name)}</span><span>${escape(item.student_name)}${item.student_number ? ` (${escape(item.student_number)})` : ''}</span><span>${escape(date(item.created_at, true))}</span><span>${escape([item.student_program, item.student_year_level ? `Year ${item.student_year_level}` : '', item.student_section, item.student_academic_year, item.student_semester].filter(Boolean).join(' | ') || 'Academic details not recorded')}</span></div><p class="concern-description">${escape(item.description)}</p><section class="concern-replies"><h3>Conversation</h3>${item.replies.map(reply => `<article class="concern-reply"><div><strong>${escape(reply.staff_name)} (${escape(reply.sender_role)})</strong><span>${escape(reply.created_at ? date(reply.created_at, true) : '')}</span></div><p>${escape(reply.message)}</p></article>`).join('') || '<p class="empty-state">No replies yet.</p>'}</section>`;
      const available = state.staff.filter(person => Number(person.is_active) && Number(person.office_id) === Number(item.office_id));
      $('assignmentStaff').innerHTML = '<option value="">Unassigned</option>' + available.map(person => `<option value="${Number(person.user_id)}">${escape(person.first_name)} ${escape(person.last_name)} (${Number(person.open_count)} open)</option>`).join('');
      if (item.assigned_staff_id && !available.some(person => Number(person.user_id) === Number(item.assigned_staff_id))) {
        $('assignmentStaff').insertAdjacentHTML('beforeend', `<option value="${Number(item.assigned_staff_id)}" disabled>Previous staff (unavailable)</option>`);
      }
      $('assignmentStaff').value = item.assigned_staff_id ?? '';
      $('assignmentStaff').disabled = item.status === 'Resolved';
      $('saveAssignment').disabled = item.status === 'Resolved';
      $('assignmentForm').elements.inquiry_id.value = item.inquiry_id;
      formError($('assignmentForm'));
      if (!$('concernDialog').open) $('concernDialog').showModal();
    } catch (error) { if (state.concernRequest === sequence) alertError(error); }
  }
  function confirmDeletion(kind, id) {
    state.deletion = { action: `delete_${kind}`, [kind === 'staff' ? 'user_id' : 'entry_id']: Number(id) };
    const item = kind === 'staff' ? state.staff.find(row => Number(row.user_id) === Number(id)) : state.knowledge.find(row => Number(row.entry_id) === Number(id));
    if (!item) return;
    $('deleteTitle').textContent = kind === 'staff' ? 'Delete staff account?' : 'Delete knowledge entry?';
    $('deleteMessage').textContent = kind === 'staff' ? `Remove ${item.first_name} ${item.last_name}? They will no longer be able to sign in. Open assignments will be released; past replies and resolved concerns will be retained.` : `Delete "${item.title}"? This entry will be permanently removed from the knowledge base.`;
    formError($('deleteForm'));
    $('deleteDialog').showModal();
  }
  async function submitForm(event, body, dialog, message) {
    event.preventDefault();
    const form = event.currentTarget;
    if (form.dataset.busy === 'true') return;
    const button = form.querySelector('button[type="submit"]');
    form.dataset.busy = 'true'; button.disabled = true; formError(form);
    try {
      await request({}, body);
      $(dialog).close(); toast(message);
      await loadView();
      if (state.view !== 'overview') void loadView('overview');
    } catch (error) { formError(form, error); }
    finally { form.dataset.busy = 'false'; button.disabled = false; }
  }
  function reportParams() { return Object.fromEntries(new FormData($('reportForm'))); }
  function reportType() {
    const inquiry = $('reportForm').elements.type.value === 'inquiries';
    for (const [name, visible] of [['Office', !inquiry], ['Status', !inquiry], ['Category', inquiry]]) {
      const label = $(`report${name}Field`); label.hidden = !visible; label.querySelector('select').disabled = !visible;
    }
    invalidateReport();
  }
  function invalidateReport() {
    state.report = null; ++state.request.reports;
    $('reportPreview').hidden = true; $('reportEmpty').hidden = false;
  }
  async function generateReport(event) {
    event?.preventDefault();
    if (!$('reportForm').reportValidity()) return;
    const sequence = state.request.reports = (state.request.reports || 0) + 1;
    const button = $('reportForm').querySelector('button[type="submit"]'); button.disabled = true;
    button.dataset.request = String(sequence);
    $('adminAlert').hidden = true;
    try {
      const data = await request(reportParams(), null, 'admin_report.php');
      if (state.request.reports !== sequence) return;
      state.report = Object.fromEntries(Object.entries(data.filters).map(([key, value]) => [key, value ?? '']));
      $('reportEmpty').hidden = true; $('reportPreview').hidden = false;
      $('reportPreviewTitle').textContent = data.title;
      $('reportPreviewMeta').textContent = `${data.period} | ${data.total} records | Generated ${date(data.generated_at, true)}`;
      $('reportHead').innerHTML = '<tr>' + data.headers.map(value => `<th>${escape(value)}</th>`).join('') + '</tr>';
      $('reportRows').innerHTML = data.rows.map(row => '<tr>' + row.map(value => `<td>${escape(value ?? '')}</td>`).join('') + '</tr>').join('') || emptyRow(data.headers.length, 'No records for this period.');
      $('reportFooter').textContent = data.total > data.rows.length ? `Preview: ${data.rows.length} of ${data.total} records` : `${data.total} records`;
    } catch (error) { if (state.request.reports === sequence) alertError(error); }
    finally { if (button.dataset.request === String(sequence)) button.disabled = false; }
  }
  async function downloadReport() {
    if (!state.report) return;
    const filters = { ...state.report };
    const button = $('downloadReport'); button.disabled = true;
    try {
      const response = await fetch('api/admin_report.php?' + new URLSearchParams({ ...filters, format: 'csv' }), { cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(30000) });
      if (!response.ok || !response.headers.get('Content-Type')?.includes('text/csv')) {
        const data = await response.json(); throw new Error(data.error || 'Report download failed.');
      }
      const blob = await response.blob();
      const url = URL.createObjectURL(blob); const link = document.createElement('a');
      link.href = url; link.download = `helpdesk-${filters.type}-${filters.from}-${filters.to}.csv`;
      document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) { alertError(error); } finally { button.disabled = false; }
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button || button.disabled) return;
    if (button.dataset.close) { if (button.closest('form')?.dataset.busy !== 'true') $(button.dataset.close).close(); }
    if (button.dataset.editStaff) openStaff(button.dataset.editStaff);
    if (button.dataset.editKnowledge) openKnowledge(button.dataset.editKnowledge);
    if (button.dataset.deleteStaff) confirmDeletion('staff', button.dataset.deleteStaff);
    if (button.dataset.deleteKnowledge) confirmDeletion('knowledge', button.dataset.deleteKnowledge);
    if (button.dataset.concern) void openConcern(button.dataset.concern);
    if (button.dataset.office) { $('concernOffice').value = button.dataset.office; $('concernStatus').value = ''; $('concernAssignment').value = ''; $('concernSearch').value = ''; state.pages.concerns = 1; location.hash = 'concerns'; }
    if (button.dataset.page) {
      const page = Number(button.dataset.page); if (page < 1) return;
      state.pages[state.view] = page;
      if (state.view === 'staff') renderStaff(); else if (state.view === 'knowledge') renderKnowledge(); else void loadView();
    }
  });
  $('addStaff').addEventListener('click', () => openStaff());
  $('addKnowledge').addEventListener('click', () => openKnowledge());
  $('staffForm').addEventListener('submit', event => {
    const form = event.currentTarget;
    void submitForm(event, { ...Object.fromEntries(new FormData(form)), action: form.elements.user_id.value ? 'update_staff' : 'create_staff', is_active: form.elements.is_active.checked }, 'staffDialog', 'Staff account saved.');
  });
  $('knowledgeForm').addEventListener('submit', event => {
    const form = event.currentTarget;
    void submitForm(event, { ...Object.fromEntries(new FormData(form)), action: form.elements.entry_id.value ? 'update_knowledge' : 'create_knowledge' }, 'knowledgeDialog', 'Knowledge entry saved.');
  });
  $('assignmentForm').addEventListener('submit', event => { void submitForm(event, { ...Object.fromEntries(new FormData(event.currentTarget)), action: 'assign_concern' }, 'concernDialog', 'Assignment saved.'); });
  $('deleteForm').addEventListener('submit', event => { void submitForm(event, state.deletion, 'deleteDialog', 'Deleted successfully.'); });
  for (const kind of ['staff', 'knowledge']) {
    for (const suffix of ['Search', 'Office', 'Status']) $(`${kind}${suffix}`).addEventListener(suffix === 'Search' ? 'input' : 'change', () => { state.pages[kind] = 1; if (kind === 'staff') renderStaff(); else renderKnowledge(); });
  }
  $('concernFilters').addEventListener('submit', event => { event.preventDefault(); clearTimeout(searchTimer); state.pages.concerns = 1; void loadView('concerns'); });
  $('concernSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { state.pages.concerns = 1; void loadView('concerns'); }, 300); });
  for (const id of ['concernOffice', 'concernStatus', 'concernAssignment']) $(id).addEventListener('change', () => { state.pages.concerns = 1; void loadView('concerns'); });
  $('viewUnassigned').addEventListener('click', () => { $('concernAssignment').value = 'unassigned'; $('concernStatus').value = ''; $('concernOffice').value = ''; $('concernSearch').value = ''; state.pages.concerns = 1; });
  $('reportForm').addEventListener('submit', generateReport);
  $('reportForm').addEventListener('change', event => { if (event.target.name === 'type') reportType(); else invalidateReport(); });
  $('downloadReport').addEventListener('click', downloadReport);
  $('printReport').addEventListener('click', () => { if (state.report) window.open('api/admin_report.php?' + new URLSearchParams({ ...state.report, format: 'print' }), '_blank', 'noopener'); });
  $('refreshView').addEventListener('click', () => { $('adminAlert').hidden = true; if (state.view === 'reports' && state.report) void generateReport(); else void loadView(); });
  $('dismissAlert').addEventListener('click', () => { $('adminAlert').hidden = true; });
  $('menuToggle').addEventListener('click', () => sidebar(!$('adminSidebar').classList.contains('open')));
  $('sidebarBackdrop').addEventListener('click', () => { sidebar(false); $('menuToggle').focus(); });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') sidebar(false); });
  document.querySelectorAll('.admin-dialog').forEach(dialog => {
    dialog.addEventListener('cancel', event => { if (dialog.querySelector('form')?.dataset.busy === 'true') event.preventDefault(); });
    dialog.addEventListener('close', () => { if (dialog.id === 'concernDialog') ++state.concernRequest; if (dialog.id === 'staffDialog') $('staffForm').elements.password.value = ''; });
  });
  const logoutForm = $('logoutForm');
  logoutForm.addEventListener('submit', event => { if (logoutForm.dataset.confirmed !== 'true') { event.preventDefault(); $('logoutConfirm').showModal(); } });
  $('cancelLogout').addEventListener('click', () => $('logoutConfirm').close());
  $('confirmLogout').addEventListener('click', () => { logoutForm.dataset.confirmed = 'true'; $('logoutConfirm').close(); logoutForm.requestSubmit(); });
  document.addEventListener('helpdesk:open-notification', event => { if (event.detail?.inquiry_id) { event.preventDefault(); location.hash = 'concerns'; void openConcern(event.detail.inquiry_id); } });
  document.addEventListener('helpdesk:notification', () => {
    clearTimeout(refreshTimer); refreshTimer = setTimeout(() => { if (document.querySelector('.admin-dialog[open]')) return; void loadView('overview'); if (state.view === 'concerns' || state.view === 'staff') void loadView(); }, 600);
  });
  function refreshVisibleView() {
    if (document.hidden || document.querySelector('.admin-dialog[open]') || state.view === 'reports') return;
    void loadView();
    if (state.view !== 'overview') void loadView('overview');
  }
  window.setInterval(refreshVisibleView, 30000);
  document.addEventListener('visibilitychange', refreshVisibleView);
  window.addEventListener('hashchange', switchView);
  window.matchMedia('(max-width: 900px)').addEventListener('change', () => sidebar(false));
  state.request.reports = 0;
  renderOverview(window.ADMIN_BOOTSTRAP.overview);
  switchView();
  const inquiry = new URLSearchParams(location.search).get('inquiry_id');
  if (inquiry && /^\d+$/.test(inquiry)) { location.hash = 'concerns'; void openConcern(inquiry); }
})();
