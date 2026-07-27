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
 * cat Quiz.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import { createStore } from 'vuex'
import CatQuiz from '../../../../../components/completion/conditions/catQuiz.vue'
import DropdownInput from '../../../../../components/nodes_items/DropdownInput.vue';
import { nextTick } from 'vue';

describe('Component.vue', () => {
  let store;
  let fetchCatquizTests;

  beforeEach(() => {
    fetchCatquizTests = jest.fn().mockResolvedValue([{ id: 1, name: 'Test One' },
        { id: 2, name: 'Test Two' },]);
    store = createStore({
      state: {
        strings: {
          no_catquiz_class: 'No quizzes available',
          conditions_min_grad: 'Minimum grade to succeed',
          conditions_parent_scale_name: 'Parent Scale Name',
          conditions_scale_value: 'Scale Value',
          conditions_attempts: 'Attempts',
          conditions_set_values: 'Set Values',
          conditions_catquiz_hide_table: 'Hide Table',
          conditions_catquiz_show_table: 'Show Table',
          conditions_no_scales: 'No Scales Available',
          conditions_name: 'Name',
        },
      },
      actions: {
        fetchCatquizTests,
        fetchCatquizParentScales: jest.fn().mockResolvedValue([]),
        fetchCatquizScales: jest.fn().mockResolvedValue([]),
        fetchCatquizParentScale: jest.fn().mockResolvedValue([]),
      },
      dispatch: jest.fn(() => Promise.resolve())
    })

  })

  it('dispatches fetchCatquizTests action on mount', async () => {
        const wrapper = shallowMount(CatQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete the quiz to pass.' },
      },
    })
    // await onNodeClick(event, setCenter, store);
    // Wait for any asynchronous operation to complete
    await nextTick();
    await flushPromises();

    expect(wrapper.text()).toContain('Complete the quiz to pass.')

    // Check if fetchCatquizTests was called
    expect(fetchCatquizTests).toHaveBeenCalled();
    // expect(store.dispatch).toHaveBeenCalledWith('fetchCatquizTests');
    
    // You can also check the state of `tests`
    const testsData = wrapper.vm.tests;
    expect(testsData).toEqual([
      { id: 1, name: 'Test One' },
      { id: 2, name: 'Test Two' },
    ]);
  });

  it('renders component properly', async () => {
    const wrapper = shallowMount(CatQuiz, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete the quiz to pass.' },
      },
    })
    await new Promise(process.nextTick);

    expect(fetchCatquizTests).toHaveBeenCalled();

    const testsData = wrapper.vm.tests;
    expect(testsData).toEqual([
      { id: 1, name: 'Test One' },
      { id: 2, name: 'Test Two' },
    ]);
    expect(wrapper.text()).toContain('Complete the quiz to pass.')

    expect(wrapper.findComponent(DropdownInput).exists()).toBe(true)

  })
})