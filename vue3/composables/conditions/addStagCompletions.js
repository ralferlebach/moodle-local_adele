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
 * add Stag Completions module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// generate a new id
const  addStageCompletions = (node) => {
  if (node.completion && node.completion.nodes) {
    node.completion.nodes.forEach((completion_node) => {
      if (completion_node.type == 'custom' && completion_node.data.label == 'course_completed' &&
        completion_node.data.value == undefined) {
          completion_node.data.value = {
            min_courses: 1,
          }
      }
    })
  }
    return node;
}

export default addStageCompletions;