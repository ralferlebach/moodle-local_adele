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
 * add Auto Completions.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addAutoCompletions from '../../../../composables/conditions/addAutoCompletions';

describe('addAutoCompletions', () => {
  let node, store;

  beforeEach(() => {
    // Mock the node structure
    node = {
      position: { x: 355, y: 120 },
    };

    // Mock the store object
    store = {
      state: {
        strings: {
          course_description_condition_course_completed: 'Course completion condition',
          course_description_before_condition_course_completed: 'Before condition description',
          course_description_inbetween_condition_course_completed: 'Inbetween condition description',
          course_description_after_condition_course_completed: 'After condition description',
          course_name_condition_course_completed: 'Course Name',
          composables_feedback_node: 'Feedback Node',
        },
      },
    };
  });

  it('should add the correct completion structure to the node', () => {
    const result = addAutoCompletions(node, store);

    expect(result.completion).toBeDefined();
    expect(result.completion.edges).toHaveLength(1);
    expect(result.completion.nodes).toHaveLength(2);

    // Check specific node data in the completion.nodes array
    const conditionNode = result.completion.nodes.find(node => node.id === 'condition_1');
    expect(conditionNode.data.description).toBe(store.state.strings.course_description_condition_course_completed);
    expect(conditionNode.data.name).toBe(store.state.strings.course_name_condition_course_completed);
    expect(conditionNode.label).toBe('custom node');

    const feedbackNode = result.completion.nodes.find(node => node.id === 'condition_1_feedback');
    expect(feedbackNode.data.feedback_before).toBe(store.state.strings.course_description_before_condition_course_completed);
    expect(feedbackNode.data.feedback_after).toBe(store.state.strings.course_description_after_condition_course_completed);
    expect(feedbackNode.label).toBe(store.state.strings.composables_feedback_node);
  });
});