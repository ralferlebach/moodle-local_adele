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
 * The keyboard focus across a refresh of the canvas (mod_adele #27).
 *
 * A refresh that brings a change rebuilds the canvas; the element that had
 * the keyboard focus is replaced by a new one. Without help the focus would
 * fall back to the page, and a keyboard or screen reader user would lose
 * their place. So the focused element is remembered by what identifies it
 * (its data-testid, else its node and position among the node's focusable
 * elements) and the same element of the new canvas gets the focus back.
 * Auto-focus itself only moves the visual view - it never takes the
 * keyboard focus anywhere.
 *
 * @module     local_adele/keyboardFocus
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const FOCUSABLE = 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * A value for an attribute selector in double quotes.
 *
 * @param {string} value
 * @returns {string}
 */
const esc = (value) => (typeof CSS !== 'undefined' && CSS.escape
  ? CSS.escape(String(value))
  : String(value).replace(/["\\]/g, '\\$&'));

/**
 * What identifies the focused element inside a container, or null if the
 * focus is elsewhere.
 *
 * @param {HTMLElement|null} container
 * @param {Document} doc
 * @returns {object|null}
 */
export function rememberFocus(container, doc = document) {
  const active = doc.activeElement;
  if (!container || !active || active === doc.body || !container.contains(active)) {
    return null;
  }
  if (active.dataset && active.dataset.testid) {
    return { testid: active.dataset.testid };
  }
  const node = active.closest('.vue-flow__node');
  if (!node || !node.dataset.id) {
    return null;
  }
  if (node === active) {
    return { nodeid: node.dataset.id, index: -1 };
  }
  const index = Array.from(node.querySelectorAll(FOCUSABLE)).indexOf(active);
  return { nodeid: node.dataset.id, index };
}

/**
 * Give the remembered element of the rebuilt canvas the focus back, without
 * scrolling the page. Nothing happens if the person has moved the focus
 * meanwhile or the element is gone.
 *
 * @param {HTMLElement|null} container
 * @param {object|null} remembered rememberFocus() result.
 * @param {Document} doc
 * @returns {boolean} Whether the focus was given back.
 */
export function restoreFocus(container, remembered, doc = document) {
  if (!container || !remembered) {
    return false;
  }
  const active = doc.activeElement;
  if (active && active !== doc.body && !container.contains(active)) {
    return false;
  }
  let target = null;
  if (remembered.testid) {
    target = container.querySelector(`[data-testid="${esc(remembered.testid)}"]`);
  } else if (remembered.nodeid) {
    const node = container.querySelector(`.vue-flow__node[data-id="${esc(remembered.nodeid)}"]`);
    if (node) {
      target = remembered.index < 0 ? node : node.querySelectorAll(FOCUSABLE)[remembered.index] || node;
    }
  }
  if (!target || target === active) {
    return false;
  }
  target.focus({ preventScroll: true });
  return doc.activeElement === target;
}

/**
 * restoreFocus() once the rebuilt canvas shows the element: tried every
 * 100 ms, at most for 3 s; given up as soon as the focus is somewhere else.
 *
 * @param {Function} getContainer () => the container (it may be re-rendered).
 * @param {object|null} remembered rememberFocus() result.
 * @param {Document} doc
 * @param {Function} wanted () => whether the focus is still to be given back
 *     (false once the view is gone or a later refresh has come).
 * @returns {void}
 */
export function restoreFocusWhenReady(getContainer, remembered, doc = document, wanted = () => true) {
  if (!remembered) {
    return;
  }
  let tries = 0;
  const attempt = () => {
    if (!wanted()) {
      return;
    }
    const container = getContainer();
    const active = doc.activeElement;
    if (container && active && active !== doc.body && !container.contains(active)) {
      return;
    }
    if (restoreFocus(container, remembered, doc) || ++tries >= 30) {
      return;
    }
    setTimeout(attempt, 100);
  };
  attempt();
}
