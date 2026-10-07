const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const root = path.resolve(__dirname, '..');
const template = execFileSync(process.env.PHP_BINARY || 'php', [path.join(root, 'public/assets/components/escalation-form.php')], { encoding: 'utf8' });
const moduleSource = fs.readFileSync(path.join(root, 'public/assets/js/inquiry_attachments.js'), 'utf8');
const extract = (page, name) => {
  const source = fs.readFileSync(path.join(root, 'public', page), 'utf8');
  const match = source.match(new RegExp('^([ \\t]*)(?:async )?function ' + name + '\\([^]*?^\\1}', 'm'));
  assert.ok(match, 'Missing function: ' + name);
  return match[0].replace(/<\?= (?:addslashes\()?dev_locator_attributes\(__FILE__, __LINE__(?: - \d+)?\)\)? \?>/g, '');
};

(async () => {
  const dom = new JSDOM(`<div id="container">${template}</div><div id="staffThread"></div>`, { runScripts: 'outside-only', url: 'http://helpdesk.test/' });
  const { window } = dom;
  try {
    const ui = window.eval(moduleSource + '\nInquiryAttachments;');
    window.InquiryAttachments = ui;
    window.HTMLElement.prototype.scrollIntoView = () => {};
    const form = window.document.querySelector('form');
    ui.bindForm(form);
    const input = form.elements.attachment;
    const select = files => {
      Object.defineProperty(input, 'files', { value: files, configurable: true });
      input.dispatchEvent(new window.Event('change'));
    };
    let pickerOpened = false;
    input.click = () => { pickerOpened = true; };
    form.querySelector('.esc-file-btn').click();
    assert.equal(pickerOpened, true, 'Choose files did not open the input.');
    const proof = new window.File(['%PDF-1.4'], 'proof.pdf', { type: 'application/pdf' });
    const extra = new window.File(['%PDF-1.4'], '<img src=x onerror=alert(1)>.pdf', { type: 'application/pdf' });
    select([proof, extra]);
    assert.equal(form.querySelectorAll('.esc-file-item').length, 2);
    assert.ok(form.querySelector('.esc-file-list').textContent.includes(extra.name));
    assert.equal(form.querySelectorAll('.esc-file-name img').length, 0, 'Filename created injected HTML.');
    form.querySelector('.esc-file-remove').click();
    assert.equal(form.escalationAttachments.items.length, 1, 'Remove did not remove the selected file.');
    select([new window.File(['<svg/>'], 'script.svg')]);
    assert.equal(form.escalationAttachments.items.length, 1);
    assert.match(form.querySelector('[data-error-msg]').textContent, /choose a JPG/);
    select([new window.File([new Uint8Array(5 * 1024 * 1024 + 1)], 'large.pdf')]);
    assert.match(form.querySelector('[data-error-msg]').textContent, /5 MB/);
    select(Array.from({ length: 5 }, () => proof));
    assert.match(form.querySelector('[data-error-msg]').textContent, /five files/);
    select([proof]);
    ui.setBusy(form, true);
    assert.equal(form.querySelector('.esc-file-btn').disabled, true);
    assert.ok([...form.querySelectorAll('.esc-file-remove')].every(button => button.disabled));

    Object.assign(window, {
      CSRF_TOKEN: 'csrf-test', concerns: [], renderChatHistory() {},
      confirmImportantAction: async () => true,
      showEscalationDuplicateNotice: form => { form.dataset.confirmedDuplicateOf = '42'; },
      console: { ...console, error() {} },
    });
    window.eval(['getOfficeName', 'handleEscalationSubmit'].map(name => extract('dashboard_student.php', name)).join('\n'));
    for (const [name, value] of Object.entries({ fullName: 'Test Student', email: 'student@example.test', phone: '09171234567', office: 'registrar', subject: 'File support', concern: 'Please review the attached files.' })) {
      form.elements.namedItem(name).value = value;
      Object.defineProperty(form, name, { value: form.elements.namedItem(name) });
    }
    ui.setBusy(form, false);
    let uploads = 0;
    let submissions = 0;
    let failSecondFile = true;
    let duplicate = true;
    const payloads = [];
    window.fetch = async (url, options) => {
      if (url.includes('upload_file')) {
        uploads++;
        assert.equal(options.headers['X-CSRF-Token'], 'csrf-test');
        assert.ok(options.body instanceof window.FormData);
        assert.ok(options.body.get('file') instanceof window.File);
        if (uploads === 2 && failSecondFile) return { ok: false, json: async () => ({ success: false, error: 'Upload unavailable' }) };
        return { ok: true, json: async () => ({ success: true, attachment_id: 100 + uploads }) };
      }
      submissions++;
      payloads.push(JSON.parse(options.body));
      return { ok: true, json: async () => duplicate
        ? { success: false, duplicate_warning: true }
        : { success: true, inquiry_id: 42, urgency_priority: 'Normal' } };
    };
    const event = { target: form, preventDefault() {}, stopPropagation() {} };
    const container = window.document.getElementById('container');
    await window.handleEscalationSubmit(event, container);
    assert.equal(submissions, 0, 'Concern was submitted despite an upload failure.');
    assert.equal(form.style.display, '');
    assert.equal(form.escalationAttachments.items.length, 2, 'Upload failure lost selected files.');
    assert.equal(form.escalationAttachments.items[0].attachmentId, 101, 'A successful upload was lost after another file failed.');
    assert.match(form.querySelector('[data-error-msg]').textContent, /Upload unavailable/);
    assert.equal(form.querySelector('.esc-file-btn').disabled, false, 'Failure left the picker disabled.');
    failSecondFile = false;
    await window.handleEscalationSubmit(event, container);
    assert.equal(uploads, 3, 'Retry reuploaded files that were already ready.');
    assert.deepEqual(payloads[0].attachment_ids, [101, 103]);
    assert.equal(form.style.display, '', 'Duplicate warning hid the form.');
    duplicate = false;
    await window.handleEscalationSubmit(event, container);
    assert.equal(uploads, 3, 'Duplicate confirmation uploaded files again.');
    assert.deepEqual(payloads[1].attachment_ids, [101, 103]);
    assert.equal(container.dataset.submitted, 'true');
    assert.equal(container.querySelectorAll('[data-success-msg]').length, 1, 'Submission duplicated the success UI.');
    assert.equal(container.querySelectorAll('[data-success-msg] .inquiry-attachments a').length, 2);

    Object.assign(window, { thread: window.document.getElementById('staffThread'), responseRequestId: 0, currentInquiryId: 42 });
    const csrf = window.document.createElement('meta');
    csrf.name = 'csrf-token'; csrf.content = 'csrf-test'; window.document.head.append(csrf);
    window.eval(['formatDate', 'createMessage', 'loadInquiryResponses'].map(name => extract('dashboard_staff.php', name)).join('\n'));
    window.thread.append(window.createMessage('student', 'Student', 'Review my proof', '2026-10-07 12:00:00'));
    window.fetch = async () => ({ ok: true, json: async () => ({ responses: [], attachments: ui.selected(form) }) });
    await window.loadInquiryResponses(42);
    const links = window.thread.querySelectorAll('.inquiry-attachments a');
    assert.equal(links.length, 2, 'Staff thread did not display submitted files.');
    assert.equal(links[0].getAttribute('href'), 'api/download_attachment.php?id=101');
    assert.ok(window.thread.textContent.includes(extra.name), 'Staff thread lost the filename.');
    assert.equal(window.thread.querySelectorAll('[onerror]').length, 0);
    assert.equal(ui.create([{ attachment_id: 'javascript:alert(1)', file_name: 'bad' }]).children.length, 0);
    await window.loadInquiryResponses(42);
    assert.equal(window.thread.querySelectorAll('.inquiry-attachments').length, 1, 'Reload duplicated attachment links.');
    console.log('File picker, filename safety, remove controls, limits, upload failure/retry, duplicate confirmation, success UI, and staff rendering checks passed.');
  } finally {
    window.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
