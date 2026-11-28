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
 * Expanded course panels of a stacked node carry their course (mod_adele #17).
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';
import { useVueFlow } from '@vue-flow/core';
import ExpandedCourses from '../../../../components/nodes_items/ExpandedCourses.vue';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('@vue-flow/core', () => ({ useVueFlow: jest.fn() }));

describe('ExpandedCourses.vue', () => {
  let added;
  let mounted = [];

  beforeEach(() => {
    jest.useFakeTimers();
    added = [];
    useStore.mockReturnValue({
      state: {
        availablecourses: [
          { course_node_id: [11], fullname: 'Mathe' },
          { course_node_id: [12], fullname: 'Mathe' },
          { course_node_id: [13], fullname: 'Physik' },
        ],
        strings: { expanded_course_label: '{$a->name} (Kurs-ID {$a->id})' },
      },
    });
    useVueFlow.mockReturnValue({
      findNode: (id) => (id === 'dndnode_1'
        ? { id, position: { x: 0, y: 0 }, data: { node_id: 'dndnode_1', course_node_id: [13, 11, 12] } }
        : null),
      addNodes: (nodes) => added.push(...nodes),
      removeNodes: jest.fn(),
    });
  });

  afterEach(() => {
    // Expanded stacks listen for Escape on the document: fold them all.
    mounted.forEach((w) => w.unmount());
    mounted = [];
    jest.useRealTimers();
  });

  const expand = (order) => {
    added = [];
    mounted.push(mount(ExpandedCourses, { props: { data: { node_id: 'dndnode_1', course_node_id: order } } }));
    jest.runAllTimers();
    return added;
  };

  it('gives each panel the real course id, a course class and a unique accessible name', () => {
    const nodes = expand([13, 11, 12]);
    expect(nodes.map((n) => n.id)).toEqual([
      'dndnode_1_expanded_courses_13', 'dndnode_1_expanded_courses_11', 'dndnode_1_expanded_courses_12',
    ]);
    expect(nodes.map((n) => n.data.course_id)).toEqual([13, 11, 12]);
    expect(nodes.map((n) => n.class)).toEqual([
      'adele-expanded-course adele-expanded-course-13',
      'adele-expanded-course adele-expanded-course-11',
      'adele-expanded-course adele-expanded-course-12',
    ]);
    const labels = nodes.map((n) => n.ariaLabel);
    expect(labels).toEqual(['Physik (Kurs-ID 13)', 'Mathe (Kurs-ID 11)', 'Mathe (Kurs-ID 12)']);
    expect(new Set(labels).size).toBe(3);
  });

  it('keeps each course\'s identity when the stack is rendered in another order', () => {
    const first = Object.fromEntries(expand([13, 11, 12]).map((n) => [n.data.course_id, [n.id, n.ariaLabel, n.class]]));
    const second = Object.fromEntries(expand([12, 13, 11]).map((n) => [n.data.course_id, [n.id, n.ariaLabel, n.class]]));
    expect(second).toEqual(first);
  });

  it('names a panel with the short name when the node data carries it', () => {
    useVueFlow.mockReturnValue({
      findNode: (id) => (id === 'dndnode_1'
        ? { id, position: { x: 0, y: 0 }, data: { node_id: 'dndnode_1', course_node_id: [11],
          course_node_id_description: { 11: { fullname: 'Mathe', shortname: 'MA-1' } } } }
        : null),
      addNodes: (nodes) => added.push(...nodes),
      removeNodes: jest.fn(),
    });
    useStore.mockReturnValue({
      state: { availablecourses: [], strings: { expanded_course_label_short: '{$a->name} ({$a->shortname}, Kurs-ID {$a->id})' } },
    });
    expect(expand([11]).map((n) => n.ariaLabel)).toEqual(['Mathe (MA-1, Kurs-ID 11)']);
  });

  /**
   * A focusable stand-in for the VueFlow wrapper of a node.
   *
   * @param {string} id The VueFlow node id.
   * @returns {HTMLElement} The focused element in it.
   */
  const focusIn = (id) => {
    const node = document.createElement('div');
    node.className = 'vue-flow__node';
    node.dataset.id = id;
    const button = document.createElement('button');
    node.appendChild(button);
    document.body.appendChild(node);
    button.focus();
    return button;
  };

  it('Escape in a panel of its own stack asks to fold the stack - elsewhere, or consumed, not', () => {
    const onCollapse = jest.fn();
    const wrapper = mount(ExpandedCourses, { props: { data: { node_id: 'dndnode_1', course_node_id: [13, 11] }, onCollapse } });
    const press = (target) => {
      const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
      target.dispatchEvent(event);
      return event;
    };
    // A panel of another stack, an ordinary node, another key: nothing.
    press(focusIn('dndnode_2_expanded_courses_13'));
    press(focusIn('dndnode_1'));
    focusIn('dndnode_1_expanded_courses_11').dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    expect(onCollapse).not.toHaveBeenCalled();
    // Consumed by an element of the panel (an open information card): nothing.
    const inner = focusIn('dndnode_1_expanded_courses_13');
    inner.addEventListener('keydown', (e) => e.preventDefault());
    press(inner);
    expect(onCollapse).not.toHaveBeenCalled();
    // In a panel of this stack: fold.
    const event = press(focusIn('dndnode_1_expanded_courses_11'));
    expect(onCollapse).toHaveBeenCalledTimes(1);
    expect(event.defaultPrevented).toBe(true);
    // Folded: the listener is gone.
    wrapper.unmount();
    press(focusIn('dndnode_1_expanded_courses_11'));
    expect(onCollapse).toHaveBeenCalledTimes(1);
    document.body.innerHTML = '';
  });
});
