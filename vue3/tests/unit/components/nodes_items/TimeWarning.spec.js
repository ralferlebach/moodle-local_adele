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
 * Time Warning.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import TimeWarning from '../../../../components/nodes_items/TimeWarning.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('TimeWarning.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_warning_time_restriction: 'Time restriction warning message',
        },
      },
    });

    wrapper = mount(TimeWarning, {
      global: {
        plugins: [store],
      },
    });
  });

  it('renders the tooltip container and icon', () => {
    const container = wrapper.find('.tooltip-container');
    const icon = wrapper.find('.fa-exclamation-triangle');
    expect(container.exists()).toBe(true);
    expect(icon.exists()).toBe(true);
  });

});