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
 * Dropdown Input.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, shallowMount } from '@vue/test-utils';
import DropdownInput from '../../../../components/nodes_items/DropdownInput.vue';
import { createStore } from 'vuex/dist/vuex.cjs.js';

describe('DropdownComponent', () => {
  let store

  const testItems = [
    { id: '1', name: 'Test One', coursename: 'Course A' },
    { id: '2', name: 'Test Two', coursename: 'Course B' },
  ];

  let wrapper;
  
  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_items_none: 'None',
          nodes_items_testname: 'Test Name:',
          nodes_items_coursename: 'Course Name:'
        }
      }
    })


    wrapper = mount(DropdownInput, {
      props: {
        selectedTestId: null,
        tests: testItems,
      },
      global: {
        plugins: [store],
      }
    });
  });

  it('renders input and dropdown correctly', () => {
    expect(wrapper.find('input.form-control').exists()).toBe(true);
    expect(wrapper.find('.dropdown').exists()).toBe(false); // Initially hidden
  });

  it('shows dropdown when input is focused', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');
    expect(wrapper.find('.dropdown').exists()).toBe(true);
  });

  it('filters options based on search input', async () => {
    const input = wrapper.find('input');

    // Focus input to show dropdown
    await input.trigger('focus');
    
    // Simulate typing text
    await input.setValue('one');
    const filteredItems = wrapper.findAll('li');
    expect(filteredItems).toHaveLength(2);
    expect(filteredItems.at(1).text()).toContain('Test One');
  });

  it('selects an option and emits update:value event', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');

    const firstOption = wrapper.findAll('li').at(1); // Skip the 'None' option
    await firstOption.trigger('mousedown');
    
    expect(wrapper.emitted('update:value')).toBeTruthy();
    expect(wrapper.emitted('update:value')[0][0]).toEqual({ id: '1', name: 'Test One', coursename: 'Course A' });
  
    // Ensure dropdown closes after selection
    expect(wrapper.find('.dropdown').exists()).toBe(false);
  });

  it('closes dropdown when input loses focus', async () => {
    const input = wrapper.find('input');
    await input.trigger('focus');
    expect(wrapper.find('.dropdown').exists()).toBe(true);

    await input.trigger('blur');
    setTimeout(() => {
      expect(wrapper.find('.dropdown').exists()).toBe(false);
    }, 300); // Wait more than 200ms to account for debounce
  });
});