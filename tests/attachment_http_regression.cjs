const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const net = require('node:net');
const path = require('node:path');

(async () => {
  const php = process.argv[2];
  const fixtures = JSON.parse(process.argv[3]);
  assert.match(fixtures.database, /^helpdeskcrmc_test_[a-f0-9]{12}$/);
  const root = path.resolve(__dirname, '..');
  const probe = net.createServer();
  await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  const base = `http://127.0.0.1:${port}`;
  const server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/router.php'], {
    cwd: root, windowsHide: true, stdio: 'ignore', env: { ...process.env, HELPDESK_TEST_DB: fixtures.database },
  });
  let checks = 0;
  const request = async (route, account, { status = 200, body, headers = {} } = {}) => {
    const response = await fetch(base + route, {
      method: body === undefined ? 'GET' : 'POST', body, redirect: 'manual', signal: AbortSignal.timeout(15000),
      headers: { ...(account ? { Cookie: account.cookie, 'X-CSRF-Token': account.token } : {}), ...headers },
    });
    assert.equal(response.status, status, `${route}: ${await response.clone().text()}`);
    checks++;
    return response;
  };
  const uploadBody = (bytes, name, type = 'application/pdf') => {
    const form = new FormData();
    form.append('file', new Blob([bytes], { type }), name);
    return form;
  };
  async function login(name) {
    const page = await request('/login.php');
    const html = await page.text();
    const token = html.match(/name="_csrf_token" value="([^"]+)"/)[1];
    const cookie = page.headers.getSetCookie()[0].split(';')[0];
    const response = await request('/login.php', { cookie, token }, {
      status: 302, body: new URLSearchParams({ email: name + '@example.test', password: 'attachment-test', _csrf_token: token }),
    });
    return { token, cookie: response.headers.getSetCookie()[0].split(';')[0] };
  }
  try {
    for (let attempt = 0; attempt < 50; attempt++) {
      try { await fetch(base + '/login.php'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); }
    }
    await request('/api/upload_file.php', null, { status: 401, body: new FormData() });
    await request('/api/download_attachment.php?id=1', null, { status: 401 });
    const accounts = {};
    for (const name of ['student', 'otherStudent', 'staff', 'otherStaff', 'unassigned', 'admin']) accounts[name] = await login(name);
    await request('/api/upload_file.php', accounts.student, { status: 403, body: new FormData(), headers: { 'X-CSRF-Token': 'invalid' } });
    await request('/api/upload_file.php', accounts.student, { status: 400, body: new FormData() });
    await request('/api/upload_file.php', accounts.student, { status: 400, body: uploadBody('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'unsafe.svg', 'image/svg+xml') });
    await request('/api/upload_file.php', accounts.student, { status: 400, body: uploadBody(new Uint8Array(5 * 1024 * 1024 + 1), 'large.pdf') });
    await request('/api/upload_file.php', accounts.student, { status: 400, body: uploadBody('', 'empty.pdf') });
    const bytes = '%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n';
    const uploaded = await (await request('/api/upload_file.php', accounts.student, { body: uploadBody(bytes, 'staff proof.pdf') })).json();
    assert.equal(uploaded.success, true);
    assert.ok(Number.isInteger(uploaded.attachment_id));
    const id = uploaded.attachment_id;
    const makeZip = valid => {
      const code = '$path = "tests/.runtime/attachment-word-" . bin2hex(random_bytes(6)) . ".zip";'
        + 'try { $zip = new PharData($path); $zip["[Content_Types].xml"] = "<Types/>";'
        + '$zip["' + (valid ? 'word/document.xml' : 'unrelated.txt') + '"] = "<document/>"; unset($zip); echo base64_encode(file_get_contents($path)); }'
        + 'finally { if (is_file($path)) unlink($path); }';
      return Buffer.from(execFileSync(php, ['-r', code], { cwd: root, encoding: 'utf8' }), 'base64');
    };
    const word = await (await request('/api/upload_file.php', accounts.student, {
      body: uploadBody(makeZip(true), 'details.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    })).json();
    assert.equal(word.success, true, 'A valid Word package was rejected.');
    await request('/api/upload_file.php', accounts.student, { status: 400, body: uploadBody(makeZip(false), 'disguised.docx', 'application/zip') });
    await request('/api/download_attachment.php?id=' + id, accounts.student, { status: 404 });
    const code = 'require "app/config/env.php"; putenv("DB_NAME=' + fixtures.database + '"); require "app/models/Inquiry.php";'
      + '$id = (new Inquiry())->create(["student_id"=>' + fixtures.student + ',"office_id"=>1,"subject"=>"Uploaded file","message"=>"Staff proof","attachment_ids"=>[' + id + ',' + word.attachment_id + ']]); echo json_encode(["inquiry_id"=>$id]);';
    const linked = JSON.parse(execFileSync(php, ['-r', code], { cwd: root, encoding: 'utf8' }));
    for (const name of ['student', 'staff', 'admin']) {
      const downloaded = await request('/api/download_attachment.php?id=' + id, accounts[name]);
      assert.equal(await downloaded.text(), bytes, 'Download bytes were changed or polluted.');
      assert.match(downloaded.headers.get('content-disposition'), /attachment;.*staff%20proof\.pdf/);
      assert.equal(downloaded.headers.get('x-content-type-options'), 'nosniff');
    }
    for (const name of ['otherStudent', 'otherStaff', 'unassigned']) await request('/api/download_attachment.php?id=' + id, accounts[name], { status: 403 });
    const staffThread = await (await request('/api/get_responses.php?inquiry_id=' + linked.inquiry_id, accounts.staff)).json();
    assert.equal(staffThread.attachments[0].attachment_id, id);
    assert.equal(staffThread.attachments[0].file_name, 'staff proof.pdf');
    assert.equal(staffThread.attachments[1].file_name, 'details.docx');
    assert.equal(staffThread.attachments[0].file_path, undefined);
    await request('/api/get_responses.php?inquiry_id=' + linked.inquiry_id, accounts.otherStaff, { status: 403 });
    const studentThread = await (await request('/api/get_student_concerns_with_replies.php', accounts.student)).json();
    assert.equal(studentThread.concerns.find(item => item.inquiry_id === linked.inquiry_id).attachments[0].attachment_id, id);
    await request('/api/download_attachment.php?id[]=1', accounts.student, { status: 400 });
    await request('/api/download_attachment.php?id=99999999', accounts.student, { status: 404 });
    await request('/api/submit_inquiry.php', accounts.student, { status: 400, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ concern: 'Test', office: 'registrar', attachment_ids: ['1'] }) });
    const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aFYkAAAAASUVORK5CYII=', 'base64');
    const imageUpload = await (await request('/api/upload_file.php', accounts.student, {
      body: uploadBody(image, 'support.png', 'image/png'),
    })).json();
    // This test-only safety phrase takes the local classifier path, with no external AI request.
    const submitted = await (await request('/api/submit_inquiry.php', accounts.student, {
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ subject: 'Attachment API fixture', concern: 'Test fixture: medical emergency report.', office: 'cashier', attachment_ids: [imageUpload.attachment_id] }),
    })).json();
    assert.equal(submitted.success, true, 'The real submission API did not accept uploaded attachments.');
    const forwarded = await (await request('/api/get_responses.php?inquiry_id=' + submitted.inquiry_id, accounts.otherStaff)).json();
    assert.equal(forwarded.attachments[0].file_name, 'support.png', 'Submitted file was not forwarded to the chosen office.');
    const downloadedImage = await request('/api/download_attachment.php?id=' + imageUpload.attachment_id, accounts.otherStaff);
    assert.deepEqual(Buffer.from(await downloadedImage.arrayBuffer()), image);
    await request('/api/download_attachment.php?id=' + imageUpload.attachment_id, accounts.staff, { status: 403 });
    console.log(`Attachment upload, staff/student listing, exact downloads, size/type validation, CSRF, and access checks passed: ${checks}`);
  } finally {
    server.kill();
    if (server.exitCode === null) await new Promise(resolve => server.once('exit', resolve));
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
