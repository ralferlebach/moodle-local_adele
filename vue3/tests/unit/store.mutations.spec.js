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
 * Node-edit store mutations must persist what the modals collected (#484).
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });

import { createAppStore } from '../../store.js';

// The editor's persisted truth is store.state.learningpath.json.tree.nodes:
// ControlsPath saves exactly that object. The modals (ModalNode/ModalCourse)
// commit these mutations, so whatever the mutations fail to write into the
// TREE is silently lost on save - the root cause of GitHub #484 (node
// descriptions never reached the DB).
const buildStore = () => {
  const store = createAppStore();
  const treenode = {
    id: 'dndnode_1',
    type: 'custom',
    data: {
      fullname: 'Stack name',
      description: 'Stack description',
      course_node_id: [11, 12],
    },
  };
  store.state.learningpath = { json: { tree: { nodes: [treenode], edges: [] } } };
  // state.node is the DISPLAY copy the modal was opened for (vue-flow clones
  // node data), deliberately a different object than the tree node's data.
  store.state.node = { node_id: 'dndnode_1', fullname: 'Stack name' };
  return store;
};

describe('updatedNode mutation (#484 single-node description)', () => {
  it('persists description and estimate_duration into the TREE node data', () => {
    const store = buildStore();
    store.commit('updatedNode', {
      node_id: 'dndnode_1',
      fullname: 'New name',
      description: 'My node description',
      estimate_duration: '3 weeks',
      selected_image: '/img/a.png',
      selected_course_image: '',
    });
    const data = store.state.learningpath.json.tree.nodes[0].data;
    expect(data.description).toBe('My node description');
    expect(data.estimate_duration).toBe('3 weeks');
    expect(data.fullname).toBe('New name');
    expect(data.selected_image).toBe('/img/a.png');
  });

  it('does not pollute the tree node with top-level display fields', () => {
    const store = buildStore();
    store.commit('updatedNode', {
      node_id: 'dndnode_1',
      fullname: 'New name',
      description: 'D',
      estimate_duration: '',
      selected_image: '',
      selected_course_image: '',
    });
    const node = store.state.learningpath.json.tree.nodes[0];
    // Node display fields live under data.*; top-level copies shadow nothing
    // the renderer reads and end up persisted as junk.
    expect(node.fullname).toBeUndefined();
    expect(node.selected_image).toBeUndefined();
  });
});

describe('updatedCourseNode mutation (#484 per-course description in a stack)', () => {
  it('persists the per-course text into the TREE node course_node_id_description', () => {
    const store = buildStore();
    store.commit('updatedCourseNode', {
      courseid: 11,
      fullname: 'Course A given name',
      description: 'Course A own description',
    });
    const data = store.state.learningpath.json.tree.nodes[0].data;
    expect(data.course_node_id_description).toBeDefined();
    expect(data.course_node_id_description[11]).toEqual({
      fullname: 'Course A given name',
      description: 'Course A own description',
    });
  });

  it('leaves the stack node\'s own name and description untouched', () => {
    const store = buildStore();
    store.commit('updatedCourseNode', {
      courseid: 11,
      fullname: 'Course A given name',
      description: 'Course A own description',
    });
    const node = store.state.learningpath.json.tree.nodes[0];
    expect(node.data.fullname).toBe('Stack name');
    expect(node.data.description).toBe('Stack description');
    expect(node.fullname).toBeUndefined();
  });
});
