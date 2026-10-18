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
 * CourseEnrolConfirmDialog - confirm an enrolment into a node course
 * (local_adele #600, TP4).
 *
 * Course, role and what it may do, source, revocability - before anything
 * happens; only "Enrol" enrols; a refusal stays in the dialog. Modal: named,
 * focus inside and kept there, Escape cancels.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { flushPromises, mount } from '@vue/test-utils';
import CourseEnrolConfirmDialog from '../../../../components/nodes_items/CourseEnrolConfirmDialog.vue';
import { makeStore } from '../../fixtures/storeFactory';

const COURSE = {
  courseid: 12, label: 'STAT-P', fullname: 'Statistics', status: 'enrol',
  role: 'Non-editing teacher', roledescription: '<p>May grade, may not edit.</p>',
};
const TEXTS = {
  teacher_enrol_title: 'Enrol into {$a}',
  teacher_enrol_role: 'Role in the course: {$a}',
  teacher_enrol_source: 'Source: {$a}',
  teacher_enrol_revocable: 'It ends with its basis.',
  teacher_enrol_confirm: 'Enrol',
  teacher_request_cancel: 'Cancel',
};

/**
 * Mount the dialog.
 *
 * @param {Function} enrol The server's answer.
 */
function dialog(enrol = () => Promise.resolve({ enrolled: true, role: 'Non-editing teacher' })) {
  const actions = { enrolInNodeCourse: jest.fn(enrol) };
  const store = makeStore({
    strings: TEXTS,
    nodeCourseAccess: { loading: false, mode: 'enrol_teacher', source: 'Activity "Path" in the course "Host"', courses: [] },
  }, { actions });
  const wrapper = mount(CourseEnrolConfirmDialog, {
    props: { course: COURSE },
    global: { plugins: [store], stubs: { teleport: true } },
    attachTo: document.body,
  });
  return { wrapper, actions };
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('CourseEnrolConfirmDialog', () => {
  it('says everything before anything happens; nothing is enrolled by opening', async () => {
    const { wrapper, actions } = dialog();
    await flushPromises();
    const box = wrapper.find('[role="dialog"]');
    expect(box.attributes('aria-modal')).toBe('true');
    expect(document.getElementById(box.attributes('aria-labelledby')).textContent.trim()).toBe('Enrol into Statistics');
    expect(wrapper.find('[data-testid="enrol-confirm-role"]').text()).toBe('Role in the course: Non-editing teacher');
    expect(wrapper.find('[data-testid="enrol-confirm-rights"]').text()).toBe('May grade, may not edit.');
    expect(wrapper.find('[data-testid="enrol-confirm-source"]').text()).toBe('Source: Activity "Path" in the course "Host"');
    expect(document.activeElement).toBe(wrapper.find('[data-testid="enrol-confirm"]').element);
    expect(actions.enrolInNodeCourse).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('Enrol: the server is asked for this course; the role it names is reported', async () => {
    const { wrapper, actions } = dialog();
    await wrapper.find('[data-testid="enrol-confirm"]').trigger('click');
    await flushPromises();
    expect(actions.enrolInNodeCourse.mock.calls[0][1]).toBe(12);
    expect(wrapper.emitted('enrolled')).toEqual([['Non-editing teacher']]);
    wrapper.unmount();
  });

  it('refused: the reason as an alert, nothing reported', async () => {
    const { wrapper } = dialog(() => Promise.reject(new Error('The person who chose this mode no longer has the authority.')));
    await wrapper.find('[data-testid="enrol-confirm"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[role="alert"]').text()).toBe('The person who chose this mode no longer has the authority.');
    expect(wrapper.emitted('enrolled')).toBeUndefined();
    wrapper.unmount();
  });

  it('Escape and Cancel close without enrolling; Tab stays inside', async () => {
    const { wrapper, actions } = dialog();
    await flushPromises();
    const box = wrapper.find('[role="dialog"]');
    wrapper.find('[data-testid="enrol-cancel"]').element.focus();
    await box.trigger('keydown', { key: 'Tab' });
    expect(document.activeElement).toBe(wrapper.find('[data-testid="enrol-confirm"]').element);
    await box.trigger('keydown', { key: 'Escape' });
    await wrapper.find('[data-testid="enrol-cancel"]').trigger('click');
    expect(wrapper.emitted('close')).toHaveLength(2);
    expect(actions.enrolInNodeCourse).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});

describe('CourseEnrolConfirmDialog - edges', () => {
  /**
   * Mount with a given state and course.
   *
   * @param {object} state
   * @param {object} course
   * @param {Function} enrol
   */
  function edge(state, course, enrol) {
    const actions = { enrolInNodeCourse: jest.fn(enrol) };
    const store = makeStore({ strings: { ...TEXTS, teacher_enrol_error: 'Could not enrol.' }, ...state }, { actions });
    const wrapper = mount(CourseEnrolConfirmDialog, {
      props: { course },
      global: { plugins: [store], stubs: { teleport: true } },
      attachTo: document.body,
    });
    return { wrapper, actions };
  }

  it('without a role description and without a known source: no rights block, an empty source', async () => {
    const { wrapper } = edge({ nodeCourseAccess: null }, { ...COURSE, roledescription: '' }, () => Promise.resolve({}));
    await flushPromises();
    expect(wrapper.find('[data-testid="enrol-confirm-rights"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="enrol-confirm-source"]').text()).toBe('Source:');
    wrapper.unmount();
  });

  it('the server names no role: the row\'s role is reported', async () => {
    const { wrapper } = edge({}, COURSE, () => Promise.resolve({ enrolled: true }));
    await wrapper.find('[data-testid="enrol-confirm"]').trigger('click');
    await flushPromises();
    expect(wrapper.emitted('enrolled')).toEqual([['Non-editing teacher']]);
    wrapper.unmount();
  });

  it('a refusal without a message: the own text; while asking, a second click and Cancel do nothing', async () => {
    let reject;
    const { wrapper, actions } = edge({}, COURSE, () => new Promise((resolve, no) => { reject = no; }));
    await wrapper.find('[data-testid="enrol-confirm"]').trigger('click');
    wrapper.vm.enrol();
    wrapper.vm.cancel();
    expect(actions.enrolInNodeCourse).toHaveBeenCalledTimes(1);
    expect(wrapper.emitted('close')).toBeUndefined();
    reject({});
    await flushPromises();
    expect(wrapper.find('[role="alert"]').text()).toBe('Could not enrol.');
    wrapper.unmount();
  });

  it('a refusal carrying its message in error.message', async () => {
    const { wrapper } = edge({}, COURSE, () => Promise.reject({ error: { message: 'Nested reason.' } }));
    await wrapper.find('[data-testid="enrol-confirm"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[role="alert"]').text()).toBe('Nested reason.');
    wrapper.unmount();
  });

  it('a click on the backdrop cancels; a click inside does not', async () => {
    const { wrapper } = edge({}, COURSE, () => Promise.resolve({}));
    await wrapper.find('[role="dialog"]').trigger('mousedown');
    expect(wrapper.emitted('close')).toBeUndefined();
    await wrapper.find('.adele-access-request-backdrop').trigger('mousedown');
    expect(wrapper.emitted('close')).toHaveLength(1);
    wrapper.unmount();
  });
});
