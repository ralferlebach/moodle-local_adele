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
 * Texts of the group and live views (#12 - #15).
 *
 * @module     local_adele/groupProgress
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fill a Moodle string: {$a} with a value, {$a->name} with the value's fields.
 *
 * @param {string} text The string.
 * @param {*} a A value or an object.
 * @returns {string}
 */
export function fill(text, a) {
  let out = String(text || '');
  if (a !== null && typeof a === 'object') {
    Object.keys(a).forEach((key) => {
      out = out.split('{$a->' + key + '}').join(String(a[key]));
    });
    return out;
  }
  return out.split('{$a}').join(String(a));
}

/**
 * An average as text: "41.7 % (3 of 4 learners with progress)", or "no data"
 * for a group without measured learners - never 0 % or NaN.
 *
 * @param {object} row groupid, value (null: none), measured, members.
 * @param {object} strings The component strings.
 * @returns {string}
 */
export function averageText(row, strings) {
  if (row.value === null || row.value === undefined || !row.measured) {
    return fill(strings.group_average_nodata, row.members - (row.measured || 0));
  }
  return fill(strings.group_average_value, { value: row.value, measured: row.measured, members: row.members });
}
