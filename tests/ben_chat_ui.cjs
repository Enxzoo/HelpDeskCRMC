const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const dom = new JSDOM('<div id="chatThreadInner"></div>', { runScripts: 'outside-only', url: 'http://localhost/helpdeskcrmc/public/' });
const { window } = dom;
window.eval(fs.readFileSync(path.join(__dirname, '../public/assets/js/ben_chat.js'), 'utf8') + '\nwindow.chatUI = BenChatUI;');
const ui = window.chatUI;
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
function message() {
  const node = window.document.createElement('div');
  node.innerHTML = '<div class="bubble-wrap"><div class="bubble">Original answer</div><span class="time">Now</span></div>';
  window.document.getElementById('chatThreadInner').appendChild(node);
  return node;
}
const selections = [];
const first = message();
ui.render(first, [{ label: 'Requirements', message: 'What are the requirements?' },
  { label: '<img src=x onerror=alert(1)>', message: 'Another question' }], choice => selections.push(choice));
check(first.querySelectorAll('button').length === 2, 'Replies were not rendered.');
check(!first.querySelector('button img'), 'Reply label was interpreted as HTML.');
check(first.querySelector('.bubble').textContent === 'Original answer', 'Suggestions changed Ben response text.');
check(!first.querySelector('.ben-feedback-prompt'), 'An extra feedback caption was added.');
first.querySelector('button').click();
first.querySelector('button').click();
check(selections.length === 1 && selections[0].message === 'What are the requirements?', 'Repeated click sent another message.');
for (const [answer, yes, no] of [
  ['Did that answer your concern?', 'Yes, thanks', 'Not yet'],
  ['Nakatabang ba sa imong pangutana?', 'Oo, salamat', 'Wala pa'],
  ['Nakatulong ba sa tanong mo?', 'Oo, thanks', 'Hindi pa']
]) {
  const node = message();
  ui.render(node, ui.suggestions(answer), choice => selections.push(choice));
  check(window.document.querySelectorAll('.ben-actions').length === 1, 'Stale choices remain active.');
  check(node.querySelectorAll('button').length === 3, 'Missing answer feedback choices.');
  check(node.querySelector('button').textContent === yes, 'Feedback language is wrong.');
  const negative = [...node.querySelectorAll('button')].find(button => button.textContent === no);
  negative.click();
  check(selections.at(-1).message !== 'no' && !selections.at(-1).kind, 'Not yet forced the existing no-to-escalation trigger.');
}
const node = message();
ui.render(node, ui.suggestions('', 'Registrar'), choice => selections.push(choice));
node.querySelector('[data-chat-action="staff"]').click();
check(selections.at(-1).kind === 'staff', 'Staff choice became an ordinary AI request.');
check(ui.suggestions('You\'re welcome!').length === 0, 'Acknowledgement repeated choices.');
check(ui.suggestions('', 'Registrar', 'I need my transcript.')[0].label === 'Requirements', 'Suggestions ignored the current subject.');
ui.clear();
check(!window.document.querySelector('.ben-actions'), 'Typed reply cannot remove old suggestions.');
ui.render(message(), [null, { label: [] }, { label: 'Bad' }], () => {});
check(!window.document.querySelector('.ben-actions'), 'Malformed choices were rendered.');
dom.window.close();
console.log('Compact Ben reply UI assertions passed: ' + checks);
