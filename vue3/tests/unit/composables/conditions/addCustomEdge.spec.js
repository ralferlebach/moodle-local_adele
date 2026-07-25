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
 * add Custom Edge.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addAndConditions from '../../../../composables/conditions/addAndConditions';
import addCustomEdge from '../../../../composables/addCustomEdge';

jest.mock('../../../../composables/addCustomEdge');

describe('addAndConditions', () => {
  let intersectingnode, edges, id;

  beforeEach(() => {
    // Mock the intersectingnode structure
    intersectingnode = {
      closestnode: {
        id: 'node2',
        parentCourse: ['parent1'],
        childCourse: ['child1'],
        restriction: { someRestriction: true },
        completion: { someCompletion: true },
      },
    };

    // Mock the edges array
    edges = {
      value: [
        { id: 'node1-node2', source: 'node1', target: 'node2' },
        { id: 'node1-node3', source: 'node1', target: 'node3' },
        { id: 'node2node3', source: 'node4', target: 'node5' },
      ],
    };

    // Mock the addCustomEdge function
    addCustomEdge.mockImplementation((source, target) => {
      return { id: `${source}-${target}`, source, target };
    });

    id = 'newNode';
    jest.clearAllMocks();
  });


  it('should return the correct parentNodes and childNodes', () => {
    const result = addAndConditions(intersectingnode, edges, id);
    expect(result.parentNodes).toEqual(['parent1']);
    expect(result.childNodes).toEqual(['child1']);
  });

  it('should create new edges based on the closestnode edges', () => {
    const result = addAndConditions(intersectingnode, edges, id);
    // Check if new edges are added correctly
    expect(result.newEdges).toEqual([
      { id: 'newNode-node3', source: 'newNode', target: 'node3' },
    ]);

    // Ensure addCustomEdge is called correctly
    expect(addCustomEdge).toHaveBeenCalledTimes(1);
    expect(addCustomEdge).toHaveBeenCalledWith('newNode', 'node3');
  });

  it('should create new edges based on the closestnode edges', () => {
    const edgeToUpdate = edges.value.find(edge => edge.id === 'node2node3');
    edgeToUpdate.source = 'node2';  // Update source to 'node2'

    const result = addAndConditions(intersectingnode, edges, id);

    // Check if the edge has been modified and processed correctly
    expect(result.newEdges).toEqual([
      { id: 'node3-newNode', source: 'node3', target: 'newNode' },
    ]);

    // Ensure addCustomEdge was called with the updated source
    expect(addCustomEdge).toHaveBeenCalledTimes(1);
    expect(addCustomEdge).toHaveBeenCalledWith('node3', 'newNode');
  });
});