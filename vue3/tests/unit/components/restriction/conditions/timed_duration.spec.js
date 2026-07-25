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
 * timed duration.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import timed_duration from '../../../../../components/restriction/conditions/timed_duration.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  course_name_condition_timed_duration_select_start: 'Choose scope',
  course_name_condition_timed_duration_duration_value: 'Duration',
  course_name_condition_timed_duration_select_format: 'Choose format',
  course_select_condition_timed_duration_learning_path: 'Learning path',
  course_select_condition_timed_duration_node: 'Node',
  course_select_condition_timed_duration_days: 'Days',
  course_select_condition_timed_duration_weeks: 'Weeks',
  course_select_condition_timed_duration_months: 'Months',
  nodes_warning_time_heading: 'Time warning',
  nodes_warning_time: 'warning body',
};

const factory = (props = {}) =>
  mount(timed_duration, {
    global: {
      plugins: [createStore({ state: { strings } })],
      stubs: { TimeWarning: true },
    },
    props: {
      restriction: { node_id: 'r1' },
      ...props,
    },
  });

describe('timed_duration.vue (restriction)', () => {
  it('renders the scope and format select options from the store', () => {
    const wrapper = factory();
    const optionTexts = wrapper.findAll('option').map((o) => o.text());
    expect(optionTexts).toContain('Learning path');
    expect(optionTexts).toContain('Node');
    expect(optionTexts).toContain('Days');
    expect(optionTexts).toContain('Weeks');
    expect(optionTexts).toContain('Months');
  });

  it('emits update:modelValue with the selected scope on change', async () => {
    const wrapper = factory();
    const scopeSelect = wrapper.find('#restriction-r1-option');
    await scopeSelect.setValue('1');

    const events = wrapper.emitted('update:modelValue');
    expect(events).toBeTruthy();
    expect(events[events.length - 1][0].selectedOption).toBe('1');
  });

  it('accepts a positive integer duration', async () => {
    const wrapper = factory();
    const durationInput = wrapper.find('#restriction-r1-duration');
    await durationInput.setValue('5');
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.data.selectedDuration).toBe('5');
  });

  it('rejects a non-integer / invalid duration and clears it', async () => {
    const wrapper = factory();
    const durationInput = wrapper.find('#restriction-r1-duration');
    await durationInput.setValue('abc');
    await wrapper.vm.$nextTick();
    // Invalid input with no valid previous value falls back to empty string.
    expect(wrapper.vm.data.selectedDuration).toBe('');
  });

  it('reverts a fractional duration to the previous valid integer', async () => {
    const wrapper = factory();
    const durationInput = wrapper.find('#restriction-r1-duration');
    await durationInput.setValue('4');
    await wrapper.vm.$nextTick();
    await durationInput.setValue('4.5');
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.data.selectedDuration).toBe('4');
  });

  it('initialises from a provided modelValue', async () => {
    const wrapper = factory({
      modelValue: { selectedOption: '0', durationValue: '1', selectedDuration: '3' },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.data.selectedOption).toBe('0');
    expect(wrapper.vm.data.selectedDuration).toBe('3');
  });
});
