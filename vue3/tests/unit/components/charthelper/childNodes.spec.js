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
 * child Nodes.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import childNodes from '../../../../components/charthelper/childNodes.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

describe('childNodes.vue', () => {
  let store;

  beforeEach(() => {
    // Mock Vuex store
    store = createStore({
      state: {
        strings: {
          charthelper_child_nodes: 'Child Nodes',
          charthelper_no_child_nodes: 'No child nodes available',
        },
      },
    });
  });

  it('renders fallback message when props.childNodes is empty', () => {
    const wrapper = mount(childNodes, {
      global: {
        plugins: [store], // Inject Vuex store
      },
      props: { childNodes: [] },
    });

    expect(wrapper.find('.card-title').text()).toContain('Child Nodes');
    const fallbackMessage = wrapper.find('li.list-group-item').text();
    expect(fallbackMessage).toBe('No child nodes available');
  });

  it('renders child nodes when props.childNodes is provided', () => {
    const propsChildNodes = [
      { data: { fullname: 'Node 1' } },
      { data: { fullname: 'Node 2' } },
    ];

    const wrapper = mount(childNodes, {
      global: {
        plugins: [store],
      },
      props: { childNodes: propsChildNodes },
    });

    expect(wrapper.find('.card-title').text()).toContain('Child Nodes');
    const listItems = wrapper.findAll('li.list-group-item');
    expect(listItems.length).toBe(2);
    expect(listItems[0].text()).toBe('Node 1');
    expect(listItems[1].text()).toBe('Node 2');
  });


});