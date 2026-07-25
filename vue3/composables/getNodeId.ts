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
 * get Node Id module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  id: string;
}

// Generate a new ID based on the prefix and an array of nodes
const getNodeId = (prefix: string, nodes: Node[]): string => {
    let highestId = 1;

    nodes.forEach((node: Node) => {
        if (node.id.startsWith(prefix)) {
            const currentId = Number(node.id.slice(prefix.length));
            if (highestId <= currentId) {
                highestId = currentId + 1;
            }
        }
    });
    return `${prefix}${highestId}`;
};

export default getNodeId;