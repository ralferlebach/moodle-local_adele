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
 * standalone Node Check module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  id: string,
}

interface Edge {
  id: string,
  target: string;
  source: string;
}

interface Tree {
  nodes: Node[];
  edges: Edge[];
}

const  standaloneNodeCheck = (tree: Tree): boolean => {
    if (tree.nodes.length === 1) {
        return false;
    }
    let node_connected = new Set<string>();

    tree.edges.forEach((edge) => {
      node_connected.add(edge.source);
      node_connected.add(edge.target);
    });
    for (const node of tree.nodes) {
      if (!node_connected.has(node.id)) {
        return true;
      }
    }
    return false;
}
export default standaloneNodeCheck;