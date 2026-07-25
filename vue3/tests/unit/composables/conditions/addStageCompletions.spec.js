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
 * add Stage Completions.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addStagCompletions from '../../../../composables/conditions/addStagCompletions';

describe('addStagCompletions', () => {
  let node;

  it('should return the node as it is', () => {
    node = {
      completion: null,
    };
    const result = addStagCompletions(node);
    expect(result).toEqual(node);
  });

  it('should return the node with min_courses', () => {
    node = {
      completion: {
        nodes: [
          {
            type: 'custom',
            data: { label: 'course_completed', value: undefined },
          },
          {
            type: 'custom',
            data: { label: 'some_other_label', value: undefined },
          },
          {
            type: 'other_type',
            data: { label: 'course_completed', value: undefined },
          },
        ],
      },
    };
    const result = addStagCompletions(node);

    // Check if the completion node with 'course_completed' has its value updated
    const updatedNode = result.completion.nodes.find(
      (completion_node) =>
        completion_node.type === 'custom' &&
        completion_node.data.label === 'course_completed'
    );

    expect(updatedNode.data.value).toEqual({
      min_courses: 1,
    });
  });
});