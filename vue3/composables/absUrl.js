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
 * Resolve a stored image path against the Moodle root URL.
 *
 * Stock-image paths were historically stored root-relative
 * ("/local/adele/public/..."), which 404s on sub-directory installs
 * (e.g. localhost/moodle03) and collapses the containers sized by the
 * image (GitHub #459/#460). Absolute URLs (uploaded pluginfile images)
 * pass through unchanged, so both legacy and new data render correctly.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Prefix a root-relative path with wwwroot; leave absolute URLs untouched.
 *
 * @param {string|null|undefined} path stored image path or URL
 * @param {string} wwwroot the Moodle root URL (store.state.wwwroot)
 * @returns {string|null|undefined} resolvable URL
 */
export default function absUrl(path, wwwroot) {
  if (typeof path === 'string' && path.startsWith('/') && !path.startsWith('//')) {
    return (wwwroot || '') + path;
  }
  return path;
}
