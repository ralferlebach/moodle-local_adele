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
 * remove Dropzone.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import removeDropzones from '../../../composables/removeDropzones';

interface Node {
  id: string,
  type: string;
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

describe('removeDropzone', () => {
  it('should remove nodes and edges with "dropzone" in their type', () => {
    const tree: Tree = {
        nodes: [
            { id: '1', type: 'custom' },
            { id: '2', type: 'dropzone' },
            { id: '3', type: 'custom' },
            { id: '4', type: 'dropzone_special' }
        ],
        edges: [
            { id: 'e1', source: '1', target: '2' },
            { id: 'e2', source: '3', target: '4' },
            { id: 'e3', source: '1', target: 'dropzone_3' }
        ]
    };

    const result = removeDropzones(tree);

    expect(result.nodes).toHaveLength(2);
    expect(result.nodes).toEqual([
        { id: '1', type: 'custom' },
        { id: '3', type: 'custom' }
    ]);
    expect(result.edges).toHaveLength(2);
    expect(result.edges).toEqual([
      { id: 'e1', source: '1', target: '2' },
      { id: 'e2', source: '3', target: '4' }
    ]);
});
});