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
 * DOM ids that stay unique on the page (local_adele #18).
 *
 * A page can hold several ADELE applications - a course page shows every
 * mod_adele activity with "show on course page" - and every application
 * mounts its dialogs at the same time. A fixed id such as "nodeModal" or
 * "fullname" then appears more than once: a label points at the field of
 * another dialog, a screen reader reads the wrong name and a click on a
 * button opens the dialog of another application.
 *
 * Every application therefore gets its own prefix when it is mounted
 * (main.js: store.state.domPrefix, and app.config.idPrefix for Vue's
 * useId()). Ids that one component sets and another one refers to - the
 * dialogs and the buttons that open them - are built with appDomId(), so
 * both ends agree on the same prefixed id.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let mounted = 0;

/**
 * The prefix of the next application on the page: adele1, adele2, ...
 *
 * @returns {string}
 */
export function nextAppDomPrefix() {
  mounted += 1;
  return `adele${mounted}`;
}

/**
 * The page-unique id of an element of this application.
 *
 * Without a prefix in the store - a component mounted on its own, as in the
 * unit tests - the name is returned as it is.
 *
 * @param {object} store The application's Vuex store.
 * @param {string} name The element's name within the application.
 * @returns {string}
 */
export function appDomId(store, name) {
  const prefix = store && store.state && store.state.domPrefix;
  return prefix ? `${prefix}-${name}` : name;
}

/**
 * A CSS selector for an element of this application (Bootstrap data-target).
 *
 * @param {object} store The application's Vuex store.
 * @param {string} name The element's name within the application.
 * @returns {string}
 */
export function appDomSelector(store, name) {
  return `#${appDomId(store, name)}`;
}

/** The names of the dialogs that other components open (data-target). */
export const DIALOGS = Object.freeze({
  node: 'nodeModal',
  course: 'courseModal',
  feedback: 'feedbackModal',
  tutorial: 'helpingSlider',
});

/**
 * Edge markers that VueFlow gives an id of its own instance (#18).
 *
 * VueFlow names a marker given as a string ("arrowclosed") by that string:
 * every graph on the page then defines <marker id="arrowclosed">. Given as an
 * object, the marker's id starts with the id of its VueFlow instance.
 *
 * @param {object|string|undefined} marker An edge's markerEnd/markerStart.
 * @returns {object|undefined}
 */
export function scopedMarker(marker) {
  // Only VueFlow's own marker types; any other string names a marker the
  // page defines itself and stays as it is.
  return marker === 'arrow' || marker === 'arrowclosed' ? { type: marker } : marker;
}

/**
 * The edges with their markers scoped to their graph (see scopedMarker).
 *
 * @param {Array} edges The edges (changed in place).
 * @returns {Array} The same edges.
 */
export function scopeMarkers(edges) {
  (edges || []).forEach((edge) => {
    if (edge.markerEnd !== undefined) {
      edge.markerEnd = scopedMarker(edge.markerEnd);
    }
    if (edge.markerStart !== undefined) {
      edge.markerStart = scopedMarker(edge.markerStart);
    }
  });
  return edges;
}
