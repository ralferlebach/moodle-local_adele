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
 * draw Dropzone.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import drawDropzone from '../../../../composables/nodesHelper/drawDropzone';

interface StoreState {
  strings: {
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface Position {
  x: number;
  y: number;
}

interface ClosestNode {
  id: string;
  position: Position;
  computedPosition: Position;
  dimensions: { height: number };
  parentCourse?: string[];
  childCourse?: string[];
}

describe('drawDropzone', () => {
  let closestNode: ClosestNode, store: Store;

  beforeEach(() => {
    closestNode = {
      id: 'node1',
      position: { x: 100, y: 100 },
      computedPosition: { x: 100, y: 150 },
      dimensions: { height: 300 },
      parentCourse: ['starting_node'],
      childCourse: [],
    };

    store = {
      state: {
        strings: {
          composables_drop_zone_parent: 'Drop Zone Parent',
          composables_drop_zone_child: 'Drop Zone Child',
          composables_drop_zone_add: 'Drop Zone Add',
          composables_drop_zone_or: 'Drop Zone Or',
        },
      },
    };
  });

  it('should handle nodes without parent/childCourse properly', async () => {
    closestNode.parentCourse = [];
    closestNode.childCourse = [];

    const result = await drawDropzone(closestNode, store);

    const parentDropZone = result.nodes.find(node => node.id === 'dropzone_parent');
    const childDropZone = result.nodes.find(node => node.id === 'dropzone_child');

    expect(parentDropZone).toBeDefined();
    expect(childDropZone).toBeDefined();

    if (parentDropZone) {
      expect(parentDropZone.position).toEqual({ x: 100, y: -150 });
    }

    if (childDropZone) {
      expect(childDropZone.position).toEqual({ x: 100, y: 450 });
    }
  });

  it('should offset x-position when parentCourse or childCourse is not empty', async () => {
    closestNode.parentCourse = ['some_course'];
    closestNode.childCourse = ['some_course'];

    const result = await drawDropzone(closestNode, store);

    const parentDropZone = result.nodes.find(node => node.id === 'dropzone_parent');
    const childDropZone = result.nodes.find(node => node.id === 'dropzone_child');

    expect(parentDropZone).toBeDefined();
    expect(childDropZone).toBeDefined();

    if (parentDropZone) {
      // Offset x-position by 500 when courses are defined
      expect(parentDropZone.position.x).toBe(600); // 100 + 500
    }

    if (childDropZone) {
      // Offset x-position by 500 when courses are defined
      expect(childDropZone.position.x).toBe(600);  // 100 + 500
    }
  });



});
