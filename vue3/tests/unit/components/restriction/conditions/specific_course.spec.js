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
 * specific course.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import specific_course from '../../../../../components/restriction/conditions/specific_course.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex'
import { nextTick } from 'vue'

// Regression test for GitHub issue #451: a "specific node completed" criterion
// must not let a node require its own completion. The current node has to be
// excluded from the dropdown (it was selectable because the component compared
// store.state.node.id - undefined - instead of store.state.node.node_id).
describe('specific_course.vue', () => {
  let store

  const nodes = [
    { id: 'dndnode_1', data: { fullname: 'Node A' } },
    { id: 'dndnode_2', data: { fullname: 'Node B (current)' } },
    { id: 'dndnode_3', data: { fullname: 'Node C' } },
  ]

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          restriction_select_course: 'Select a node',
          restriction_no_node_warning: 'Please select a node',
        },
        node: { node_id: 'dndnode_2' }, // the node currently being edited
        learningpath: { json: { tree: { nodes } } },
      }
    })
  })

  const mountComponent = () => mount(specific_course, {
    global: { plugins: [store] },
    props: { modelValue: null, restriction: { description: 'Pick a node' } },
  })

  it('excludes the current node from the dropdown (no self-dependency, #451)', async () => {
    const wrapper = mountComponent()
    await nextTick()

    const options = wrapper.findAll('option').map(o => o.text())
    expect(options).not.toContain('Node B (current)')
  })

  it('still offers the other nodes as targets', async () => {
    const wrapper = mountComponent()
    await nextTick()

    const options = wrapper.findAll('option').map(o => o.text())
    expect(options).toContain('Node A')
    expect(options).toContain('Node C')
  })
})
