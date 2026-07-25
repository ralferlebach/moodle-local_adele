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
 * outer Graph Display.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import outerGraphDisplay from '../../../../composables/flowHelper/outerGraphDisplay';

describe('outerGraphDisplay', () => {
  let edges, findNode, addEdges;

  beforeEach(() => {
    edges = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: false },
      },
    ];

    findNode = jest.fn((id) => {
      if (id === 'node1') {
        return { id: 'node1', data: { module: 'moduleA' } };
      }
      if (id === 'node2') {
        return { id: 'node2', data: { module: 'moduleB' } };
      }
      return null;
    });

    addEdges = jest.fn();
  });

  it('should hide the existing edges', () => {
    const result = outerGraphDisplay(edges, findNode, addEdges);

    expect(result[0].data.hidden).toBe(true);
  });

  it('should not add a new edge if the edge name includes undefined', () => {
    // Modify findNode to return undefined for one of the nodes
    findNode = jest.fn((id) => {
      if (id === 'node1') {
        return { id: 'node1', data: { module: 'moduleA' } };
      }
      if (id === 'node2') {
        return { id: 'node2', data: { module: undefined } };
      }
      return null;
    });

    outerGraphDisplay(edges, findNode, addEdges);

    expect(addEdges).not.toHaveBeenCalled();
  });
});