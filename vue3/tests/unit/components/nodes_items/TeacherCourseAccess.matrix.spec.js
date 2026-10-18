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
 * TeacherCourseAccess - the courses of the active tile in the teacher view
 * of mod_adele (issue "Kurszugriff in der Lehrendenansicht", TP1).
 *
 * View x activation decides whether it is there at all; the server's answer
 * decides what each row offers. Rows follow the node's own order, a link
 * exists only for a course the server calls open, the full name is always
 * there as text, equal short names are told apart.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import TeacherCourseAccess from '../../../../components/nodes_items/TeacherCourseAccess.vue';
import { makeStore } from '../../fixtures/storeFactory';

const STRINGS = {
  teacher_courses_heading: 'Courses ({$a})',
  teacher_course_open: 'Open course',
  teacher_course_open_label: 'Open course {$a->shortname} – {$a->fullname} in a new tab',
  teacher_course_status_open: 'Access',
  teacher_course_status_none: 'No course access',
  teacher_course_id: 'ID {$a}',
  teacher_courses_collapse: 'Hide course actions',
  teacher_courses_expand: 'Show course actions',
  teacher_courses_loading: 'Checking course access …',
  teacher_courses_error: 'The course access could not be checked.',
  teacher_courses_none: 'No course is linked to this node.',
  teacher_courses_announce: '{$a->open} of {$a->all} courses of this node are open to you.',
};

const COURSES = [
  { courseid: 11, shortname: 'MAT-101', fullname: 'Mathematics I', nodeids: ['dndnode_1'], status: 'open', url: 'https://example.test/course/view.php?id=11' },
  { courseid: 12, shortname: 'STAT-P', fullname: 'Statistics', nodeids: ['dndnode_1'], status: 'none' },
  { courseid: 13, shortname: 'PRAX', fullname: 'Practice II', nodeids: ['dndnode_1'], status: 'open', url: 'https://example.test/course/view.php?id=13' },
];

/**
 * Mount the section for one tile.
 *
 * @param {object} state Store state.
 * @param {object} props Component props.
 */
function section(state = {}, props = {}) {
  const mutations = {
    setActiveNode: jest.fn((s, id) => { s.activeNodeId = id; }),
    announce: jest.fn(),
  };
  const store = makeStore({
    view: 'teacher',
    activeNodeId: 'dndnode_1',
    nodeCourseAccess: { loading: false, mode: 'existing_only', courses: COURSES },
    strings: STRINGS,
    ...state,
  }, { mutations });
  const wrapper = mount(TeacherCourseAccess, {
    props: { nodeId: 'dndnode_1', courseIds: [13, 11, 12], ...props },
    global: { plugins: [store] },
    attachTo: document.body,
  });
  return { wrapper, store, mutations };
}

describe('TeacherCourseAccess - where it appears', () => {
  it.each([
    ['teacher', 'dndnode_1', true],
    ['teacher', 'dndnode_2', false],
    ['teacher', null, false],
    ['student', 'dndnode_1', false],
    ['manager', 'dndnode_1', false],
    ['assistant', 'dndnode_1', false],
  ])('view=%s active=%s: shown %s', (view, active, shown) => {
    const { wrapper } = section({ view, activeNodeId: active });
    expect(wrapper.find('[data-testid="node-course-access"]').exists()).toBe(shown);
    wrapper.unmount();
  });
});

