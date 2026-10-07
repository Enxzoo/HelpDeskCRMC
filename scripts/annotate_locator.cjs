const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { Parser } = require('../tests/node_modules/parse5');
const acorn = require('../tests/node_modules/acorn');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const write = process.argv.includes('--write');
const skip = new Set(['head', 'meta', 'link', 'title', 'script', 'style']);
function phpMarker(lines = 0, escaped = false) {
  const call = `dev_locator_attributes(__FILE__, __LINE__${lines ? ' - ' + lines : ''})`;
  return `<?= ${escaped ? 'addslashes(' + call + ')' : call} ?>`;
}
const jsMarker = "${globalThis.HelpdeskLocator?.attributes() || ''}";
const files = [
  ...fs.readdirSync(path.join(root, 'public')).filter(file => file.endsWith('.php') && file !== 'logout.php').map(file => 'public/' + file),
  ...fs.readdirSync(path.join(root, 'public/assets/components')).filter(file => file.endsWith('.php')).map(file => 'public/assets/components/' + file),
  ...fs.readdirSync(path.join(root, 'public/assets/js')).filter(file => file.endsWith('.js') && file !== 'dev_locator.js').map(file => 'public/assets/js/' + file),
];
let total = 0;
function tags(html) {
  const found = [];
  class TagParser extends Parser {
    onStartTag(token) {
      if (!skip.has(token.tagName) && token.location && token.location.startOffset < html.length) found.push(token.location);
      super.onStartTag(token);
    }
  }
  new TagParser({ sourceCodeLocationInfo: true }).tokenizer.write(html + '"\'>', true);
  return found;
}
for (const file of files) {
  const target = path.join(root, file);
  const original = fs.readFileSync(target, 'utf8');
  const isPhp = file.endsWith('.php');
  const source = isPhp ? original.replace(/[ \t]*<\?= (?:addslashes\()?dev_locator_attributes\(__FILE__, __LINE__(?: - \d+)?\)\)? \?>/g, '') : original;
  const tokens = isPhp ? JSON.parse(execFileSync(php, ['-r',
    'echo json_encode(array_map(static fn($t) => is_array($t) ? [token_name($t[0]), $t[1]] : ["CHAR", $t], token_get_all(stream_get_contents(STDIN))));',
  ], { input: source, encoding: 'utf8', windowsHide: true })) : [];
  // Keep offsets and line breaks intact while PHP expressions are hidden from the HTML/JS parsers.
  const masked = tokens.map(([type, text]) => type === 'T_INLINE_HTML' ? text : type === 'T_OPEN_TAG_WITH_ECHO' ? '0' + ' '.repeat(text.length - 1) : text.replace(/[^\r\n]/g, ' ')).join('');
  const edits = [];
  const scripts = [];
  let scriptStart;
  function addTag(offset, text) {
    const name = source.slice(offset).match(/^<[\w:-]+/)[0];
    edits.push({ offset: offset + name.length, text });
  }
  function addPhpTag(location, base = 0, escaped = false) {
    const start = base + location.startOffset;
    const nameEnd = start + source.slice(start).match(/^<[\w:-]+/)[0].length;
    // CSS Peek scans PHP as HTML: keep class/id ahead of the unquoted PHP close tag.
    const offset = Math.max(nameEnd, ...['class', 'id'].map(name => base + (location.attrs?.[name]?.endOffset ?? location.startOffset)));
    const lines = source.slice(start, offset).split('\n').length - 1;
    edits.push({ offset, text: ' ' + phpMarker(lines, escaped) });
  }
  class LocatorParser extends Parser {
    onStartTag(token) {
      if (token.tagName === 'script') scriptStart = token.location.endOffset;
      if (!skip.has(token.tagName) && token.location) {
        addPhpTag(token.location);
      }
      super.onStartTag(token);
    }
    onEndTag(token) {
      if (token.tagName === 'script' && scriptStart !== undefined) {
        scripts.push([scriptStart, token.location.startOffset]);
        scriptStart = undefined;
      }
      super.onEndTag(token);
    }
  }
  if (isPhp) new LocatorParser({ sourceCodeLocationInfo: true }).tokenizer.write(masked, true);
  else scripts.push([0, source.length]);
  for (const [start, end] of scripts) {
    const code = (isPhp ? masked : source).slice(start, end);
    if (!code.trim()) continue;
    const ast = acorn.parse(code, { ecmaVersion: 'latest', allowAwaitOutsideFunction: true });
    function visit(node) {
      if (node.type === 'TemplateLiteral' || (node.type === 'Literal' && typeof node.value === 'string')) {
        const contentStart = start + node.start + 1;
        let html = source.slice(contentStart, start + node.end - 1);
        if (node.type === 'TemplateLiteral') {
          // Parse only literal HTML, not nested JavaScript interpolation expressions.
          const characters = html.split('').map(c => c === '\n' || c === '\r' ? c : ' ');
          for (const quasi of node.quasis) {
            const from = quasi.start - node.start - 1;
            const raw = source.slice(start + quasi.start, start + quasi.end);
            for (let i = 0; i < raw.length; i++) characters[from + i] = raw[i];
          }
          html = characters.join('');
        }
        if (/<[a-z][\w:-]*(?:\s|>)/i.test(html)) {
          let inserted = false;
          for (const location of tags(html)) {
            const offset = contentStart + location.startOffset;
            const afterName = offset + source.slice(offset).match(/^<[\w:-]+/)[0].length;
            if (!isPhp && (source.slice(afterName).startsWith(jsMarker) || source.slice(afterName).startsWith("' + (globalThis.HelpdeskLocator?.attributes() || '') + '") || source.slice(afterName).startsWith('" + (globalThis.HelpdeskLocator?.attributes() || \'\') + "'))) continue;
            const quote = source[start + node.start];
            if (isPhp) addPhpTag(location, contentStart, quote === '"');
            else addTag(offset, node.type === 'TemplateLiteral' ? jsMarker : `${quote} + (globalThis.HelpdeskLocator?.attributes() || '') + ${quote}`);
            inserted = true;
          }
          if (!isPhp && inserted && node.type === 'Literal') {
            edits.push({ offset: start + node.start, text: '(' }, { offset: start + node.end, text: ')' });
          }
        }
      }
      if (!isPhp && node.type === 'CallExpression' && node.callee.type === 'MemberExpression' && node.callee.object.name === 'document' && node.callee.property.name === 'createElement') {
        edits.push({ offset: start + node.callee.object.start, end: start + node.callee.object.end, text: '(globalThis.HelpdeskLocator || document)' });
      }
      for (const child of Object.values(node)) {
        if (Array.isArray(child)) child.forEach(value => { if (value?.type) visit(value); });
        else if (child?.type) visit(child);
      }
    }
    visit(ast);
  }
  let result = source;
  for (const { offset, end = offset, text } of edits.sort((a, b) => b.offset - a.offset)) result = result.slice(0, offset) + text + result.slice(end);
  if (result !== original) {
    console.log(`${file}: ${edits.length} source markers${write ? ' updated' : ' pending'}`);
    if (write) fs.writeFileSync(target, result);
  }
  total += edits.length;
}
console.log(`${total} exact template source markers checked.`);
