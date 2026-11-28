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

import { flushPromises, mount } from '@vue/test-utils';
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

describe('TeacherCourseAccess - mode "request" (TP2)', () => {
  const REQ = [
    { courseid: 11, shortname: 'MAT-101', fullname: 'Mathematics I', nodeids: ['dndnode_1'], status: 'open', url: 'https://example.test/course/view.php?id=11' },
    { courseid: 12, shortname: 'STAT-P', fullname: 'Statistics', nodeids: ['dndnode_1'], status: 'request' },
    { courseid: 13, shortname: 'PRAX', fullname: 'Practice II', nodeids: ['dndnode_1'], status: 'request_copy' },
  ];
  const TEXTS = {
    ...STRINGS,
    teacher_course_request: 'Request access',
    teacher_course_requestcopy: 'Prepare request',
    teacher_course_request_label: 'Request access to the course {$a->shortname} – {$a->fullname}',
    teacher_course_requestcopy_label: 'Prepare a request for access to the course {$a->shortname} – {$a->fullname}',
    teacher_course_status_request: 'No access – you can ask',
    teacher_course_status_requestcopy: 'No access – nobody to ask',
    teacher_course_status_requested: 'Request sent',
    teacher_request_sent: 'Request for {$a->course} sent to {$a->recipient}.',
  };

  /**
   * Mount with request rows and a dialog stub that can report "sent".
   */
  function requests() {
    const mutations = { setActiveNode: jest.fn(), announce: jest.fn() };
    const store = makeStore({
      view: 'teacher',
      activeNodeId: 'dndnode_1',
      nodeCourseAccess: { loading: false, mode: 'request', courses: REQ },
      strings: TEXTS,
    }, { mutations });
    const wrapper = mount(TeacherCourseAccess, {
      props: { nodeId: 'dndnode_1', courseIds: [11, 12, 13] },
      global: {
        plugins: [store],
        stubs: { CourseAccessRequestDialog: { name: 'CourseAccessRequestDialog', props: ['course'], template: '<div data-testid="dialog-stub" />' } },
      },
      attachTo: document.body,
    });
    return { wrapper, mutations };
  }

  it('each row: its own status and action - link, request, or prepare', () => {
    const { wrapper } = requests();
    const rows = wrapper.findAll('li.adele-course-access-row');
    expect(rows.map((r) => r.find('.adele-course-access-status').text()))
      .toEqual(['Access', 'No access – you can ask', 'No access – nobody to ask']);
    expect(rows[0].find('a').exists()).toBe(true);
    expect(rows[0].find('button').exists()).toBe(false);
    const ask = rows[1].find('button');
    expect(ask.attributes('type')).toBe('button');
    expect(ask.text()).toBe('Request access');
    expect(ask.attributes('aria-label')).toBe('Request access to the course STAT-P – Statistics');
    expect(rows[1].find('a').exists()).toBe(false);
    expect(rows[2].find('button').text()).toBe('Prepare request');
    expect(rows[2].find('button').attributes('aria-label')).toBe('Prepare a request for access to the course PRAX – Practice II');
    wrapper.unmount();
  });

  it('the button opens the dialog for exactly that course; closing gives the focus back to the button', async () => {
    const { wrapper } = requests();
    expect(wrapper.find('[data-testid="dialog-stub"]').exists()).toBe(false);
    const ask = wrapper.find('[data-testid="node-course-request-12"]');
    ask.element.focus();
    await ask.trigger('click');
    const stub = wrapper.findComponent({ name: 'CourseAccessRequestDialog' });
    expect(stub.props('course').courseid).toBe(12);
    document.body.focus();
    stub.vm.$emit('close');
    await wrapper.vm.$nextTick();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('[data-testid="dialog-stub"]').exists()).toBe(false);
    expect(document.activeElement).toBe(ask.element);
    wrapper.unmount();
  });

  it('sent: the row says so and it is announced; the other rows stay as they were', async () => {
    const { wrapper, mutations } = requests();
    await wrapper.find('[data-testid="node-course-request-12"]').trigger('click');
    wrapper.findComponent({ name: 'CourseAccessRequestDialog' }).vm.$emit('sent', 'Olga Owner');
    await wrapper.vm.$nextTick();
    const rows = wrapper.findAll('li.adele-course-access-row');
    expect(rows[1].find('.adele-course-access-status').text()).toBe('Request sent');
    expect(rows[2].find('.adele-course-access-status').text()).toBe('No access – nobody to ask');
    expect(mutations.announce).toHaveBeenCalledWith(expect.anything(), 'Request for STAT-P sent to Olga Owner.');
    wrapper.unmount();
  });
});

