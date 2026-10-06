const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.resolve(__dirname, '../public/dashboard_student.php'), 'utf8')
  .replace(/<\?=[\s\S]*?\?>/g, 'null');
function extract(name) {
  const match = source.match(new RegExp('^(?:async )?function ' + name + '\\([^]*?^}', 'm'));
  assert.ok(match, 'Missing function: ' + name);
  return match[0];
}
new vm.Script(extract('showChatView'));
let stored = [{ role: 'user', message: 'Hello' }, { role: 'model', message: 'Welcome' }];
const writes = [];
let historyLoads = 0;
const firstKey = 'a'.repeat(32);
const elements = new Map();
function element() {
  const classes = new Set();
  return {
    value: '', disabled: false, style: {}, children: [], innerHTML: '',
    appendChild(child) { this.children.push(child); }, focus() {},
    classList: { add: value => classes.add(value), remove: value => classes.delete(value) },
    addEventListener(type, handler) { this[type] = handler; }
  };
}
const context = {
  console, Date, currentCategory: 'Registrar', chatLoadRequestId: 1,
  currentChatSessionKey: firstKey, chatSessions: [], crypto: require('node:crypto').webcrypto,
  categoryMeta: {}, categoryIconMarkup: category => category, hideThreadView() {}, closeChatHistory() {},
  conversationHistory: [], chatSaveQueue: Promise.resolve(), CSRF_TOKEN: 'test',
  showBenActions() {},
  BenChatUI: {
    clear() {}, suggestions: () => []
  },
  escapeHtml: value => value, renderMarkdown: value => value,
  addBenMessage() {}, scrollChatToBottom() {},
  document: {
    getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
    createElement: element, querySelectorAll: () => []
  },
  async fetch(url, options) {
    if (url.includes('load_chat_session')) {
      historyLoads++;
      const session_key = new URL(url, 'https://example.test').searchParams.get('session_key');
      const category = context.currentCategory;
      return { ok: true, async json() { return { success: true, category, session_key, conversationHistory: structuredClone(stored) }; } };
    }
    const payload = JSON.parse(options.body);
    writes.push(payload);
    stored = payload.conversationHistory;
    return { ok: true, async json() { return { success: true }; } };
  }
};
vm.createContext(context);
new vm.Script(['addUserMessage', 'saveChatHistory', 'loadChatHistory', 'newChatSessionKey', 'showChatGreeting', 'showChatView', 'rememberChatSession', 'renderChatHistory'].map(extract).join('\n')).runInContext(context);
(async () => {
  for (let attempt = 0; attempt < 3; attempt++) {
    await vm.runInContext('loadChatHistory(currentCategory, document.getElementById(\'chatThreadInner\'))', context);
    assert.equal(context.conversationHistory.length, 2);
  }
  assert.equal(writes.length, 0, 'Viewing chat must not save or duplicate messages.');
  context.currentCategory = 'Finance';
  context.conversationHistory = [{ role: 'user', message: 'Finance history' }];
  await vm.runInContext('loadChatHistory(\'Registrar\', document.getElementById(\'chatThreadInner\'), 0)', context);
  assert.equal(context.conversationHistory[0].message, 'Finance history', 'Stale history overwrote current conversation.');
  await Promise.all([
    vm.runInContext('saveChatHistory(\'Finance\', [{ role: \'user\', message: \'First\' }])', context),
    vm.runInContext('saveChatHistory(\'Finance\', [{ role: \'user\', message: \'Second\' }])', context)
  ]);
  assert.equal(stored[0].message, 'Second');
  assert.equal(writes[0].session_key, firstKey, 'A save lost its conversation identity.');
  const savedHistory = structuredClone(stored);
  const loadsBefore = historyLoads;
  await vm.runInContext('showChatView(\'Finance\')', context);
  const financeKey = context.currentChatSessionKey;
  assert.equal(context.conversationHistory.length, 0, 'Choosing a category restored an old chat.');
  assert.equal(historyLoads, loadsBefore, 'Choosing a category fetched history.');
  assert.match(financeKey, /^[a-f0-9]{32}$/);
  assert.deepEqual(stored, savedHistory, 'Opening a fresh chat overwrote saved history.');
  await vm.runInContext('showChatView(\'Finance\')', context);
  assert.notEqual(context.currentChatSessionKey, financeKey, 'Selecting the same category reused its previous chat.');
  context.chatSessions = [{ session_key: firstKey, category: 'Registrar', last_message: 'Previous conversation', updated_at: 1720000000 }];
  vm.runInContext('renderChatHistory()', context);
  await elements.get('chatHistoryList').children.at(-1).click({ preventDefault() {} });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(context.currentChatSessionKey, firstKey, 'History click did not select the saved conversation.');
  assert.equal(historyLoads, loadsBefore + 1, 'History click did not restore messages.');
  assert.equal(context.conversationHistory[0].message, 'Second');
  const fetchBeforeRace = context.fetch;
  let finishRestore;
  context.fetch = () => new Promise(resolve => { finishRestore = resolve; });
  const restoring = vm.runInContext('showChatView(\'Registrar\', \'' + firstKey + '\')', context);
  await new Promise(resolve => setImmediate(resolve));
  await vm.runInContext('showChatView(\'Registrar\')', context);
  const freshKey = context.currentChatSessionKey;
  finishRestore({ ok: true, async json() { return { success: true, category: 'Registrar', session_key: firstKey, conversationHistory: savedHistory }; } });
  await restoring;
  assert.equal(context.currentChatSessionKey, freshKey, 'An old history fetch changed the new conversation identity.');
  assert.equal(context.conversationHistory.length, 0, 'An old history fetch populated a new same-category chat.');
  assert.equal(elements.get('chatInput').disabled, false, 'An old history fetch disabled a new chat.');
  context.fetch = fetchBeforeRace;
  const controls = { chatInput: { value: 'I need to know the office hours', disabled: false, focus() {} }, chatSendBtn: {} };
  const originalFetch = context.fetch;
  let finishReply;
  let submitted;
  let escalationCount = 0;
  context.chatSending = false;
  context.currentCategory = 'Registrar';
  context.currentChatSessionKey = firstKey;
  context.chatLoadRequestId = 2;
  context.conversationHistory = [{ role: 'model', message: 'Welcome' }];
  context.concerns = [];
  context.renderChatHistory = () => {};
  context.addTypingIndicator = () => {};
  context.removeTypingIndicator = () => {};
  context.addEscalationFormMessage = () => { escalationCount++; };
  context.setTimeout = callback => callback();
  context.document.getElementById = id => controls[id] || { appendChild() {} };
  context.fetch = async (url, options) => {
    if (url.includes('ai_chat')) {
      submitted = JSON.parse(options.body);
      return new Promise(resolve => { finishReply = resolve; });
    }
    return originalFetch(url, options);
  };
  new vm.Script(extract('sendChatMessage')).runInContext(context);
  const sending = vm.runInContext('sendChatMessage()', context);
  assert.equal(submitted.history.length, 1, 'Current user message was sent twice.');
  context.currentCategory = 'Finance';
  context.currentChatSessionKey = 'b'.repeat(32);
  context.chatLoadRequestId = 3;
  context.conversationHistory = [{ role: 'user', message: 'Finance history' }];
  finishReply({ async json() { return { success: true, answer: 'Registrar reply' }; } });
  await sending;
  assert.equal(context.conversationHistory[0].message, 'Finance history', 'Pending reply changed another conversation.');
  assert.equal(writes.at(-1).category, 'Registrar', 'Pending reply was saved to another office.');
  assert.equal(writes.at(-1).session_key, firstKey, 'Pending reply was saved into a different conversation.');
  assert.equal(writes.at(-1).conversationHistory.at(-1).message, 'Registrar reply');
  assert.equal(escalationCount, 0);
  context.fetch = async (url, options) => url.includes('ai_chat')
    ? { async json() { return { success: true, answer: 'Office hours' }; } }
    : originalFetch(url, options);
  context.chatSending = false;
  controls.chatInput.value = 'What documents are normally required?';
  await vm.runInContext('sendChatMessage()', context);
  assert.equal(escalationCount, 0, 'Normal wording triggered escalation.');
  console.log('Chat regression checks passed: 26');
})().catch(error => { console.error(error); process.exitCode = 1; });
