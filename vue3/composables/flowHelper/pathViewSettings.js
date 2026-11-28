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
 * The site-wide settings of the open learning path view, as the page hands
 * them over (attribute "pathviewsettings", local_adele\path_view_settings):
 * refresh (mod_adele #26) and auto-focus (mod_adele #27). Anything missing
 * or out of range counts as its default, mirroring the server.
 *
 * @module     local_adele/pathViewSettings
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const POLLING_OFF = 0;
export const POLLING_FOCUS = 1;
export const POLLING_ACTIVE = 2;
export const POLLING_ALWAYS = 3;

export const FOCUS_NONE = 0;
export const FOCUS_LASTCHANGE = 1;
export const FOCUS_NEXTOPEN = 2;

export const DEFAULTS = Object.freeze({
  pollingmode: POLLING_ACTIVE,
  pollinginterval: 3000,
  pollingdebounce: 300,
  autofocusenabled: 1,
  autofocusteachermode: FOCUS_LASTCHANGE,
  autofocuslearnermode: FOCUS_NEXTOPEN,
});

const CHOICES = {
  pollingmode: [0, 1, 2, 3],
  autofocusenabled: [0, 1],
  autofocusteachermode: [0, 1, 2],
  autofocuslearnermode: [0, 1, 2],
};

const BOUNDS = {
  pollinginterval: [1000, 300000],
  pollingdebounce: [0, 10000],
};

/**
 * Read the settings.
 *
 * @param {string|object|null} raw The attribute's JSON, or an object.
 * @returns {object} Every setting, valid.
 */
export function parsePathViewSettings(raw) {
  let given = raw;
  if (typeof raw === 'string') {
    try {
      given = JSON.parse(raw);
    } catch (e) {
      given = null;
    }
  }
  given = given && typeof given === 'object' ? given : {};
  const out = { ...DEFAULTS };
  Object.keys(CHOICES).forEach((key) => {
    const value = Number(given[key]);
    if (Number.isInteger(value) && CHOICES[key].includes(value)) {
      out[key] = value;
    }
  });
  Object.keys(BOUNDS).forEach((key) => {
    const value = Number(given[key]);
    const [min, max] = BOUNDS[key];
    if (Number.isInteger(value) && value >= min && value <= max) {
      out[key] = value;
    }
  });
  return out;
}
