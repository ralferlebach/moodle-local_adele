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
 * inner Graph Display module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

type removeEdges = (id: string) =>void;

interface Edge {
  id: string;
  data: EdgeData;
}

interface EdgeData {
  hidden: boolean;
}

const innerGraphDisplay = (edges: Edge[], removeEdges: removeEdges): Edge[] => {
  let newedges: Edge[] = []
  edges.forEach(
    (edge) => {
      if (edge.data == undefined) {
        removeEdges(edge.id)
      } else {
        edge.data.hidden = false
        newedges.push(edge)
      }
    }
  )
  return newedges
}

export default innerGraphDisplay;