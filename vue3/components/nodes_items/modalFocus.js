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
 * Keyboard of the modal dialogs of the teacher course access (local_adele
 * #600): Tab and Shift+Tab cycle inside the dialog, Escape cancels.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The elements the focus may cycle through.
 *
 * @param {HTMLElement|null} root The dialog.
 * @returns {HTMLElement[]}
 */
export function focusables(root) {
  if (!root) {
    return [];
  }
  return Array.from(root.querySelectorAll('button, textarea, select, a[href]'))
    .filter((el) => !el.disabled);
}

/**
 * Handle a key in a modal dialog.
 *
 * @param {KeyboardEvent} event
 * @param {HTMLElement|null} root The dialog.
 * @param {Function} onEscape Called on Escape.
 */
export function modalKeydown(event, root, onEscape) {
  if (event.key === 'Escape') {
    event.preventDefault();
    event.stopPropagation();
    onEscape();
    return;
  }
  if (event.key !== 'Tab') {
    return;
  }
  const list = focusables(root);
  if (!list.length) {
    return;
  }
  const first = list[0];
  const last = list[list.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}
