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
 * A hash of the sources the AMD bundle is built from (local_adele #18 DoD6,
 * build gate). The build writes it into the bundle; the CI check compares it
 * with the sources of the commit - a bundle built from other sources, or not
 * rebuilt after a change, is refused. Independent of Node, npm and the
 * minifier: only the sources count.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const DIRS = ['components', 'composables', 'directives', 'router', 'config', 'images'];
const FILES = ['main.js', 'store.js', 'package.json', 'webpack.config.js', 'babel.config.js', 'tsconfig.json'];

/**
 * The files that make the bundle, relative to vue3/, sorted.
 *
 * @returns {string[]}
 */
function sourceFiles() {
  const out = [];
  const walk = (rel) => {
    const abs = path.join(ROOT, rel);
    if (!fs.existsSync(abs)) {
      return;
    }
    for (const entry of fs.readdirSync(abs, { withFileTypes: true })) {
      const child = path.posix.join(rel, entry.name);
      if (entry.isDirectory()) {
        walk(child);
      } else {
        out.push(child);
      }
    }
  };
  DIRS.forEach(walk);
  FILES.filter((f) => fs.existsSync(path.join(ROOT, f))).forEach((f) => out.push(f));
  return out.sort();
}

/**
 * The hash: sha256 over path and content of every source file, line endings normalised.
 *
 * @returns {string}
 */
function sourceHash() {
  const hash = crypto.createHash('sha256');
  for (const file of sourceFiles()) {
    hash.update(file + '\0');
    hash.update(fs.readFileSync(path.join(ROOT, file)).toString('binary').replace(/\r\n/g, '\n'));
    hash.update('\0');
  }
  return hash.digest('hex');
}

module.exports = { sourceFiles, sourceHash };
