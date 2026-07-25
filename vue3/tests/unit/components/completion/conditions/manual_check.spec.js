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
 * manual check.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import manual_check from '../../../../../components/completion/conditions/manual_check.vue';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { createStore } from 'vuex';

describe('manual_check.vue', () => {
  let store;

  beforeEach(() => {
    store = createStore({
      state() {
        return {
          strings: {
            completion_manual_check: 'Completion description',
            enabletextarea_manual_check: 'Enable Textarea',
            info_placeholder_manual_check: 'Information text (Not Feedback!)',
            btnupdatetext_manual_check: 'Update Information',
          },
        };
      },
    });
  });

  it('renders checkbox with correct label from Vuex store', () => {
    const wrapper = mount(manual_check, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Manual description' },
      },
    });

    const descriptionText = wrapper.find('.form-check').text();
    expect(descriptionText).toContain('Completion description');
  });

  it('renders checkbox initially unchecked', () => {
    const wrapper = mount(manual_check, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Manual description', informationedited: false },
      },
    });

    const checkbox = wrapper.find('#enableTextarea');
    expect(checkbox.element.checked).toBe(false);
  });

  it('enables textarea when checkbox is clicked', async () => {
    const wrapper = mount(manual_check, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Manual description' },
      },
    });

    const checkbox = wrapper.find('#enableTextarea');
    await checkbox.setChecked();
    await checkbox.trigger('click');
    await nextTick()
    await wrapper.vm.$nextTick();
    const textarea = wrapper.find('textarea');
    expect(textarea.element.disabled).toBe(false);
  });

  it('displays correct placeholder in textarea', () => {
    const wrapper = mount(manual_check, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Manual description' },
      },
    });

    const textarea = wrapper.find('textarea');
    expect(textarea.attributes('placeholder')).toBe('Information text (Not Feedback!)');
  });

  it('changes button color to green when clicked and keeps it until textarea changes', async () => {
    const wrapper = mount(manual_check, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Manual description' },
      },
    });
    const button = wrapper.find('button');
    button.element.disabled = false;
    await button.trigger('click'); // Click the button to update information
    await nextTick()
    await wrapper.vm.$nextTick();
    expect(button.classes()).toContain('btn-success');

    const textarea = wrapper.find('textarea');
    textarea.element.value = 'New text 123';
    textarea.element.dispatchEvent(new Event('input')); // Simulate changing the textarea content
    await nextTick()
    await wrapper.vm.$nextTick();
    expect(button.classes()).toContain('btn-primary');
  });
});