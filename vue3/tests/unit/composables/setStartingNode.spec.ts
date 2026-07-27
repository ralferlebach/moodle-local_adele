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
 * set Starting Node.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import setStartingNode from '../../../composables/setStartingNode';

interface Position {
  x: number;
  y: number;
}

interface Dimensions {
  width: number;
  height: number;
}

interface NodeData {
  opacity: string;
  bgcolor: string;
  height: string;
  width: string;
  infotext?: string; // Optional property for infotext
}

interface Node {
  id: string;
  type: string,
  position: Position;
  label: string;
  data: NodeData;
  draggable: boolean;
  parentCourse: string;
  dimensions?: Dimensions; // Optional since it may not be set initially
}

interface Store {
  state: {
    view: string;
    strings: {
      composables_new_node: string;
    };
  };
}

describe('setStartingNode', () => {
  let removeNodesMock: jest.Mock, updateNodesMock: jest.Mock, nextTickMock: jest.Mock, addNodesMock: jest.Mock, storeMock: Store, nodes: any[];

    beforeEach(() => {
    removeNodesMock = jest.fn(); // Mock for removeNodes
    updateNodesMock = jest.fn();
    nextTickMock = jest.fn((cb: () => void) => cb()); // Mock for nextTick
    addNodesMock = jest.fn(); // Mock for addNodes
    storeMock = {
      state: {
        view: 'student',
        strings: {
          composables_new_node: 'New node info text',
        },
      },
    };

    nodes = [
      {
        id: 'node1',
        type: 'course',
        position: { x: 100, y: 100 },
        dimensions: { width: 200, height: 100 },
        parentCourse: 'starting_node',
        label: 'Node 1',
        data: {
          opacity: '0.5',
          bgcolor: 'red',
          height: '100px',
          width: '200px',
        },
        draggable: true,
      },
      {
        id: 'node2',
        type: 'course',
        position: { x: 400, y: 100 },
        dimensions: { width: 200, height: 100 },
        parentCourse: 'starting_node',
        label: 'Node 2',
        data: {
          opacity: '0.5',
          bgcolor: 'red',
          height: '100px',
          width: '200px',
        },
        draggable: true,
      },
      {
        id: 'node3',
        type: 'course',
        position: { x: -400, y: 100 },
        dimensions: { width: 200, height: 100 },
        parentCourse: 'starting_node',
        label: 'Node 3',
        data: {
          opacity: '0.5',
          bgcolor: 'red',
          height: '100px',
          width: '200px',
        },
        draggable: true,
      },
    ];
  });


  it('should not execute if the store view is teacher', () => {
    storeMock.state.view = 'teacher';
    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock, updateNodesMock);
    expect(removeNodesMock).not.toHaveBeenCalled();
    expect(addNodesMock).not.toHaveBeenCalled();
  });

  it('should calculate the correct position for the starting node', () => {
    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock, updateNodesMock);

    expect(removeNodesMock).toHaveBeenCalledWith(['starting_node']);
    expect(addNodesMock).toHaveBeenCalledWith([expect.objectContaining({
      id: 'starting_node',
      position: { x: 900, y: 0 },
      data: {
          infotext: 'New node info text',
          opacity: '0.6',
          bgcolor: 'grey',
          height: '200px',
          width: '400px',
      },
      draggable: true,
      type: 'dropzone',
      label: 'DZ node',
      parentCourse: '',
  })]);
  });

  it('should adjust the position to avoid intersections', () => {
    nodes.push({
      id: 'starting_node',
      type: 'dropzone',
      position: { x: 750, y: 0 },
      dimensions: { width: 200, height: 100 },
      parentCourse: 'starting_node',
      label: 'Starting Node',
      data: {
        opacity: '0.6',
        bgcolor: 'grey',
        height: '200px',
        width: '400px',
      },
      draggable: true,
    });

    setStartingNode(removeNodesMock, nextTickMock, addNodesMock, nodes, 150, storeMock, updateNodesMock, true);

    expect(removeNodesMock).toHaveBeenCalledWith(['starting_node']);
    expect(addNodesMock).toHaveBeenCalledWith([expect.objectContaining({
        id: 'starting_node',
        position: { x: 1050, y: 0 },
        data: {
            infotext: 'New node info text',
            opacity: '0.6',
            bgcolor: 'grey',
            height: '200px',
            width: '400px',
        },
        draggable: true,
        type: 'dropzone',
        label: 'DZ node',
        parentCourse: '',
    })]);
  });
});