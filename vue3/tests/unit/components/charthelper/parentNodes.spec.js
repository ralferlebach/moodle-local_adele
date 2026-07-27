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
 * parent Nodes.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import parentNodes from '../../../../components/charthelper/parentNodes.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';

describe('parentNodes.vue', () => {
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          charthelper_parent_nodes: 'Parent Nodes',
          charthelper_no_parent_nodes: 'No parent nodes available',
        },
      },
    });
  });

  it('renders fallback message when props.parentNodes is empty', () => {
    const wrapper = mount(parentNodes, {
      global: {
        plugins: [store],
      },
      props: { parentNodes: [] },
    });

    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes');
    const fallbackMessage = wrapper.find('li.list-group-item').text();
    expect(fallbackMessage).toBe('No parent nodes available');
  });

  it('renders parent nodes when props.parentNodes is provided', () => {
    const propsParentNodes = [
      { data: { fullname: 'Node 1' } },
      { data: { fullname: 'Node 2' } },
    ];

    const wrapper = mount(parentNodes, {
      global: {
        plugins: [store],
      },
      props: { parentNodes: propsParentNodes },
    });

    expect(wrapper.find('.card-title').text()).toContain('Parent Nodes');
    const listItems = wrapper.findAll('li.list-group-item');
    expect(listItems.length).toBe(2);
    expect(listItems[0].text()).toBe('Node 1');
    expect(listItems[1].text()).toBe('Node 2');
  });


});