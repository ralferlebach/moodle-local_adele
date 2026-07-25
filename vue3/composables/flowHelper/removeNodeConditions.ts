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
 * remove Node Conditions module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  restriction?: Restriction;
}

interface Restriction {
  nodes: RestrictionNode[];
}

interface RestrictionNode {
  id: string;
  data: RestrictionNodeData;
}

interface RestrictionNodeData {
  value: RestrictionNodeDataValue;
}

interface RestrictionNodeDataValue {
  courses_id: number[];
  courseid?: number | null;
  min_courses: number;
  node_id: number |number[];
}

const  removeNodeConditions = (node: Node, removedid: number): Node => {
    if(node.restriction) {
      node.restriction.nodes.forEach((noderestriction) => {
        if (
          !noderestriction.id.includes('_feedback') &&
          noderestriction.data.value
        ) {
            if (
              noderestriction.data.value.courses_id &&
              noderestriction.data.value.courses_id.includes(removedid)
            ) {
              noderestriction.data.value.courses_id = noderestriction.data.value.courses_id.filter(id => id !== removedid);
              if (noderestriction.data.value.min_courses > noderestriction.data.value.courses_id.length) {
                noderestriction.data.value.min_courses -=1
              }
            }
            if (
              noderestriction.data.value.node_id
            ) {
              if (
                Array.isArray(noderestriction.data.value.node_id) &&
                noderestriction.data.value.node_id.includes(removedid)
              ) {
                noderestriction.data.value.node_id = noderestriction.data.value.node_id.filter(id => id !== removedid);
              } else if (noderestriction.data.value.node_id == removedid) {
                delete node.restriction;
              }
            }
            if (
              noderestriction.data.value.courseid &&
              noderestriction.data.value.courseid == removedid
            ) {
              noderestriction.data.value.courseid = null
            }
        }
      })
    }
    return node;
}

export default removeNodeConditions;