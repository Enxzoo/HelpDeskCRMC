const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { JSDOM } = require('jsdom');
const ui = vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../public/assets/js/ben_chat.js'), 'utf8') + '\nBenChatUI;', { TextDecoder });
const encoder = new TextEncoder();
function response(text, chunkSize = 1) {
  const bytes = encoder.encode(text);
  return new Response(new ReadableStream({
    start(controller) {
      for (let index = 0; index < bytes.length; index += chunkSize) controller.enqueue(bytes.slice(index, index + chunkSize));
      controller.close();
    }
  }), { headers: { 'Content-Type': 'text/event-stream; charset=utf-8' } });
}
(async () => {
  const updates = [];
  const answer = 'Sige, tabangan tika. Caf\u00e9';
  const wire = ': keepalive\n\nevent: start\ndata: {"started":true}\n\n'
    + 'event: delta\r\ndata: {"text":"Sige, "}\r\n\r\n'
    + 'event: delta\ndata: ' + JSON.stringify({ text: 'tabangan tika. Caf\u00e9' }) + '\n\n'
    + 'event: done\ndata: ' + JSON.stringify({ success: true, answer });
  const result = await ui.readResponse(response(wire), text => updates.push(text));
  assert.equal(result.answer, answer);
  assert.deepEqual(updates, ['Sige, ', answer], 'Text was not displayed incrementally or UTF-8 was damaged.');
  const legacy = await ui.readResponse(new Response(JSON.stringify({ success: true, answer: 'Legacy answer' }), { headers: { 'Content-Type': 'application/json' } }), () => {});
  assert.equal(legacy.answer, 'Legacy answer');
  await assert.rejects(ui.readResponse(response('event: delta\ndata: {"text":"Partial only"}\n\n'), () => {}), /interrupted/);
  const failed = await ui.readResponse(response('event: delta\ndata: {"text":"Partial"}\n\nevent: error\ndata: {"error":"Unavailable"}\n\n'), () => {});
  assert.equal(failed.success, false);
  assert.equal(failed.error, 'Unavailable');
  await assert.rejects(ui.readResponse(response('event: done\ndata: {"success":true}\n\n'), () => {}), /Invalid completed/);
  await assert.rejects(ui.readResponse(response('event: delta\ndata: not-json\n\n'), () => {}));
  assert.equal(ui.suggestions('', 'General', 'Dili ko ka-login sa portal')[1].label, 'Dili pa maka-log in');
  assert.equal(ui.suggestions('Nakatabang ba kini sa imong pangutana?')[0].label, 'Oo, salamat');
  assert.equal(ui.suggestions('Did that answer your concern?')[0].label, 'Yes, thanks');
  const dashboard = fs.readFileSync(path.join(__dirname, '../public/dashboard_student.php'), 'utf8');
  const extract = name => {
    const match = dashboard.match(new RegExp('^([ \\t]*)function ' + name + '\\([^]*?^\\1}', 'm'));
    assert.ok(match, 'Missing renderer: ' + name);
    return match[0];
  };
  const dom = new JSDOM('<div id="chatThreadInner"></div>', { runScripts: 'outside-only' });
  dom.window.scrollChatToBottom = () => {};
  dom.window.eval(['escapeHtml', 'renderMarkdown', 'addBenMessage', 'addUserMessage'].map(extract).join('\n'));
  let message;
  const dangerous = '<img src=x onerror=alert(1)> **Safe bold**';
  await ui.readResponse(response('event: delta\ndata: ' + JSON.stringify({ text: dangerous }) + '\n\nevent: done\ndata: ' + JSON.stringify({ success: true, answer: dangerous }) + '\n\n'), partial => {
    message ||= dom.window.addBenMessage('');
    message.querySelector('.bubble').innerHTML = dom.window.renderMarkdown(partial);
  });
  assert.equal(dom.window.document.querySelectorAll('.msg.ben').length, 1, 'Streaming created duplicate bubbles.');
  assert.ok(!message.querySelector('.bubble img') && message.querySelector('.bubble strong').textContent === 'Safe bold', 'Streaming bypassed safe Markdown rendering.');
  assert.ok(message.querySelector('.bubble').textContent.includes('<img src=x onerror=alert(1)>'));
  const style = dom.window.document.createElement('style');
  style.textContent = fs.readFileSync(path.join(__dirname, '../public/assets/css/student/chat-messages.css'), 'utf8');
  dom.window.document.head.appendChild(style);
  dom.window.addUserMessage(dangerous, false);
  const studentMessage = dom.window.document.querySelector('.msg.user');
  assert.equal(studentMessage.querySelector('.m-avatar'), null, 'Student messages still show a profile avatar.');
  assert.equal(studentMessage.querySelector('.bubble').textContent, dangerous, 'Student text was not escaped correctly.');
  assert.equal(studentMessage.querySelector('.bubble img'), null);
  assert.ok(studentMessage.querySelector('.time').textContent, 'Student timestamp is missing.');
  assert.ok(message.querySelector('.m-avatar img'), 'Ben avatar was removed.');
  const bubbleStyle = dom.window.getComputedStyle(studentMessage.querySelector('.bubble'));
  assert.equal(bubbleStyle.color, 'rgb(255, 255, 255)', 'Student text must be white.');
  const studentBubbleRule = Array.from(style.sheet.cssRules).find(rule => rule.selectorText === '.msg.user .bubble');
  assert.equal(studentBubbleRule.style.getPropertyValue('background'), 'linear-gradient(135deg,var(--amber),var(--amber-dk))', 'Student bubbles must retain the original gold gradient.');
  dom.window.close();
  console.log('Ben streaming, UTF-8, failure, JSON fallback, bilingual suggestions, and student bubble checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