describe('TeacherCourseAccess - enrolment modes (TP4)', () => {
  const ROWS = [
    { courseid: 11, shortname: 'MAT-101', fullname: 'Mathematics I', nodeids: ['dndnode_1'], status: 'enrol', role: 'Non-editing teacher' },
    { courseid: 12, shortname: 'STAT-P', fullname: 'Statistics', nodeids: ['dndnode_1'], status: 'unavailable', reason: 'The course is hidden.' },
  ];
  const TEXTS = {
    ...STRINGS,
    teacher_course_enrol_teacher: 'Enrol as non-editing teacher',
    teacher_course_enrol_label: '{$a->action}: course {$a->shortname} – {$a->fullname}',
    teacher_course_status_enrol: 'No access – enrolment possible',
    teacher_course_status_unavailable: 'No access – not available',
    teacher_enrol_done: 'Enrolled into {$a->course} as {$a->role}.',
  };

  /**
   * Mount with enrolment rows; fetchNodeCourseAccess turns MAT-101 open.
   */
  function enrolment() {
    const mutations = {
      setActiveNode: jest.fn(),
      announce: jest.fn(),
      setNodeCourseAccess: (s, v) => { s.nodeCourseAccess = v; },
    };
    const actions = {
      fetchNodeCourseAccess: jest.fn((context) => context.commit('setNodeCourseAccess', {
        loading: false,
        mode: 'enrol_teacher',
        courses: [{ ...ROWS[0], status: 'open', url: 'https://example.test/course/view.php?id=11' }, ROWS[1]],
      })),
    };
    const store = makeStore({
      view: 'teacher',
      activeNodeId: 'dndnode_1',
      nodeCourseAccess: { loading: false, mode: 'enrol_teacher', courses: ROWS },
      strings: TEXTS,
    }, { mutations, actions });
    const wrapper = mount(TeacherCourseAccess, {
      props: { nodeId: 'dndnode_1', courseIds: [11, 12] },
      global: {
        plugins: [store],
        stubs: { CourseEnrolConfirmDialog: { name: 'CourseEnrolConfirmDialog', props: ['course'], template: '<div data-testid="enrol-stub" />' } },
      },
      attachTo: document.body,
    });
    return { wrapper, mutations, actions };
  }

  it('enrol: a button named by mode and course; unavailable: the reason, no button', () => {
    const { wrapper } = enrolment();
    const rows = wrapper.findAll('li.adele-course-access-row');
    expect(rows.map((r) => r.find('.adele-course-access-status').text()))
      .toEqual(['No access – enrolment possible', 'No access – not available']);
    const button = rows[0].find('button');
    expect(button.text()).toBe('Enrol as non-editing teacher');
    expect(button.attributes('aria-label')).toBe('Enrol as non-editing teacher: course MAT-101 – Mathematics I');
    expect(rows[1].find('button').exists()).toBe(false);
    expect(rows[1].find('a').exists()).toBe(false);
    expect(rows[1].find('[data-testid="node-course-reason-12"]').text()).toBe('The course is hidden.');
    wrapper.unmount();
  });

  it('confirmed: asked again, announced, and the focus on the new link', async () => {
    const { wrapper, mutations, actions } = enrolment();
    await wrapper.find('[data-testid="node-course-enrol-11"]').trigger('click');
    const stub = wrapper.findComponent({ name: 'CourseEnrolConfirmDialog' });
    expect(stub.props('course').courseid).toBe(11);
    stub.vm.$emit('enrolled', 'Non-editing teacher');
    await flushPromises();
    await wrapper.vm.$nextTick();
    expect(actions.fetchNodeCourseAccess).toHaveBeenCalled();
    expect(mutations.announce).toHaveBeenCalledWith(expect.anything(), 'Enrolled into MAT-101 as Non-editing teacher.');
    expect(document.activeElement).toBe(wrapper.find('[data-testid="node-course-11"] a').element);
    wrapper.unmount();
  });
});

