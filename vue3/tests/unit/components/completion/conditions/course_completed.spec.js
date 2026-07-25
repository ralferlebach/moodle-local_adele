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
 * course completed.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import course_completed from '../../../../../components/completion/conditions/course_completed.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex'
import { nextTick } from 'vue'


describe('course_completed.vue', () => {
  let store

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          course_completion_minimum_amount: 'Select the minimum amount of finished courses',
          course_completion_choose_number: 'Choose a number of courses',
        },
        node: {
          course_node_id: ['node_1'], // Default mocked node
        }
      }
    })
  })

  it('renders completion description when only one course_node_id is present', async() => {
    const wrapper = mount(course_completed, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete this course' }
      }
    })

    // Check that the description is displayed
    await nextTick()
    const descriptionText = wrapper.find('.form-check').text()
    expect(descriptionText).toBe('Complete this course')
  })

  it('renders the dropdown when course_node_id length is greater than 1', async () => {
    store.state.node.course_node_id = ['node_1', 'node_2', 'node_3']

    const wrapper = mount(course_completed, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete this course' }
      }
    })

    // Wait for DOM updates
    await nextTick()

    // Check that the label for the select dropdown is present
    const label = wrapper.find('label.form-label')
    expect(label.text()).toBe('Select the minimum amount of finished courses')

    // Check that the dropdown exists
    const selectDropdown = wrapper.find('select.form-select')
    expect(selectDropdown.exists()).toBe(true)

    // Check that the options contain the correct values from nodeCourses
    const options = wrapper.findAll('option')
    expect(options).toHaveLength(4) // One disabled option + 3 course options
    expect(options[0].text()).toBe('Choose a number of courses')
    expect(options[1].text()).toBe('1')
    expect(options[2].text()).toBe('2')
    expect(options[3].text()).toBe('3')

    // Check that course_node_id.length is rendered after the dropdown
    const nodeLengthText = wrapper.text().includes('/ 3')
    expect(nodeLengthText).toBe(true)
  })

  it('emits update:modelValue when select dropdown is changed', async () => {
    store.state.node.course_node_id = ['node_1', 'node_2']

    const wrapper = mount(course_completed, {
      global: {
        plugins: [store],
      },
      props: {
        modelValue: {},
        completion: { description: 'Complete this course' }
      }
    })

    // Wait for DOM updates
    await nextTick()

    // Simulate changing the select dropdown
    const selectDropdown = wrapper.find('select.form-select')
    await selectDropdown.setValue('2')

    // Check that the event has been emitted with the correct value
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([{ min_courses: 2 }])
  })
});