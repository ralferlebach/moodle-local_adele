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
 * validate Quiz Global module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface NodeData {
  label?: string;
  value?: {
    scales?: {
      parent?: {
        scale?: string;
      };
    };
  };
  error?: boolean;
}

interface Node {
  id: string;
  data: NodeData
}

interface Conditions {
  nodes: Node[]
}

type FindNode = (id: string) => Node  | undefined;

const  validateQuizGlobal = (conditions: Conditions, findNode: FindNode, quizsetting: string): number => {
  let globalwarning = 0
  if (quizsetting == 'all_quiz_global') {
    conditions.nodes.forEach((node) => {
      if (
        node.data.label == 'catquiz' &&
        (
          !node.data.value?.scales?.parent?.scale ||
          node.data.value.scales.parent.scale == ''
        )
      ) {
        const invalidNode = findNode(node.id)
        if (invalidNode) {
          invalidNode.data.error = true
          globalwarning += 1
        }
      } else if (node.data.error) {
        const invalidNode = findNode(node.id);
        if (invalidNode) {
          delete invalidNode.data.error;
        }
      }
    })
  }
  return globalwarning
}
export default validateQuizGlobal;