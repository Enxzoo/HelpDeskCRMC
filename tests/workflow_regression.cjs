const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function functionsFrom(page, names) {
  const source = fs.readFileSync(path.resolve(__dirname, '../public', page), 'utf8');
  return names.map(name => {
    const match = source.match(new RegExp('^(?:async )?function ' + name + '\\([^]*?^}', 'm'));
    assert.ok(match, 'Missing function: ' + name);
    return match[0];
  }).join('\n');
}

function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return { promise, resolve };
}

function element() {
  return {
    value: '', textContent: '', innerHTML: '', disabled: false, hidden: false,
    style: {}, dataset: {}, children: [], scrollHeight: 10,
    classList: { add() {}, remove() {}, toggle() {} },
    appendChild(child) { this.children.push(child); },
    setAttribute() {}, removeAttribute() {}, focus() {}
  };
}

function documentFixture() {
  const elements = new Map();
  const get = id => {
    if (!elements.has(id)) elements.set(id, element());
    return elements.get(id);
  };
  return {
    get,
    document: {
      getElementById: get,
      createElement: element,
      querySelector: selector => selector.startsWith('[data-inquiry-id=') ? null : get(selector),
      querySelectorAll: () => []
    }
  };
}

async function main() {
  let checks = 0;
  function check(condition, message) {
    assert.ok(condition, message);
    checks++;
  }
  const studentDom = documentFixture();
  studentDom.get('threadReplySection').querySelector = () => studentDom.get('#threadReplySection .thread-reply-btn');
  studentDom.get('threadFeedbackSection').querySelector = () => studentDom.get('#threadFeedbackSection .thread-reply-btn');
  const concerns = [1, 2].map(id => ({
    inquiry_id: id, office: 'Registrar', subject: 'Concern ' + id,
    description: 'Description ' + id, status: id === 1 ? 'On Hold' : 'Resolved',
    created_at: '2026-10-02 10:00:00', replies: []
  }));
  const student = {
    console, Date, ...studentDom, CSRF_TOKEN: 'test', STUDENT_INITIALS: 'TS',
    activeThreadInquiryId: null, threadLoadRequestId: 0, currentCategory: 'Registrar',
    chatLoadRequestId: 0, categoryLogoPaths: {}, defaultCategoryLogoPath: 'test.png',
    escapeHtml: text => text, setTimeout: callback => callback(), alert: assert.fail,
    fetch: async () => ({ ok: true, json: async () => ({ success: true, concerns }) })
  };
  vm.createContext(student);
  new vm.Script(functionsFrom('dashboard_student.php', [
    'hideThreadView', 'openThreadView', 'sendThreadReply', 'submitThreadFeedback', 'sendHeroMessage'
  ])).runInContext(student);

  await student.openThreadView(2);
  const selected = studentDom.get('.thread-emoji-btn.selected');
  selected.dataset.rating = '5';
  const feedbackSection = studentDom.get('threadFeedbackSection');
  feedbackSection.innerHTML = 'Original feedback controls';
  student.fetch = async () => ({ ok: true, json: async () => ({ success: true }) });
  await student.submitThreadFeedback();
  check(feedbackSection.innerHTML === 'Original feedback controls', 'Feedback must preserve reusable inputs.');
  check(studentDom.get('threadActionMessage').textContent === 'Thank you for your feedback!', 'Feedback confirmation missing.');
  student.fetch = async () => ({ ok: true, json: async () => ({ success: true, concerns }) });
  await student.openThreadView(2);
  check(feedbackSection.style.display === 'block' && !studentDom.get('#threadFeedbackSection .thread-reply-btn').disabled,
    'Opening another concern must restore feedback controls.');

  await student.openThreadView(1);
  studentDom.get('threadReplyInput').value = 'Original reply';
  const reply = deferred();
  let replyCalls = 0;
  student.fetch = async route => {
    if (route.includes('submit_student_reply')) { replyCalls++; return reply.promise; }
    return { ok: true, json: async () => ({ success: true, concerns }) };
  };
  const pendingReply = student.sendThreadReply();
  await student.sendThreadReply();
  check(replyCalls === 1, 'Repeated student reply clicks must not create duplicate requests.');
  await student.openThreadView(2);
  studentDom.get('threadReplyInput').value = 'New draft';
  const messageCount = studentDom.get('threadMessages').children.length;
  reply.resolve({ ok: true, json: async () => ({ success: true }) });
  await pendingReply;
  check(studentDom.get('threadMessages').children.length === messageCount
    && studentDom.get('threadReplyInput').value === 'New draft', 'A delayed reply must not change another thread.');

  const older = deferred();
  const newer = deferred();
  let loadCalls = 0;
  student.fetch = () => (++loadCalls === 1 ? older.promise : newer.promise);
  const olderLoad = student.openThreadView(1);
  const newerLoad = student.openThreadView(2);
  newer.resolve({ ok: true, json: async () => ({ success: true, concerns }) });
  await newerLoad;
  older.resolve({ ok: true, json: async () => ({ success: true, concerns }) });
  await olderLoad;
  check(student.activeThreadInquiryId === 2, 'A stale thread load must not replace the latest selection.');
  const navigationLoad = deferred();
  student.fetch = () => navigationLoad.promise;
  const pendingLoad = student.openThreadView(1);
  student.hideThreadView();
  navigationLoad.resolve({ ok: true, json: async () => ({ success: true, concerns }) });
  await pendingLoad;
  check(student.activeThreadInquiryId === null && studentDom.get('threadView').style.display === 'none',
    'A late load must not reopen a closed thread.');

  let chatLoad = deferred();
  let sentText;
  student.showChatView = category => {
    student.currentCategory = category;
    student.chatLoadRequestId++;
    studentDom.get('chatInput').disabled = true;
    return chatLoad.promise;
  };
  student.sendChatMessage = async () => {
    check(!studentDom.get('chatInput').disabled, 'Hero message sent before chat finished loading.');
    sentText = studentDom.get('chatInput').value;
  };
  const heroMessage = student.sendHeroMessage('Office hours?');
  check(sentText === undefined, 'Hero search must wait for its conversation.');
  studentDom.get('chatInput').disabled = false;
  chatLoad.resolve();
  await heroMessage;
  check(sentText === 'Office hours?', 'Slow chat loads must preserve the first message.');
  chatLoad = deferred();
  const staleHeroMessage = student.sendHeroMessage('Stale message');
  student.chatLoadRequestId++;
  chatLoad.resolve();
  await staleHeroMessage;
  check(sentText === 'Office hours?', 'Hero search must not send into another conversation.');

  const staffDom = documentFixture();
  const staff = {
    console, Date, ...staffDom, currentInquiryId: 1, staffReplySending: false, staffResolving: false,
    inquiriesData: [{ inquiry_id: 1, status: 'Pending' }, { inquiry_id: 2, status: 'In Progress' }],
    alert: assert.fail, reorderConcernCards() {}, applyQueueFilters() {},
    priorityClass: value => value.toLowerCase(), renderSelectedInquiry: assert.fail,
    confirmImportantAction: async () => true
  };
  staffDom.get('meta[name="csrf-token"]').content = 'test';
  vm.createContext(staff);
  new vm.Script(functionsFrom('dashboard_staff.php', [
    'statusKey', 'updateConcernStatus', 'updateConcernPriority', 'sendReply', 'resolveConcern'
  ])).runInContext(staff);
  staffDom.get('replyText').value = 'Original response';
  staffDom.get('statusSelect').value = 'On Hold';
  const response = deferred();
  let sentPayload;
  let staffCalls = 0;
  staff.fetch = async (route, options) => {
    staffCalls++;
    sentPayload = JSON.parse(options.body);
    return response.promise;
  };
  const pendingStaffReply = staff.sendReply({ preventDefault() {} });
  await staff.sendReply({ preventDefault() {} });
  check(staffCalls === 1, 'Repeated staff submit events must not create duplicate responses.');
  staff.currentInquiryId = 2;
  staffDom.get('replyText').value = 'New response draft';
  staffDom.get('statusSelect').value = 'In Progress';
  staffDom.get('detailStatus').textContent = 'In Progress';
  response.resolve({ ok: true, json: async () => ({ success: true }) });
  await pendingStaffReply;
  check(sentPayload.inquiry_id === 1 && sentPayload.message === 'Original response' && sentPayload.status === 'On Hold',
    'Staff requests must use the original concern and composer values.');
  check(staff.inquiriesData[0].status === 'On Hold' && staff.inquiriesData[1].status === 'In Progress'
    && staffDom.get('detailStatus').textContent === 'In Progress'
    && staffDom.get('replyText').value === 'New response draft', 'A delayed response must not alter another concern.');

  const confirmation = deferred();
  staff.currentInquiryId = 1;
  staff.confirmImportantAction = () => confirmation.promise;
  staff.fetch = async (route, options) => {
    sentPayload = JSON.parse(options.body);
    return { ok: true, json: async () => ({ success: true }) };
  };
  const resolving = staff.resolveConcern();
  staff.currentInquiryId = 2;
  confirmation.resolve(true);
  await resolving;
  check(sentPayload.inquiry_id === 1 && staff.inquiriesData[0].status === 'Resolved'
    && staff.inquiriesData[1].status === 'In Progress', 'Resolve confirmation must stay attached to its original concern.');
  check(!staffDom.get('resolveButton').disabled && !staff.staffResolving, 'Resolve action must release controls after switching concerns.');
  staffDom.get('detailUrgency').textContent = 'Normal';
  staff.updateConcernPriority(staff.inquiriesData[0], 'High', 'High', 'Tester');
  check(staffDom.get('detailUrgency').textContent === 'Normal' && staff.inquiriesData[0].urgency_priority === 'High',
    'A delayed urgency update must not alter another concern detail.');
  console.log('Workflow regression checks passed: ' + checks);
}

main().catch(error => { console.error(error); process.exitCode = 1; });
