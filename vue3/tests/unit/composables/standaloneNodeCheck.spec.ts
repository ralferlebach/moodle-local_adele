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
 * standalone Node Check.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import standaloneNodeCheck from '../../../composables/standaloneNodeCheck';

interface Node {
  id: string,
}

interface Edge {
  id: string,
  target: string;
  source: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

describe('standaloneNodeCheck', () => {
  it('returns false when there is only one node', () => {
    const tree: Tree = {
      nodes: [{ id: 'node1' }],
      edges: [],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns false when all nodes are connected', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
        { id: 'edge1', source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });

  it('returns true when there is a standalone node', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(true);
  });

  it('returns true when there is a standalone node', () => {
    const tree: Tree = {
      nodes: [
        { id: 'node1' },
        { id: 'node2' },
        { id: 'node3' },
      ],
      edges: [
        { id: 'edge1', source: 'node1', target: 'node2' },
        { id: 'edge2', source: 'node1', target: 'node3' },
        { id: 'edge3', source: 'node2', target: 'node3' },
      ],
    };

    const result = standaloneNodeCheck(tree);
    expect(result).toBe(false);
  });
});