describe('TeacherCourseAccess - edges of the enrolment rows', () => {
  /**
   * Mount with given rows and mode.
   *
   * @param {object[]} courses
   * @param {string} mode
   * @param {object} actions
   */
  function edge(courses, mode, actions = {}) {
    const mutations = { setActiveNode: jest.fn(), announce: jest.fn() };
    const store = makeStore({
      view: 'teacher',
      activeNodeId: 'dndnode_1',
      nodeCourseAccess: { loading: false, mode, courses },
      strings: { ...STRINGS, teacher_course_enrol_student: 'Enrol as participant' },
    }, { mutations, actions });
    const wrapper = mount(TeacherCourseAccess, {
      props: { nodeId: 'dndnode_1', courseIds: courses.map((c) => c.courseid) },
      global: {
        plugins: [store],
        stubs: {
          CourseEnrolConfirmDialog: { name: 'CourseEnrolConfirmDialog', props: ['course'], template: '<div data-testid="enrol-stub" />' },
          CourseAccessRequestDialog: { name: 'CourseAccessRequestDialog', props: ['course'], template: '<div />' },
        },
      },
      attachTo: document.body,
    });
    return { wrapper, mutations };
  }

  it('the label follows the mode; unavailable without a reason: no reason line; open without a URL: no link', () => {
    const { wrapper } = edge([
      { courseid: 1, shortname: 'A', fullname: 'Alpha', status: 'enrol', role: 'Teacher' },
      { courseid: 2, shortname: 'B', fullname: 'Beta', status: 'unavailable' },
      { courseid: 3, shortname: 'C', fullname: 'Gamma', status: 'open' },
    ], 'enrol_editingteacher');
    expect(wrapper.find('[data-testid="node-course-enrol-1"]').text()).toBe('teacher_course_enrol_editingteacher');
    expect(wrapper.find('[data-testid="node-course-reason-2"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="node-course-3"] a').exists()).toBe(false);
    wrapper.unmount();
  });

  it('confirmation closed: the focus back on the button; enrolled with no link afterwards: nothing to focus', async () => {
    const actions = { fetchNodeCourseAccess: jest.fn() };
    const { wrapper, mutations } = edge([{ courseid: 1, shortname: 'A', fullname: 'Alpha', status: 'enrol', role: 'Student' }], 'enrol_student', actions);
    const button = wrapper.find('[data-testid="node-course-enrol-1"]');
    await button.trigger('click');
    wrapper.findComponent({ name: 'CourseEnrolConfirmDialog' }).vm.$emit('close');
    await wrapper.vm.$nextTick();
    await wrapper.vm.$nextTick();
    expect(document.activeElement).toBe(button.element);

    await button.trigger('click');
    wrapper.findComponent({ name: 'CourseEnrolConfirmDialog' }).vm.$emit('enrolled', 'Student');
    await flushPromises();
    expect(actions.fetchNodeCourseAccess).toHaveBeenCalled();
    expect(mutations.announce).toHaveBeenCalledWith(expect.anything(), 'teacher_enrol_done');
    expect(wrapper.find('[data-testid="enrol-stub"]').exists()).toBe(false);
    wrapper.unmount();
  });

  it('no answer yet at all: checking, no rows', () => {
    const mutations = { setActiveNode: jest.fn(), announce: jest.fn() };
    const store = makeStore({ view: 'teacher', activeNodeId: 'dndnode_1', nodeCourseAccess: null, strings: STRINGS }, { mutations });
    const wrapper = mount(TeacherCourseAccess, {
      props: { nodeId: 'dndnode_1', courseIds: [1] },
      global: { plugins: [store] },
      attachTo: document.body,
    });
    expect(wrapper.find('.adele-course-access-note').attributes('role')).toBe('status');
    expect(wrapper.findAll('li')).toHaveLength(0);
    wrapper.unmount();
  });
});
