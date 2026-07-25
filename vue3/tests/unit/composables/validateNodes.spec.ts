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
 * validate Nodes.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import validateNodes from '../../../composables/validateNodes';

interface Conditions {
  nodes: Node[];
}

interface Node {
  id: string;
  data: NodeData;
}

interface NodeData {
  label: string;
  value?: {
    testid?: string | null;
    quizid?: string | null;
  };
  error?: boolean;
}

describe('validateNodes', () => {
  let conditions: Conditions;
  let findNodeMock: (id: string) => Node;

  beforeEach(() => {
    // Initial conditions with some valid and invalid nodes
    conditions = {
      nodes: [
        {
          id: 'node1',
          data: {
            label: 'catquiz',
            value: {
              testid: null,
              quizid: null,
            },
            error: false,
          },
        },
        {
          id: 'node2',
          data: {
            label: 'modquiz',
            value: {
              testid: 'test1',
              quizid: null,
            },
            error: false,
          },
        },
        {
          id: 'node3',
          data: {
            label: 'otherquiz',
            value: {
              testid: null,
              quizid: null,
            },
            error: false,
          },
        },
      ],
    };

    // Mock function to simulate findNode
    findNodeMock = jest.fn((id: string): Node => {
      const node = conditions.nodes.find(node => node.id === id);
      if (!node) {
        throw new Error(`Node with id ${id} not found`);
      }
      return node;
    });
  });

  it('returns false and does not set errors for valid nodes', () => {
    // Set all nodes to valid
    conditions.nodes[0].data.value = { testid: 'test123', quizid: 'quiz123' };

    const result = validateNodes(conditions, findNodeMock);

    expect(result).toBe(false);
    expect(findNodeMock).not.toHaveBeenCalled();
  });

  it('sets error on invalid nodes and returns true', () => {
    const result = validateNodes(conditions, findNodeMock);

    // The first node (node1) should have an error because both testid and quizid are null
    expect(result).toBe(true);
    expect(findNodeMock).toHaveBeenCalledWith('node1');
    expect(conditions.nodes[0].data.error).toBe(true);
  });

  it('removes error from a previously invalid node when it becomes valid', () => {
    // Set node1 as invalid with an error
    conditions.nodes[0].data.error = true;
    conditions.nodes[0].data.value = { testid: 'test123', quizid: null };

    const result = validateNodes(conditions, findNodeMock);

    // Since node1 is now valid, the error should be removed
    expect(result).toBe(false);
    expect(conditions.nodes[0].data.error).toBe(undefined);
  });


});