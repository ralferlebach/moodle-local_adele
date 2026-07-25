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
 * remove Modules.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import removeModules from '../../../../composables/nodesHelper/removeModules';

interface Node {
  id: string;
  type: string
}

interface Tree {
  nodes: Node[];
}
describe('removeModules', () => {
  let tree: Tree, removeNodes: jest.Mock<any, any>;

  beforeEach(() => {
    tree = {
      nodes: [
        { id: 'node1', type: 'custom' },
        { id: 'node2', type: 'moduleA' },
        { id: 'node3', type: 'custom' },
        { id: 'node4', type: 'moduleB' },
      ],
    };

    removeNodes = jest.fn();
  });

  it('should filter out nodes without "module" in their type when removeNodes is null', () => {
    const expectedTree: Tree = {
      nodes: [
        { id: 'node1', type: 'custom' },
        { id: 'node3', type: 'custom' },
      ],
    };

    const result = removeModules(tree);
    expect(result).toEqual(expectedTree);
  });

  it('should call removeNodes with nodes that have "module" in their type', () => {
    removeModules(tree, removeNodes);

    // Ensure removeNodes was called with the correct nodes
    expect(removeNodes).toHaveBeenCalledWith([{ id: 'node2', type: 'moduleA' },{ id: 'node4', type: 'moduleB' }]);
  });

  it('should return 1 as nothing happens', () => {
    const result =  removeModules(null as unknown as Tree, removeNodes);
    expect(result).toEqual(1);
  });
});