describe('TeacherCourseAccess - one row per course, in the node\'s order', () => {
  it('rows follow the node, not the answer; status in words; a link only where open', () => {
    const { wrapper } = section();
    const rows = wrapper.findAll('li.adele-course-access-row');
    expect(rows.map((row) => row.attributes('data-testid'))).toEqual(['node-course-13', 'node-course-11', 'node-course-12']);
    expect(rows.map((row) => row.attributes('data-access'))).toEqual(['open', 'open', 'none']);
    expect(rows.map((row) => row.find('.adele-course-access-status').text())).toEqual(['Access', 'Access', 'No course access']);

    const link = rows[1].find('a');
    expect(link.attributes('href')).toBe('https://example.test/course/view.php?id=11');
    expect(link.attributes('target')).toBe('_blank');
    expect(link.attributes('rel')).toBe('noopener noreferrer');
    expect(link.attributes('aria-label')).toBe('Open course MAT-101 – Mathematics I in a new tab');
    expect(rows[2].find('a').exists()).toBe(false);
    expect(rows[2].find('button').exists()).toBe(false);
    wrapper.unmount();
  });

  it('the full name is text of its own, and the link is described by it', () => {
    const { wrapper } = section();
    const row = wrapper.find('[data-testid="node-course-11"]');
    const full = row.find('.adele-course-access-full');
    expect(full.text()).toBe('Mathematics I');
    expect(row.find('a').attributes('aria-describedby')).toBe(full.attributes('id'));
    expect(row.find('.adele-course-access-short').attributes('title')).toBe('Mathematics I');
    wrapper.unmount();
  });

  it('equal short names are told apart by the course id', () => {
    const twins = [
      { courseid: 21, shortname: 'SAME', fullname: 'First', nodeids: ['dndnode_1'], status: 'none' },
      { courseid: 22, shortname: 'SAME', fullname: 'Second', nodeids: ['dndnode_1'], status: 'none' },
    ];
    const { wrapper } = section({ nodeCourseAccess: { loading: false, courses: twins } }, { courseIds: [21, 22] });
    expect(wrapper.findAll('.adele-course-access-short').map((s) => s.text())).toEqual(['SAME (ID 21)', 'SAME (ID 22)']);
    wrapper.unmount();
  });

  it('a course the server does not list gets no row and no action', () => {
    const { wrapper } = section({}, { courseIds: [11, 99] });
    expect(wrapper.findAll('li.adele-course-access-row')).toHaveLength(1);
    expect(wrapper.find('h6').text()).toBe('Courses (1)');
    wrapper.unmount();
  });
});

describe('TeacherCourseAccess - states of the answer', () => {
  it.each([
    ['loading', { loading: true, courses: [] }, 'Checking course access …', 'status'],
    ['error', { loading: false, error: true, courses: [] }, 'The course access could not be checked.', 'alert'],
  ])('%s: a note, no link', (_label, answer, text, role) => {
    const { wrapper } = section({ nodeCourseAccess: answer });
    const note = wrapper.find('.adele-course-access-note');
    expect(note.text()).toBe(text);
    expect(note.attributes('role')).toBe(role);
    expect(wrapper.findAll('a')).toHaveLength(0);
    wrapper.unmount();
  });

  it('no course: says so', () => {
    const { wrapper } = section({}, { courseIds: [] });
    expect(wrapper.find('.adele-course-access-note').text()).toBe('No course is linked to this node.');
    wrapper.unmount();
  });
});

describe('TeacherCourseAccess - keyboard and assistive technology', () => {
  it('the toggle says what it controls and whether it is open', async () => {
    const { wrapper } = section();
    const toggle = wrapper.find('button.adele-course-access-toggle');
    const list = wrapper.find('ul');
    expect(toggle.attributes('aria-controls')).toBe(list.attributes('id'));
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(toggle.text()).toBe('Hide course actions');
    await toggle.trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(toggle.text()).toBe('Show course actions');
    expect(list.isVisible()).toBe(false);
    wrapper.unmount();
  });

  it('the list is named by the heading', () => {
    const { wrapper } = section();
    const heading = wrapper.find('h6');
    expect(wrapper.find('ul').attributes('aria-labelledby')).toBe(heading.attributes('id'));
    expect(wrapper.find('section').attributes('aria-labelledby')).toBe(heading.attributes('id'));
    wrapper.unmount();
  });

  it('Escape closes the actions (the tile is no longer active)', async () => {
    const { wrapper, mutations } = section();
    await wrapper.find('section').trigger('keydown', { key: 'Escape' });
    expect(mutations.setActiveNode).toHaveBeenCalledWith(expect.anything(), null);
    wrapper.unmount();
  });

  it('a click inside does not reach the tile (no zoom, no navigation of the tile)', async () => {
    const { wrapper } = section();
    const outer = jest.fn();
    wrapper.element.parentElement.addEventListener('click', outer);
    await wrapper.find('button.adele-course-access-toggle').trigger('click');
    expect(outer).not.toHaveBeenCalled();
    wrapper.element.parentElement.removeEventListener('click', outer);
    wrapper.unmount();
  });

  it('the result is announced: how many of the courses are open', async () => {
    const { wrapper, mutations } = section({ activeNodeId: null });
    wrapper.vm.$store.state.activeNodeId = 'dndnode_1';
    await wrapper.vm.$nextTick();
    expect(mutations.announce).toHaveBeenCalledWith(expect.anything(), '2 of 3 courses of this node are open to you.');
    wrapper.unmount();
  });
});
