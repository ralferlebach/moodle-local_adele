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
 * Modal Node.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalNode from '../../../../components/modals/ModalNode.vue';
import { shallowMount } from '@vue/test-utils';
import { createStore } from 'vuex'
import { nextTick } from 'vue'

// Regression test for GitHub issue #459: the node editor must show the learning
// path's default image (store.state.learningpath.image) as the preview when the
// node has no image of its own - display only, without overwriting the node's
// (empty) selection.
describe('ModalNode.vue image preview (#459)', () => {
  const baseState = (nodeImage, lpImage) => ({
    strings: {},
    version: '2024010100',
    node: {
      selected_image: nodeImage,
      selected_course_image: '',
      node_id: 'dndnode_1',
      fullname: 'Node A',
      imagepaths: {},
    },
    lpimages: { helpingslider: [], node_background_image: [] },
    learningpath: { image: lpImage },
  })

  const mountWith = (state) => {
    const store = createStore({ state, mutations: { updatedNode() {} } })
    const wrapper = shallowMount(ModalNode, {
      global: { plugins: [store] },
      props: { learningpath: { json: { tree: { nodes: [] } } } },
    })
    return { store, wrapper }
  }

  it('falls back to the LP default image when the node has no own image', async () => {
    const { wrapper } = mountWith(baseState('', 'https://x/lp-default.jpg'))
    await nextTick()

    const img = wrapper.find('img.image-preview')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('https://x/lp-default.jpg')
  })

  it('shows the node own image when one is selected', async () => {
    const { store, wrapper } = mountWith(baseState('', 'https://x/lp-default.jpg'))
    // Opening a node sets store.state.node -> the watch sets the selected image.
    store.state.node = {
      selected_image: 'https://x/node.jpg',
      selected_course_image: '',
      node_id: 'dndnode_1',
      fullname: 'Node A',
      imagepaths: {},
    }
    await nextTick()

    const img = wrapper.find('img.image-preview')
    expect(img.attributes('src')).toBe('https://x/node.jpg')
  })
})
