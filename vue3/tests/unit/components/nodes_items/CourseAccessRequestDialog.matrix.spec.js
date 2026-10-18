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
 * CourseAccessRequestDialog - "Request access" to one node course
 * (local_adele #600, TP2).
 *
 * Nothing goes out before "Send"; what goes out is the teacher's edited
 * text to the chosen recipient; a refusal keeps the dialog and says why;
 * without anybody to ask there is no "Send", only copying. A modal dialog:
 * named by its heading, the focus inside and kept there, Escape cancels.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { flushPromises, mount } from '@vue/test-utils';
import CourseAccessRequestDialog from '../../../../components/nodes_items/CourseAccessRequestDialog.vue';
import { makeStore } from '../../fixtures/storeFactory';

const COURSE = { courseid: 12, label: 'STAT-P', fullname: 'Statistics' };
const ONE = {
  courseid: 12,
  coursename: 'Statistics',
  recipients: [{ id: 7, fullname: 'Olga Owner' }],
  message: 'I teach in "Host" and need access to "Statistics".',
  fallback: '',
  maxlength: 4000,
};

/**
 * Mount the dialog with the server answering as given.
 *
 * @param {object} options { fetch, send, teleport }
 */
async function dialog(options = {}) {
  const actions = {
    fetchAccessRequest: jest.fn(options.fetch || (() => Promise.resolve(ONE))),
    sendAccessRequest: jest.fn(options.send || (() => Promise.resolve({ sent: true, recipient: 'Olga Owner' }))),
  };
  const store = makeStore({}, { actions });
  const wrapper = mount(CourseAccessRequestDialog, {
    props: { course: COURSE },
    global: { plugins: [store], stubs: options.teleport ? {} : { teleport: true } },
    attachTo: document.body,
  });
  await flushPromises();
  return { wrapper, actions };
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('CourseAccessRequestDialog - a modal dialog', () => {
  it('outside the graph: in the page body, named by its heading, the focus in it', async () => {
    const { wrapper } = await dialog({ teleport: true });
    const box = document.body.querySelector('[data-testid="access-request-dialog"]');
    expect(box.parentElement.parentElement).toBe(document.body);
    expect(box.getAttribute('role')).toBe('dialog');
    expect(box.getAttribute('aria-modal')).toBe('true');
    const heading = document.getElementById(box.getAttribute('aria-labelledby'));
    expect(heading.textContent.trim()).toBe('teacher_request_title');
    expect(box.contains(document.activeElement)).toBe(true);
    expect(document.activeElement.getAttribute('data-testid')).toBe('access-request-message');
    wrapper.unmount();
  });

  it('while asking the server: a status, no Send', async () => {
    const { wrapper } = await dialog({ fetch: () => new Promise(() => {}) });
    expect(wrapper.find('[role="status"]').text()).toBe('teacher_request_loading');
    expect(wrapper.find('[data-testid="access-request-send"]').exists()).toBe(false);
    wrapper.unmount();
  });

  it('Escape cancels; Tab stays inside', async () => {
    const { wrapper } = await dialog();
    const box = wrapper.find('[data-testid="access-request-dialog"]');
    const cancel = wrapper.find('[data-testid="access-request-cancel"]').element;
    cancel.focus();
    await box.trigger('keydown', { key: 'Tab' });
    expect(document.activeElement).toBe(wrapper.find('[data-testid="access-request-message"]').element);
    await box.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(cancel);
    await box.trigger('keydown', { key: 'Escape' });
    expect(wrapper.emitted('close')).toHaveLength(1);
    wrapper.unmount();
  });
});

describe('CourseAccessRequestDialog - nothing goes out before "Send"', () => {
  it('opening and editing send nothing; Send sends the edited text to the recipient', async () => {
    const { wrapper, actions } = await dialog();
    expect(wrapper.find('[data-testid="access-request-recipient"]').text()).toBe('Olga Owner');
    const field = wrapper.find('[data-testid="access-request-message"]');
    expect(field.element.value).toBe(ONE.message);
    await field.setValue('My own words.');
    expect(actions.sendAccessRequest).not.toHaveBeenCalled();

    await wrapper.find('[data-testid="access-request-send"]').trigger('click');
    await flushPromises();
    expect(actions.sendAccessRequest).toHaveBeenCalledTimes(1);
    expect(actions.sendAccessRequest.mock.calls[0][1]).toEqual({ courseid: 12, recipientid: 7, message: 'My own words.' });
    expect(wrapper.emitted('sent')).toEqual([['Olga Owner']]);
    wrapper.unmount();
  });

  it('several recipients: the one chosen gets it', async () => {
    const two = { ...ONE, recipients: [{ id: 7, fullname: 'Olga Owner' }, { id: 9, fullname: 'Paul Partner' }] };
    const { wrapper, actions } = await dialog({ fetch: () => Promise.resolve(two) });
    const select = wrapper.find('select[data-testid="access-request-recipient"]');
    expect(select.findAll('option').map((o) => o.text())).toEqual(['Olga Owner', 'Paul Partner']);
    await select.setValue(9);
    await wrapper.find('[data-testid="access-request-send"]').trigger('click');
    await flushPromises();
    expect(actions.sendAccessRequest.mock.calls[0][1].recipientid).toBe(9);
    wrapper.unmount();
  });

  it('an empty message cannot be sent', async () => {
    const { wrapper } = await dialog();
    await wrapper.find('[data-testid="access-request-message"]').setValue('   ');
    expect(wrapper.find('[data-testid="access-request-send"]').attributes('disabled')).toBeDefined();
    wrapper.unmount();
  });

  it('refused by the server: the reason as an alert, the text kept, nothing reported as sent', async () => {
    const { wrapper } = await dialog({ send: () => Promise.reject(new Error('This person cannot be asked.')) });
    await wrapper.find('[data-testid="access-request-message"]').setValue('Kept text');
    await wrapper.find('[data-testid="access-request-send"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[role="alert"]').text()).toBe('This person cannot be asked.');
    expect(wrapper.find('[data-testid="access-request-message"]').element.value).toBe('Kept text');
    expect(wrapper.emitted('sent')).toBeUndefined();
    wrapper.unmount();
  });
});

describe('CourseAccessRequestDialog - nobody to ask', () => {
  it('no Send: the site\'s contact, the text to copy, Close', async () => {
    const none = { ...ONE, recipients: [], fallback: '<p>Write to support@example.invalid</p>' };
    Object.assign(navigator, { clipboard: { writeText: jest.fn(() => Promise.resolve()) } });
    const { wrapper, actions } = await dialog({ fetch: () => Promise.resolve(none) });
    expect(wrapper.find('[data-testid="access-request-send"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="access-request-fallback"]').text()).toBe('Write to support@example.invalid');
    expect(wrapper.find('.adele-access-request-note').text()).toBe('teacher_request_nobody');
    expect(wrapper.find('[data-testid="access-request-cancel"]').text()).toBe('teacher_request_close');
    await wrapper.find('[data-testid="access-request-copy"]').trigger('click');
    await flushPromises();
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(ONE.message);
    expect(wrapper.find('[role="status"]').text()).toBe('teacher_request_copied');
    expect(actions.sendAccessRequest).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('the server refuses the dialog itself: the reason, only Close', async () => {
    const { wrapper } = await dialog({ fetch: () => Promise.reject(new Error('You can already open this course.')) });
    expect(wrapper.find('[role="alert"]').text()).toBe('You can already open this course.');
    expect(wrapper.find('[data-testid="access-request-send"]').exists()).toBe(false);
    expect(wrapper.find('[data-testid="access-request-copy"]').exists()).toBe(false);
    wrapper.unmount();
  });
});
