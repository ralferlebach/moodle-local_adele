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
 * Build gate (local_adele #18 DoD6): the shipped bundle must be built from
 * the sources of this commit, and the three files in amd/build must be the
 * bundle (repository convention). Exit 1 with the reason otherwise.
 *
 * Usage: node buildtools/check-bundle.js
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fs = require('fs');
const path = require('path');
const { sourceHash } = require('./sourcehash');

const AMD = path.resolve(__dirname, '../../amd');
const bundle = fs.readFileSync(path.join(AMD, 'src/app-lazy.js'), 'utf8');
const expected = sourceHash();
const found = /adele-source-hash:([0-9a-f]{64})/.exec(bundle);
const errors = [];
if (!found) {
  errors.push('amd/src/app-lazy.js carries no source hash - built before the gate, or not by webpack.config.js.');
} else if (found[1] !== expected) {
  errors.push(`amd/src/app-lazy.js was built from other sources (bundle ${found[1].slice(0, 12)}, `
    + `sources ${expected.slice(0, 12)}): run "npm run build" in vue3 and copy the bundle to amd/build.`);
}
for (const name of ['app-lazy.js', 'app-lazy.min.js', 'app-lazy.min.js.map']) {
  const file = path.join(AMD, 'build', name);
  if (!fs.existsSync(file) || fs.readFileSync(file, 'utf8') !== bundle) {
    errors.push(`amd/build/${name} is not the bundle amd/src/app-lazy.js.`);
  }
}
if (errors.length) {
  errors.forEach((e) => console.error(`::error::${e}`));
  process.exit(1);
}
console.log(`Bundle built from the sources of this commit (${expected.slice(0, 12)}); amd/build matches.`);
