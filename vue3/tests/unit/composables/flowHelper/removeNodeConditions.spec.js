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
 * remove Node Conditions.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import removeNodeConditions from '../../../../composables/flowHelper/removeNodeConditions';

describe('removeNodeConditions', () => {

  it('should remove the removedid from courses_id and adjust min_courses if necessary', () => {
    const node = {
      restriction: {
        nodes: [
          {
            id: 'node_1',
            data: {
              value: {
                courses_id: [1, 2, 3],
                min_courses: 2
              }
            }
          }
        ]
      }
    };

    let removedid = 2;
    let result = removeNodeConditions(node, removedid);

    expect(result.restriction.nodes[0].data.value.courses_id).toEqual([1, 3]);
    expect(result.restriction.nodes[0].data.value.min_courses).toBe(2);

    removedid = 3;
    result = removeNodeConditions(node, removedid);
    expect(result.restriction.nodes[0].data.value.courses_id).toEqual([1]);
    expect(result.restriction.nodes[0].data.value.min_courses).toBe(1);
  });

  it('should remove node restriction when node_id is equal to removedid and is not an array', () => {
    const node = {
      restriction: {
        nodes: [
          {
            id: 'node_2',
            data: {
              value: {
                node_id: 4
              }
            }
          }
        ]
      }
    };

    const removedid = 4;
    const result = removeNodeConditions(node, removedid);

    expect(result.restriction).toBeUndefined();
  });

  it('should filter out removedid from node_id when it is an array', () => {
    const node = {
      restriction: {
        nodes: [
          {
            id: 'node_3',
            data: {
              value: {
                node_id: [1, 2, 4]
              }
            }
          }
        ]
      }
    };

    const removedid = 2;
    const result = removeNodeConditions(node, removedid);

    expect(result.restriction.nodes[0].data.value.node_id).toEqual([1, 4]);
  });

  it('should set courseid to null when it matches the removedid', () => {
    const node = {
      restriction: {
        nodes: [
          {
            id: 'node_4',
            data: {
              value: {
                courseid: 7
              }
            }
          }
        ]
      }
    };

    const removedid = 7;
    const result = removeNodeConditions(node, removedid);

    expect(result.restriction.nodes[0].data.value.courseid).toBeNull();
  });

  it('should handle nodes without restrictions', () => {
    const node = {
      name: 'Simple node',
      restriction: {
        nodes: [
          {
            id: 'node_feedback',
            data: {
              value: {
                courseid: 7
              }
            }
          }
        ]
      }
    };

    const removedid = 7;
    const result = removeNodeConditions(node, removedid);

    expect(result).toEqual(node);
  });

  it('should handle nodes without restrictions', () => {
    const node = {
      name: 'Simple node'
    };

    const removedid = 5;
    const result = removeNodeConditions(node, removedid);

    expect(result).toEqual(node); // Node should remain unchanged
  });

  it('should handle node id strings', () => {
    const node = {
      name: 'Simple node',
      restriction: {
        nodes: [
          {
            id: 'node_3',
            data: {
              value: {
                node_id: 7
              }
            }
          }
        ]
      }
    };
    const removedid = 6;
    const result = removeNodeConditions(node, removedid);
    expect(result).toEqual(node);
  });
});