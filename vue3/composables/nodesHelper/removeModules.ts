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
 * remove Modules module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  id: string;
  type: string
}

interface Tree {
  nodes: Node[];
}

const  removeModules = (tree: Tree, removeNodes?: (nodes: Node[]) => void): Tree | number => {
  if (removeNodes == null) {
    tree.nodes = tree.nodes.filter(node => !node.type.includes('module'));
    return tree;
  }
  if (tree) {
    const moduleNodes = tree.nodes.filter(node => node.type.includes('module'));
    if (moduleNodes.length > 0) {
      removeNodes(moduleNodes);
    }
  }
  return 1
}

export default removeModules;