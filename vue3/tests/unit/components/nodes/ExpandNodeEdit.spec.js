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
 * Expand Node Edit.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ExpandNodeEdit from '../../../../components/nodes/ExpandNodeEdit.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

// Mock the store
jest.mock('vuex', () => ({
  useStore: jest.fn(),
}));

describe('ExpandNodeEdit.vue', () => {
  let storeMock;

  beforeEach(() => {
    storeMock = {
      state: {
        availablecourses: [
          {
            course_node_id: [1],
            fullname: 'Test Course',
            summary: 'This is a test course',
          },
        ],
        strings: {
          LIGHT_GRAY: 'ffffff',
        },
        wwwroot: 'wwwroot',
      },
    };

    useStore.mockReturnValue(storeMock);
  });

  it('renders correctly with given props', async () => {
    const wrapper = mount(ExpandNodeEdit, {
      props: {
        data: {
          course_id: 1,
          course_node_id_description: {
            1: {
              fullname: 'Test Course Fullname',
              description: 'Test Course Description',
            },
          },
          imagepaths: {
            1: '/path/to/image.jpg',
          },
          showCard: false,
        },
        zoomstep: 1,
      },
    });
    expect(wrapper.find('.card').exists()).toBe(false);
    wrapper.setProps({ data: { ...wrapper.props().data, showCard: true } });
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.card').exists()).toBe(true);
    expect(wrapper.find('.card-img').element.style.backgroundImage).toContain('/path/to/image.jpg');
    const courseName = wrapper.find('h5').text();
    expect(courseName).toBe('Test Course Fullname');
  });

  it('opens the course with a named link in a new tab (#17, TP-08)', async () => {
    const wrapper = mount(ExpandNodeEdit, {
      props: {
        data: {
          course_id: 1,
          course_node_id_description: {},
          imagepaths: {},
          showCard: true,
        },
        zoomstep: 1,
      },
    });
    const link = wrapper.find('a.icon-link');
    expect(link.attributes('href')).toBe('wwwroot/course/view.php?id=1');
    expect(link.attributes('target')).toBe('_blank');
    expect(link.attributes('rel')).toBe('noopener');
    expect(link.attributes('aria-label')).toBe('Go to course: Test Course (opens in a new tab)');
    expect(wrapper.find('button.icon-link').exists()).toBe(false);
  });

  it('carries the course id and a readable, unique name on the panel (#17)', async () => {
    storeMock.state.strings.expanded_course_label = '{$a->name} (Kurs-ID {$a->id})';
    const mk = (id) => mount(ExpandNodeEdit, {
      props: {
        data: { course_id: id, node_id: 'dndnode_1', course_node_id_description: { [id]: { fullname: 'Same name' } },
          imagepaths: {}, showCard: true },
        zoomstep: 1,
      },
    });
    const a = mk(4);
    const b = mk(5);
    await a.vm.$nextTick();
    await b.vm.$nextTick();
    const ca = a.find('[data-testid="expanded-course-4"]');
    const cb = b.find('[data-testid="expanded-course-5"]');
    expect(ca.attributes('data-course-id')).toBe('4');
    expect(ca.attributes('data-node-id')).toBe('dndnode_1');
    expect(ca.attributes('title')).toBe('Same name (Kurs-ID 4)');
    expect(cb.attributes('title')).toBe('Same name (Kurs-ID 5)');
  });

  it('names the panel by heading and describes it in visible text: short name, full name, id, status', async () => {
    Object.assign(storeMock.state.strings, {
      expanded_course_label_short: '{$a->name} ({$a->shortname}, Kurs-ID {$a->id})',
      expanded_course_shortname: 'Kurzname: {$a}',
      expanded_course_fullname: 'Kurs: {$a}',
      expanded_course_id: 'Kurs-ID {$a}',
      expanded_course_status_completed: 'Abgeschlossen',
      expanded_course_status_open: 'Nicht abgeschlossen',
    });
    const wrapper = mount(ExpandNodeEdit, {
      attachTo: document.body,
      props: {
        data: {
          course_id: 7, node_id: 'dndnode_3',
          course_node_id_description: { 7: { fullname: 'Given name', coursefullname: 'Real course', shortname: 'RC7' } },
          completion: { completioncriteria: { course_completed: { completed: { 7: true } } } },
          imagepaths: {}, showCard: true,
        },
        zoomstep: 1,
      },
    });
    const card = wrapper.find('[data-testid="expanded-course-7"]');
    expect(card.attributes('role')).toBe('group');
    expect(card.attributes('id')).toBe('adele-expanded-course-dndnode_3-7');
    expect(card.attributes('data-course-shortname')).toBe('RC7');
    expect(card.attributes('data-status')).toBe('completed');
    expect(card.attributes('title')).toBe('Given name (RC7, Kurs-ID 7)');
    // aria-labelledby and aria-describedby point at elements of this card.
    const heading = document.getElementById(card.attributes('aria-labelledby'));
    const desc = document.getElementById(card.attributes('aria-describedby'));
    expect(heading.textContent.trim()).toBe('Given name');
    expect(card.element.contains(desc)).toBe(true);
    expect(desc.textContent.trim()).toBe('Kurzname: RC7 · Kurs: Real course · Kurs-ID 7 · Abgeschlossen');
    wrapper.unmount();
  });

  it('one course or several, also one course in two stacks: no DOM id twice', async () => {
    const mk = (node, id) => mount(ExpandNodeEdit, {
      attachTo: document.body,
      props: {
        data: { course_id: id, node_id: node, course_node_id_description: { [id]: { fullname: 'C' + id, shortname: 'S' + id } },
          imagepaths: {}, showCard: true },
        zoomstep: 1,
      },
    });
    const single = [mk('dndnode_9', 1)];
    const ids = () => Array.from(document.querySelectorAll('[id]')).map((e) => e.id);
    expect(new Set(ids()).size).toBe(ids().length);
    const several = [mk('dndnode_1', 4), mk('dndnode_1', 5), mk('dndnode_2', 4)];
    // The information cards open: their ids join the page.
    for (const w of [...single, ...several]) {
      await w.find('.icon-container button.information').trigger('click');
    }
    expect(ids().filter((id) => id.endsWith('-information'))).toHaveLength(4);
    expect(new Set(ids()).size).toBe(ids().length);
    expect(ids().some((id) => id.includes('undefined'))).toBe(false);
    [...single, ...several].forEach((w) => w.unmount());
  });

  it('in the order of the stack swapped, every panel keeps its course', async () => {
    const data = (id) => ({ course_id: id, node_id: 'dndnode_1',
      course_node_id_description: { 4: { fullname: 'Vier', shortname: 'K4' }, 5: { fullname: 'Fünf', shortname: 'K5' } },
      imagepaths: {}, showCard: true });
    const read = (w) => {
      const c = w.find('[data-course-id]');
      return [c.attributes('data-course-id'), c.attributes('data-course-shortname'), w.find('h5').text(), c.attributes('id')];
    };
    const forward = [4, 5].map((id) => read(mount(ExpandNodeEdit, { props: { data: data(id), zoomstep: 1 } })));
    const backward = [5, 4].map((id) => read(mount(ExpandNodeEdit, { props: { data: data(id), zoomstep: 1 } })));
    expect(backward.reverse()).toEqual(forward);
    expect(forward[0]).toEqual(['4', 'K4', 'Vier', 'adele-expanded-course-dndnode_1-4']);
  });
});
