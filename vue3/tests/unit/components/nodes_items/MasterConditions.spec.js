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
 * Master Conditions.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import MasterConditions from '../../../../components/nodes_items/MasterConditions.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';
import { nextTick } from 'vue'

// Mock the store
jest.mock('vuex', () => ({
  useStore: jest.fn(),
}));

describe('MasterConditions.vue', () => {
  let storeMock;

  beforeEach(() => {
    storeMock = {
      state: {
        strings: {
          course_master_conditions: 'Master Conditions',
          course_master_condition_restriction: 'Master Restriction Condition',
          course_master_condition_completion: 'Master Completion Condition',
        },
      },
    };
    useStore.mockReturnValue(storeMock);
  });


  it('dropdown should start collapsed and toggle on click', async () => {
    const wrapper = mount(MasterConditions, {
      props: {
        data: {
          node_id: 'test_node',
          completion: {
            master: {
              completion: false,
              restriction: false,
            },
          },
        },
      },
    });

    expect(wrapper.find('.form-check').exists()).toBe(false);
    await wrapper.find('button').trigger('click');
    expect(wrapper.find('.form-check').exists()).toBe(true);
  });

  it('renders data from Vuex store correctly', async() => {
    const wrapper = mount(MasterConditions, {
      props: {
        data: {
          node_id: 'test_node',
          completion: {
            master: {
              completion: false,
              restriction: false,
            },
          },
        },
      },
    });

    // Check if the button label is rendered with correct string from the store
    expect(wrapper.find('button').text()).toBe(storeMock.state.strings.course_master_conditions);

    // Simulate dropdown visibility to check label content
    wrapper.find('button').trigger('click');

    await nextTick()
    // Check if the labels for the checkboxes are rendered with correct store strings
    const restrictionLabel = wrapper.find('label[for="test_node_master_restriction"]');
    expect(restrictionLabel.text()).toBe(storeMock.state.strings.course_master_condition_restriction);

    const completionLabel = wrapper.find('label[for="test_node_master_completion"]');
    expect(completionLabel.text()).toBe(storeMock.state.strings.course_master_condition_completion);
  });

  it('binds data to checkboxes and updates on change', async () => {
    const wrapper = mount(MasterConditions, {
      props: {
        data: {
          node_id: 'test_node',
          completion: {
            master: {
              completion: false,
              restriction: false,
            },
          },
        },
      },
    });

    // Show the dropdown
    await wrapper.find('button').trigger('click');

    // Check initial checkbox states
    const restrictionCheckbox = wrapper.find('#test_node_master_restriction');
    const completionCheckbox = wrapper.find('#test_node_master_completion');

    expect(restrictionCheckbox.element.checked).toBe(false);
    expect(completionCheckbox.element.checked).toBe(false);
  });

});