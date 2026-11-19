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
 * The keyboard focus survives a rebuild of the canvas (mod_adele #27):
 * no loss of place, no focus trap, no focus taken from elsewhere.
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
  rememberFocus, restoreFocus, restoreFocusWhenReady,
} from '../../../../composables/flowHelper/keyboardFocus';

/**
 * A canvas with two nodes; node 2 has two buttons, the second with a test id.
 *
 * @returns {HTMLElement}
 */
function canvas() {
  const container = document.createElement('div');
  container.innerHTML = `
    <div class="vue-flow__node" data-id="dndnode_1" tabindex="0"><button>i</button></div>
    <div class="vue-flow__node" data-id="dndnode_2" tabindex="0">
      <button>first</button><button data-testid="feedback-2">feedback</button></div>`;
  document.body.appendChild(container);
  return container;
}

describe('keyboardFocus', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('remembers a focused element by its test id and gives its successor the focus back', () => {
    const old = canvas();
    old.querySelector('[data-testid="feedback-2"]').focus();
    const remembered = rememberFocus(old);
    expect(remembered).toEqual({ testid: 'feedback-2' });
    old.remove();
    const rebuilt = canvas();
    expect(restoreFocus(rebuilt, remembered)).toBe(true);
    expect(document.activeElement).toBe(rebuilt.querySelector('[data-testid="feedback-2"]'));
  });

  it('without a test id: the node and the position of the element in it; the node itself', () => {
    const old = canvas();
    old.querySelector('[data-id="dndnode_2"] button').focus();
    const button = rememberFocus(old);
    expect(button).toEqual({ nodeid: 'dndnode_2', index: 0 });
    old.querySelector('[data-id="dndnode_1"]').focus();
    const node = rememberFocus(old);
    expect(node).toEqual({ nodeid: 'dndnode_1', index: -1 });
    old.remove();
    const rebuilt = canvas();
    restoreFocus(rebuilt, button);
    expect(document.activeElement).toBe(rebuilt.querySelector('[data-id="dndnode_2"] button'));
    restoreFocus(rebuilt, node);
    expect(document.activeElement).toBe(rebuilt.querySelector('[data-id="dndnode_1"]'));
  });

  it('focus outside the canvas: nothing remembered, nothing taken', () => {
    const c = canvas();
    const outside = document.createElement('input');
    document.body.appendChild(outside);
    outside.focus();
    expect(rememberFocus(c)).toBeNull();
    // The person moved on meanwhile: the focus stays where they put it.
    expect(restoreFocus(c, { testid: 'feedback-2' })).toBe(false);
    expect(document.activeElement).toBe(outside);
  });

  it('the element is gone: no focus moved; when it appears later, it gets the focus', () => {
    jest.useFakeTimers();
    let container = document.createElement('div');
    document.body.appendChild(container);
    restoreFocusWhenReady(() => container, { testid: 'feedback-2' });
    expect(document.activeElement).toBe(document.body);
    jest.advanceTimersByTime(300);
    container.remove();
    container = canvas();
    jest.advanceTimersByTime(100);
    expect(document.activeElement).toBe(container.querySelector('[data-testid="feedback-2"]'));
    jest.useRealTimers();
  });

  it('gives up after 3 s; nothing remembered: nothing happens', () => {
    jest.useFakeTimers();
    const container = document.createElement('div');
    document.body.appendChild(container);
    restoreFocusWhenReady(() => container, { testid: 'never' });
    jest.advanceTimersByTime(5000);
    expect(jest.getTimerCount()).toBe(0);
    restoreFocusWhenReady(() => container, null);
    expect(jest.getTimerCount()).toBe(0);
    jest.useRealTimers();
  });

  it('no longer wanted (view gone, a later refresh): no focus given, no further tries', () => {
    jest.useFakeTimers();
    let wanted = true;
    let container = document.createElement('div');
    document.body.appendChild(container);
    restoreFocusWhenReady(() => container, { testid: 'feedback-2' }, document, () => wanted);
    jest.advanceTimersByTime(200);
    wanted = false;
    container.remove();
    container = canvas();
    jest.advanceTimersByTime(500);
    expect(document.activeElement).toBe(document.body);
    expect(jest.getTimerCount()).toBe(0);
    jest.useRealTimers();
  });
});
