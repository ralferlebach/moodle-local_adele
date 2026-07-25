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
 * inner Graph Display.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import innerGraphDisplay from '../../../../composables/flowHelper/innerGraphDisplay';

describe('innerGraphDisplay', () => {
  let edges, removeEdges;

  beforeEach(() => {
    edges = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: true },
      },
      {
        id: 'edge2',
        source: 'node1',
        target: 'node3',
      },
    ];

    removeEdges = jest.fn();
  });

  it('should hide the existing edges', () => {
    const expected = [
      {
        id: 'edge1',
        source: 'node1',
        target: 'node2',
        data: { hidden: false },
      },
    ];
    const result = innerGraphDisplay(edges, removeEdges);
    expect(result).toEqual(expected);

    // Check if edges without data are removed
    expect(removeEdges).toHaveBeenCalledWith('edge2');
  });
});