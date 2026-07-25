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
 * recalculate Parent Child module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  id: string;
  type: string;
  data?: {
    course_node_id: string | string[];
  };
  parentNodes?: string[];
  childNodes?: string[];
  [key: string]: any;
}

interface Edge {
  id: string,
  sourceHandle: string;
  source: string;
  target: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

const  recalculateParentChild = (tree: Tree, parentNode: string, childNode: string, startNode: string): Tree => {
    tree.nodes.forEach((node) => {
        if (
          node.type == 'custom' ||
          node.type == 'orcourses'
        ) {
            node[parentNode] = []
            node[childNode] = []
            tree.edges.forEach((edge) => {
                if (!edge.sourceHandle.includes('or')) {
                    if (edge.source == node.id &&
                        !node[childNode].includes(edge.target)) {
                        node[childNode].push(edge.target);
                    }
                    if (edge.target == node.id &&
                        !node[parentNode].includes(edge.source)) {
                        node[parentNode].push(edge.source);
                    }
                }
            })
            if(node[parentNode].length == 0){
                node[parentNode].push(startNode);
            }
            if (
              node.data?.course_node_id
            ) {
              if (node.data.course_node_id.length > 1) {
                node.type = 'orcourses'
              } else {
                node.type = 'custom'
              }
            }
        }
    })
    return tree;
}
export default recalculateParentChild;