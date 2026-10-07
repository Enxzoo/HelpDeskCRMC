const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const net = require('node:net');
const path = require('node:path');
const { JSDOM } = require('jsdom');

(async () => {
  // Like CSS Peek, an editor's HTML scanner sees PHP source before PHP executes it.
  for (const [file, selectors] of [
    ['login.php', ['.login-card', '.submit-btn', '.password-toggle', '.create-account-btn', '#email', '#password']],
    ['register.php', ['#studentRegistration', '.registration-next', '.registration-submit', '.password-toggle', '#email', '#confirm_password']],
  ]) {
    const source = new JSDOM(fs.readFileSync(path.resolve(__dirname, '../public', file), 'utf8'));
    for (const selector of selectors) {
      assert.ok(source.window.document.querySelector(selector), `PHP locator hides ${selector} from HTML tooling in ${file}.`);
    }
    source.window.close();
  }
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
  const markedFixture = `<?php
function env(string $key, $default = null) {
    $mode = $_GET['mode'] ?? 'local';
    return ['APP_ENV' => $mode === 'production' ? 'production' : 'local', 'APP_DEBUG' => $mode === 'disabled' ? 'false' : 'true'][$key] ?? $default;
}
require '${helper}';
?>
<!doctype html>
<html <?= dev_locator_attributes(__FILE__, __LINE__) ?>><head><title>Exact locations</title></head>
<body <?= dev_locator_attributes(__FILE__, __LINE__) ?>>


<h1 id="title" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Title after blank lines</h1>
<?php require __DIR__ . '/partial.php'; ?>
<?php foreach ([1, 2] as $number): ?>
<button
  class="repeat" <?= dev_locator_attributes(__FILE__, __LINE__ - 1) ?> type="button">Repeated <?= $number ?></button>
<?php endforeach; ?>
<?= dev_locator_script() ?>
<?= dev_locator_script() ?>
</body></html>
`;
  const partialFixture = `<section id="partial" <?= dev_locator_attributes(__FILE__, __LINE__) ?>>Included component</section>`;
  fs.writeFileSync(path.join(directory, 'markers.php'), markedFixture);
  fs.writeFileSync(path.join(directory, 'partial.php'), partialFixture);
  fs.mkdirSync(path.join(directory, 'api'));
  fs.writeFileSync(path.join(directory, 'api/markers.php'), markedFixture);
  fs.writeFileSync(path.join(directory, 'api/partial.php'), partialFixture);
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
    const marked = new JSDOM(await (await fetch(url + 'markers.php')).text());
    const document = marked.window.document;
    for (const [selector, tag] of [['#title', '<h1'], ['.repeat', '<button']]) {
      const line = markedFixture.split('\n').findIndex(text => text.startsWith(tag)) + 1;
      for (const element of document.querySelectorAll(selector)) {
        assert.equal(Number(element.dataset.phpLine), line, 'Incorrect exact source line: ' + selector);
        assert.equal(element.dataset.phpFile, path.join(directory, 'markers.php').replaceAll('\\', '/'));
      }
    }
    assert.equal(document.querySelector('#partial').dataset.phpFile, path.join(directory, 'partial.php').replaceAll('\\', '/'));
    assert.equal(document.querySelector('#partial').dataset.phpLine, '1');
    assert.equal(document.querySelectorAll('script[data-source-root]').length, 1, 'Development runtime loaded more than once.');
    marked.window.close();
    for (const route of ['markers.php?mode=production', 'markers.php?mode=disabled', 'api/markers.php']) {
      const clean = new JSDOM(await (await fetch(url + route)).text());
      assert.equal(clean.window.document.querySelectorAll('[data-php-file],[data-source-root]').length, 0, 'Development markers leaked: ' + route);
      clean.window.close();
    }
    const cli = execFileSync(process.env.PHP_BINARY || 'php', [path.join(directory, 'markers.php')], { encoding: 'utf8' });
    assert.ok(!cli.includes('data-php-file') && !cli.includes('data-source-root'), 'Development markers leaked into CLI output.');

    const browser = new JSDOM('<script src="http://helpdesk.test/assets/js/dev_locator.js" data-source-root="C:/project/public"></script>', { url: 'http://helpdesk.test/dashboard_student.php', runScripts: 'outside-only' });
    Object.defineProperty(browser.window.document, 'currentScript', { value: browser.window.document.querySelector('script'), configurable: true });
    browser.window.eval(fs.readFileSync(path.resolve(__dirname, '../public/assets/js/dev_locator.js'), 'utf8') + '\n//# sourceURL=http://helpdesk.test/assets/js/dev_locator.js');
    browser.window.eval(`
      document.body.insertAdjacentHTML('beforeend', '<button' + globalThis.HelpdeskLocator.attributes() + ' id="dynamic">Dynamic</button>');
      const item = globalThis.HelpdeskLocator.createElement('span');
      item.id = 'created'; document.body.append(item);
      //# sourceURL=http://helpdesk.test/assets/js/example.js
    `);
    assert.equal(browser.window.document.querySelector('#dynamic').dataset.phpFile, 'C:/project/public/assets/js/example.js');
    assert.equal(browser.window.document.querySelector('#dynamic').dataset.phpLine, '2');
    assert.equal(browser.window.document.querySelector('#created').dataset.phpLine, '3');
    browser.window.close();
    console.log('PHP Locator editor class detection, buffering, exact lines, included components, runtime JS sources, and production/API/CLI isolation checks passed.');
  } finally {
    server.kill();
    await stopped;
    assert.ok(path.resolve(directory).startsWith(path.resolve(runtime) + path.sep));
    fs.rmSync(directory, { recursive: true, force: true });
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
