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
 * validate Nodes module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Conditions {
  nodes: Node[];
}

interface Node {
  id: string;
  data: NodeData;
}

interface NodeData {
  label: string;
  value?: {
    testid?: string | null;
    quizid?: string | null;
  };
  error?: boolean;
}

const  validateNodes = (conditions: Conditions, findNode: (id: string) => Node) => {
  let invalidNodes = false
  conditions.nodes.forEach((node) => {
    if (
      (
        node.data.label == 'catquiz' ||
        node.data.label == 'modquiz'
      ) &&
      (
        node.data.value == null ||
        (
          node.data.value.testid == null &&
          node.data.value.quizid == null
        )
      )
    ) {
      let invalidNode = findNode(node.id)
      if (invalidNode) {
        invalidNode.data.error = true;
        invalidNodes = true;
      }
    } else if (node.data.error) {
      let invalidNode = findNode(node.id)
      delete(invalidNode.data.error)
    }
  })
  return invalidNodes
}
export default validateNodes;