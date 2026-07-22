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
 * Client-side persistence of a student's VueFlow viewport (pan + zoom) per learning
 * path + user, so a genuine page (re)load restores where they were instead of jumping
 * back to the start (#485). Kept as pure helpers so they are unit-testable without
 * mounting VueFlow; the caller injects Moodle's core/localstorage as `storage`.
 */

/**
 * Build the storage key for a learning-path + user viewport.
 *
 * @param {number|string} learningpathid
 * @param {number|string} userid
 * @returns {string}
 */
export function viewportKey(learningpathid, userid) {
  return `local_adele/viewport/${learningpathid}/${userid}`;
}

/**
 * Persist a viewport. Never throws (storage may be unavailable / quota-full).
 *
 * @param {{get:Function,set:Function}} storage core/localstorage
 * @param {string} key
 * @param {{x:number,y:number,zoom:number}} viewport
 */
export function saveViewport(storage, key, viewport) {
  try {
    if (viewport && typeof viewport.zoom === 'number') {
      storage.set(key, JSON.stringify({ x: viewport.x, y: viewport.y, zoom: viewport.zoom }));
    }
  } catch {
    // Ignore: a missing viewport must never break the view.
  }
}

/**
 * Load a previously saved viewport, or null if none / invalid.
 *
 * @param {{get:Function,set:Function}} storage core/localstorage
 * @param {string} key
 * @returns {({x:number,y:number,zoom:number}|null)}
 */
export function loadViewport(storage, key) {
  try {
    const raw = storage.get(key);
    const v = raw ? JSON.parse(raw) : null;
    if (v && typeof v.x === 'number' && typeof v.y === 'number' && typeof v.zoom === 'number') {
      return v;
    }
  } catch {
    // Ignore corrupt/absent value; fall back to fit-view.
  }
  return null;
}
