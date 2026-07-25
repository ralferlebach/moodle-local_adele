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

import addCustomEdge from '../../../composables/addCustomEdge';
import { MarkerType } from '@vue-flow/core';

interface CustomEdge {
  id: string;
  source: string;
  target: string;
  sourceHandle: string;
  targetHandle: string;
  style: {
    'stroke-width': number;
    'position': string;
    'z-index': number;
  };
  markerEnd: MarkerType;
}

describe('addCustomEdge', () => {
  it('should generate a new edge with correct properties', () => {
    const source: string = 'node1';
    const target: string = 'node2';

    const expectedEdge: CustomEdge = {
      id: 'node1node2',
      source: 'node2',
      target: 'node1',
      sourceHandle: 'source',
      targetHandle: 'target',
      style: {
        'stroke-width': 5,
        'position': 'relative',
        'z-index': -10
      },
      markerEnd: MarkerType.ArrowClosed,
    };

    const result = addCustomEdge(source, target);

    expect(result).toEqual(expectedEdge);
  });
});