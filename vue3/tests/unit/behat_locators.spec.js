/**
 * Guard for the Behat field locators (CI 2026-11-20).
 *
 * Behat's "I set the field" finds a field by id, name or label. Since #18
 * the ids carry a per-app prefix (adele1-...), so a locator that only matched
 * the id no longer finds anything - Behat failed with
 * 'Field matching locator "goalsubjectplaceholder" not found'. Chromedriver
 * is not available in every development environment, so this test checks
 * statically, in Jest, that every field locator of the features is still
 * reachable: as a name attribute of a Vue component (template literals
 * allowed) or, for a locator in words, as the text of a language string.
 * And no CSS or XPath selector of the features or the Behat context may
 * address an id that the application prefixes (CI 2026-11-21:
 * 'Expected exactly 1 element for '#restriction-condition_1-duration', found 0').
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '../../..');

function filesUnder(dir, ext) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      return entry.name === 'node_modules' ? [] : filesUnder(full, ext);
    }
    return full.endsWith(ext) ? [full] : [];
  });
}

function fieldLocators() {
  const locators = new Set();
  filesUnder(path.join(ROOT, 'tests/behat'), '.feature').forEach((file) => {
    const text = fs.readFileSync(file, 'utf8');
    for (const match of text.matchAll(/the field "([^"]+)"/g)) {
      locators.add(match[1]);
    }
  });
  return [...locators];
}

function escapeRegex(text) {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Name attributes of the components, as regular expressions. */
function namePatterns() {
  const patterns = [];
  filesUnder(path.join(ROOT, 'vue3/components'), '.vue').forEach((file) => {
    const text = fs.readFileSync(file, 'utf8');
    for (const match of text.matchAll(/\sname="([^"]+)"/g)) {
      patterns.push(new RegExp(`^${escapeRegex(match[1])}$`));
    }
    for (const match of text.matchAll(/\s:name="`([^`"]+)`"/g)) {
      const source = match[1].split(/\$\{[^}]+\}/).map(escapeRegex).join('[^\\s]+');
      patterns.push(new RegExp(`^${source}$`));
    }
  });
  return patterns;
}

/** Ids the application prefixes: appDomId(store, 'x') / appDomId(store, `x-${...}`). */
function prefixedIdPatterns() {
  const patterns = [];
  filesUnder(path.join(ROOT, 'vue3'), '.vue').concat(filesUnder(path.join(ROOT, 'vue3'), '.js'))
    .filter((file) => !file.includes(`${path.sep}tests${path.sep}`))
    .forEach((file) => {
      const text = fs.readFileSync(file, 'utf8');
      for (const match of text.matchAll(/appDomId\(\s*[\w.]+\s*,\s*(['`])([^'`]+)\1/g)) {
        const source = match[2].split(/\$\{[^}]+\}/).map(escapeRegex).join('[^\\s\'"\\]]+');
        patterns.push(new RegExp(`^${source}$`));
      }
    });
  return patterns;
}

/** Ids addressed by CSS (#id, [id=...]) or XPath (@id=...) selectors. */
function selectorIds() {
  const files = filesUnder(path.join(ROOT, 'tests/behat'), '.feature')
    .concat(filesUnder(path.join(ROOT, 'tests/behat'), '.php'));
  const ids = new Set();
  files.forEach((file) => {
    const text = fs.readFileSync(file, 'utf8');
    for (const match of text.matchAll(/#([A-Za-z][\w-]*)/g)) {
      ids.add(`${path.basename(file)}: ${match[1]}`);
    }
    for (const match of text.matchAll(/(?:\[id|@id)\s*=\s*\\?['"]([^'"\\]+)/g)) {
      ids.add(`${path.basename(file)}: ${match[1]}`);
    }
  });
  return [...ids];
}

function langTexts() {
  const text = fs.readFileSync(path.join(ROOT, 'lang/en/local_adele.php'), 'utf8');
  return new Set([...text.matchAll(/=\s*'((?:[^'\\]|\\.)*)';/g)].map((m) => m[1].replace(/\\'/g, "'")));
}

describe('Behat field locators (#18 prefixed ids)', () => {
  const locators = fieldLocators();
  const names = namePatterns();
  const labels = langTexts();

  it('finds the locators of the features', () => {
    expect(locators).toEqual(expect.arrayContaining(['goalnameplaceholder', 'goalsubjectplaceholder']));
  });

  it.each(locators)('"%s" is reachable without the prefixed id', (locator) => {
    const byName = names.some((pattern) => pattern.test(locator));
    const byLabel = /\s|[A-Z]/.test(locator) && labels.has(locator);
    expect({ locator, reachable: byName || byLabel }).toEqual({ locator, reachable: true });
  });

  const prefixed = prefixedIdPatterns();
  const ids = selectorIds();

  it('knows the prefixed ids and the selectors', () => {
    expect(prefixed.some((pattern) => pattern.test('restriction-condition_1-duration'))).toBe(true);
    expect(ids.length).toBeGreaterThan(0);
  });

  it.each(ids)('selector id %s is not one the application prefixes', (entry) => {
    const id = entry.split(': ')[1];
    expect({ entry, prefixed: prefixed.some((pattern) => pattern.test(id)) }).toEqual({ entry, prefixed: false });
  });
});
