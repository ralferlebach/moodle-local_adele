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
 * The expand button of a course stack (mod_adele #17, re-audit TP-08):
 * a named button with its state (aria-expanded) and what it controls
 * (aria-controls: the cards of the stack's courses); Escape folds from the
 * button or from a panel, and the button keeps or takes the keyboard focus;
 * while the panels fold it rests without dropping the focus. The same for
 * both stack nodes (learner/teacher view and the parallel stack).
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { useStore } from 'vuex';
import CustomStagNodeEdit from '../../../../components/nodes/CustomStagNodeEdit.vue';
import OrCourses from '../../../../components/nodes/OrCourses.vue';
import { expandedStacks } from '../../../../composables/flowHelper/expandedStacks';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('@vue-flow/core', () => ({
  Handle: { name: 'Handle', render: () => null },
  Position: { Top: 'top', Bottom: 'bottom' },
  useVueFlow: () => ({ findNode: () => null, addNodes: () => {}, removeNodes: () => {} }),
}));

// The panels themselves are VueFlow nodes elsewhere; the stand-in only
// reports what the real one reports: folded in (done-folding), Escape in a
// panel (collapse).
let panels = null;
const ExpandedCoursesStub = defineComponent({
  name: 'ExpandedCourses',
  emits: ['done-folding', 'doneFolding', 'collapse'],
  setup(_, { emit }) {
    panels = { done: () => emit('doneFolding'), escape: () => emit('collapse') };
    return () => h('div', { class: 'expanded-courses-stub' });
  },
});

const stubs = {
  ExpandedCourses: ExpandedCoursesStub,
  NodeInformation: true, UserInformation: true, CourseCarousel: true, CourseCompletion: true,
  ProgressBar: true, MasterConditions: true, CompletionOutPutItem: true, RestrictionOutPutItem: true,
  TeacherCourseAccess: true, Handle: true,
};

const strings = {
  expand_courses: 'Expand courses', collapse_courses: 'Collapse courses', nodes_collection: 'Collection',
  LIGHT_GRAY: '#eee', LIGHT_STEEL_BLUE: '#bcd',
};

const data = () => ({
  node_id: 'dndnode_1', fullname: 'Stapel A', course_node_id: [12, 11],
  completion: {
    singlerestrictionnode: [], restrictionnode: { valid: true }, feedback: { status: 'accessible' },
    completioncriteria: { course_completed: { completed: {} } },
  },
  progress: 0,
});

const learningpath = () => ({
  json: { tree: { nodes: [{ id: 'dndnode_1', parentCourse: ['starting_node'], data: {} }] }, user_path_relation: {} },
});

const nodes = {
  'stack node (learner and teacher view)': (attachTo) => mount(CustomStagNodeEdit, {
    attachTo, props: { data: data(), learningpath: learningpath(), zoomstep: 1 }, global: { stubs },
  }),
  'parallel stack (OrCourses)': (attachTo) => mount(OrCourses, {
    attachTo, props: { data: data(), learningpath: learningpath(), editorview: false }, global: { stubs },
  }),
};

describe.each(Object.entries(nodes))('%s: the expand button', (_, make) => {
  let wrapper;
  const toggle = () => wrapper.find('[data-testid="stack-toggle-dndnode_1"]');

  beforeEach(() => {
    panels = null;
    Object.keys(expandedStacks).forEach((k) => delete expandedStacks[k]);
    useStore.mockReturnValue({
      state: { strings, view: 'teacher', canedit: false, feedbacksettings: {}, lpuserpathrelation: {}, wwwroot: '' },
    });
    wrapper = make(document.body);
  });

  afterEach(() => {
    wrapper.unmount();
    document.body.innerHTML = '';
  });

  it('is a named button with its state; it controls the stack\'s cards only while they are shown', async () => {
    const b = toggle();
    expect(b.element.tagName).toBe('BUTTON');
    expect(b.attributes('type')).toBe('button');
    expect(b.attributes('id')).toBe('adele-stack-toggle-dndnode_1');
    expect(b.attributes('aria-label')).toBe('Expand courses: Stapel A');
    expect(b.attributes('aria-expanded')).toBe('false');
    expect(b.attributes('aria-controls')).toBeUndefined();
    expect(b.find('i').attributes('aria-hidden')).toBe('true');
    await b.trigger('click');
    expect(b.attributes('aria-expanded')).toBe('true');
    expect(b.attributes('aria-label')).toBe('Collapse courses: Stapel A');
    // In the order of the stack, by course id.
    expect(b.attributes('aria-controls')).toBe('adele-expanded-course-dndnode_1-12 adele-expanded-course-dndnode_1-11');
  });

  it('rests while the panels fold - marked, not disabled, so the focus stays on it', async () => {
    const b = toggle();
    b.element.focus();
    await b.trigger('click');
    expect(b.attributes('disabled')).toBeUndefined();
    expect(b.attributes('aria-disabled')).toBe('true');
    expect(document.activeElement).toBe(b.element);
    // A second press meanwhile changes nothing.
    await b.trigger('click');
    expect(b.attributes('aria-expanded')).toBe('true');
    panels.done();
    await wrapper.vm.$nextTick();
    expect(b.attributes('aria-disabled')).toBeUndefined();
  });

  it('Escape on the button folds the stack; the focus stays on the button', async () => {
    const b = toggle();
    await b.trigger('click');
    panels.done();
    await wrapper.vm.$nextTick();
    b.element.focus();
    await b.trigger('keydown', { key: 'Escape' });
    expect(b.attributes('aria-expanded')).toBe('false');
    expect(wrapper.find('.expanded-courses-stub').exists()).toBe(false);
    expect(document.activeElement).toBe(b.element);
  });

  it('Escape in a panel folds the stack and gives the button the focus', async () => {
    const b = toggle();
    await b.trigger('click');
    panels.done();
    await wrapper.vm.$nextTick();
    const elsewhere = document.createElement('button');
    document.body.appendChild(elsewhere);
    elsewhere.focus();
    const focus = jest.spyOn(b.element, 'focus');
    panels.escape();
    await wrapper.vm.$nextTick();
    expect(b.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(b.element);
    // The focus moved by a key is to be shown (Firefox, CI 2026-10-18).
    expect(focus).toHaveBeenCalledWith({ preventScroll: true, focusVisible: true });
  });
});
