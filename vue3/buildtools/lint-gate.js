// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lint gate for the Vue sources (local_adele #18 DoD6), as a ratchet.
 *
 * ESLint (eslint:recommended, plugin:vue/essential; configuration in
 * package.json) reports findings the code had before the gate existed. They
 * are recorded per file and rule in lint-baseline.json; the gate fails on
 * every finding beyond that - a new file, a new rule, one more of the same -
 * and asks for the baseline to be lowered when findings went away.
 *
 * Usage: node buildtools/lint-gate.js           check
 *        node buildtools/lint-gate.js --update  write the baseline (only to lower it)
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const BASELINE = path.join(__dirname, 'lint-baseline.json');
const TARGETS = ['components', 'composables', 'directives', 'router', 'config', 'store.js', 'main.js'];

(async () => {
  process.env.ESLINT_USE_FLAT_CONFIG = 'false';
  const { loadESLint } = require('eslint');
  const ESLint = await loadESLint({ useFlatConfig: false });
  const eslint = new ESLint({ cwd: ROOT, extensions: ['.js', '.vue', '.ts'] });
  const results = await eslint.lintFiles(TARGETS.filter((t) => fs.existsSync(path.join(ROOT, t))));
  const counts = {};
  for (const result of results) {
    const file = path.relative(ROOT, result.filePath).split(path.sep).join('/');
    for (const message of result.messages) {
      const key = `${file} ${message.ruleId || 'parse-error'}`;
      counts[key] = (counts[key] || 0) + 1;
    }
  }
  const sorted = Object.fromEntries(Object.entries(counts).sort(([a], [b]) => (a < b ? -1 : 1)));
  if (process.argv.includes('--update')) {
    fs.writeFileSync(BASELINE, JSON.stringify(sorted, null, 2) + '\n');
    console.log(`Baseline written: ${Object.values(sorted).reduce((a, b) => a + b, 0)} findings.`);
    return;
  }
  const baseline = JSON.parse(fs.readFileSync(BASELINE, 'utf8'));
  const worse = [];
  const better = [];
  for (const [key, n] of Object.entries(sorted)) {
    if (n > (baseline[key] || 0)) {
      worse.push(`${key}: ${n} (baseline ${baseline[key] || 0})`);
    }
  }
  for (const [key, n] of Object.entries(baseline)) {
    if ((sorted[key] || 0) < n) {
      better.push(`${key}: ${sorted[key] || 0} (baseline ${n})`);
    }
  }
  if (worse.length) {
    for (const result of results) {
      for (const m of result.messages) {
        const file = path.relative(ROOT, result.filePath).split(path.sep).join('/');
        if (worse.some((w) => w.startsWith(`${file} ${m.ruleId || 'parse-error'}:`))) {
          console.error(`::error file=vue3/${file},line=${m.line}::${m.ruleId || 'parse-error'}: ${m.message}`);
        }
      }
    }
    console.error(`New lint findings beyond the baseline:\n  ${worse.join('\n  ')}`);
    process.exit(1);
  }
  if (better.length) {
    console.log(`Fewer findings than the baseline - lower it (node buildtools/lint-gate.js --update):\n  ${better.join('\n  ')}`);
  }
  console.log(`Lint gate passed: ${Object.values(sorted).reduce((a, b) => a + b, 0)} findings, none beyond the baseline.`);
})().catch((e) => {
  console.error(e);
  process.exit(2);
});
