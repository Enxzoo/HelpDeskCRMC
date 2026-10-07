const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const css = fs.readFileSync(path.resolve(__dirname, '../public/assets/css/student/home.css'), 'utf8');
const markup = fs.readFileSync(path.resolve(__dirname, '../public/dashboard_student.php'), 'utf8');
const source = new JSDOM(markup.replace(/<\?= dev_locator_attributes\(__FILE__, __LINE__(?: - \d+)?\) \?>/g, ''));
const tiles = [...source.window.document.querySelectorAll('.tile[data-category]')];
const categories = tiles.map(tile => tile.dataset.category);
assert.equal(categories.length, 13, 'Expected all nine office and four department tiles.');
assert.equal(new Set(categories).size, categories.length);
const html = tiles.map(tile => tile.outerHTML).join('');
source.window.close();

function fixture(styles) {
  return new JSDOM('<style>' + styles + '</style>' + html);
}

const baseline = fixture(css);
const rules = [...baseline.window.document.styleSheets[0].cssRules];
assert.equal(rules.find(rule => rule.selectorText === '.tile').style.getPropertyValue('background'), 'var(--tile-background,#5b5955)');
assert.equal(rules.find(rule => rule.selectorText === '.tile:hover').style.getPropertyValue('background'), 'var(--tile-hover-background,#4d4b47)');

for (const category of categories) {
  const selector = `.tile[data-category="${category}"]`;
  const rule = rules.find(item => item.selectorText === selector);
  assert.ok(rule, 'Missing individual color rule for ' + category);
  assert.ok(rule.style.getPropertyValue('--tile-background'), 'Missing normal color for ' + category);
  assert.ok(rule.style.getPropertyValue('--tile-hover-background'), 'Missing hover color for ' + category);
  for (const property of ['--tile-background', '--tile-hover-background']) {
    const original = rule.style.getPropertyValue(property);
    rule.style.setProperty(property, '#123456');
    const changed = fixture(rules.map(item => item.cssText).join('\n'));
    for (const tile of changed.window.document.querySelectorAll('.tile')) {
      const value = changed.window.getComputedStyle(tile).getPropertyValue(property).trim();
      const expected = tile.dataset.category === category ? '#123456'
        : baseline.window.getComputedStyle(baseline.window.document.querySelector(`.tile[data-category="${tile.dataset.category}"]`)).getPropertyValue(property).trim();
      assert.equal(value, expected, `${category}'s ${property} affects ${tile.dataset.category}.`);
    }
    changed.window.close();
    rule.style.setProperty(property, original);
  }
}
baseline.window.close();
console.log('All 13 tile color rules support independent normal and hover colors.');
