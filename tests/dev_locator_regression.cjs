const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { JSDOM } = require('jsdom');

(async () => {
  const runtime = path.join(__dirname, '.runtime');
  fs.mkdirSync(runtime, { recursive: true });
  const directory = fs.mkdtempSync(path.join(runtime, 'locator-'));
  const helper = path.resolve(__dirname, '../app/helpers/dev_locator.php').replaceAll('\\', '/');
  const fixture = `<?php
function env(string $key, $default = null) {
    return ['APP_ENV' => 'local', 'APP_DEBUG' => 'true'][$key] ?? $default;
}
require '${helper}';
$level = ob_get_level();
enable_dev_locator();
if ($level !== ob_get_level()) {
    http_response_code(500);
    exit('Locator created a duplicate buffer.');
}
$prefix = '<!doctype html><html><head><title>Locator test</title></head><body>';
$input = '<input type="email" id="email" name="email" value="';
echo $prefix . str_repeat(' ', 4096 - strlen($prefix) - strlen($input)) . $input;
echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
echo '"><p id="after">After input</p></body></html>';
`;
  fs.writeFileSync(path.join(directory, 'index.php'), fixture);
  const reservation = net.createServer();
  await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
  const port = reservation.address().port;
  await new Promise(resolve => reservation.close(resolve));
  const server = spawn(process.env.PHP_BINARY || 'php', [
    '-d', 'output_buffering=4096', '-S', '127.0.0.1:' + port, '-t', directory,
  ], { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
  let output = '';
  server.stdout.on('data', chunk => { output += chunk; });
  server.stderr.on('data', chunk => { output += chunk; });
  const stopped = new Promise(resolve => {
    server.once('close', resolve);
    server.once('error', error => { output += error.message; });
  });
  try {
    const url = 'http://127.0.0.1:' + port + '/';
    let ready = false;
    for (let attempt = 0; attempt < 50; attempt++) {
      try {
        await fetch(url, { signal: AbortSignal.timeout(1000) });
        ready = true;
        break;
      } catch {
        await new Promise(resolve => setTimeout(resolve, 100));
      }
    }
    assert.ok(ready, 'PHP test server did not start: ' + output);
    for (const email of ['', 'student@example.com', 'quoted"&value@example.com']) {
      const response = await fetch(url, { method: 'POST', body: new URLSearchParams({ email }) });
      assert.equal(response.status, 200);
      const dom = new JSDOM(await response.text());
      const document = dom.window.document;
      assert.equal(document.querySelector('#email').value, email, 'Locator changed the email value.');
      assert.equal(document.querySelector('#after').textContent, 'After input');
      const comments = document.createTreeWalker(document, dom.window.NodeFilter.SHOW_COMMENT);
      let locatorComments = 0;
      while (comments.nextNode()) {
        if (comments.currentNode.data.includes('PHP Locator: Tracking file:')) locatorComments++;
      }
      assert.equal(locatorComments, 1, 'Locator debug output is not a single HTML comment.');
      assert.ok(document.body.hasAttribute('data-php-file'), 'Locator no longer annotates the page.');
      dom.window.close();
    }
    console.log('PHP Locator full-page buffering regression passed.');
  } finally {
    server.kill();
    await stopped;
    assert.ok(path.resolve(directory).startsWith(path.resolve(runtime) + path.sep));
    fs.rmSync(directory, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
