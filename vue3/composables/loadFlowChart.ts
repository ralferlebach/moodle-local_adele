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
 * load Flow Chart module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Build flow-chart with edges and nodes
interface Node {
  id: string,
  draggable: boolean;
  deletable: boolean;
}

interface Edge {
  id: string,
  deletable: boolean;
}

interface FlowChart {
  nodes: Node[];
  edges: Edge[];
}

const  loadFlowChart = (flow: FlowChart, view: string): FlowChart => {
    if (view == 'teacher') {
      flow.nodes.forEach((nodes) => {
        nodes.draggable = false
        nodes.deletable = false
      })
      flow.edges.forEach((edge) => {
        edge.deletable = false
      })
    }
    return flow
}

export default loadFlowChart;