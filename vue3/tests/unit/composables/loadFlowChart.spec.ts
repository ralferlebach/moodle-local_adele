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
 * load Flow Chart.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import loadFlowChart from '../../../composables/loadFlowChart';

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

describe('loadFlowChart', () => {
  let flow : FlowChart;

  beforeEach(() => {
    // Define the flow object that will be used in each test
    flow = {
      nodes: [
        { id: '1', draggable: true, deletable: true },
        { id: '2', draggable: true, deletable: true },
      ],
      edges: [
        { id: 'e1-2', deletable: true },
      ],
    };
  });

  it('should load the view as teacher', () => {
    const view: string = 'teacher';
    const result: FlowChart = loadFlowChart(flow, view);
    result.nodes.forEach(node => {
      expect(node.draggable).toBe(false);
      expect(node.deletable).toBe(false);
    });

    // Check that all edges are not deletable
    result.edges.forEach(edge => {
      expect(edge.deletable).toBe(false);
    });
  });
  it('should load the view not as teacher', () => {
    const view: string = 'student';
    const result: FlowChart = loadFlowChart(flow, view);

    // Check that nodes and edges remain unchanged
    result.nodes.forEach((node, index) => {
      expect(node.draggable).toBe(flow.nodes[index].draggable);
      expect(node.deletable).toBe(flow.nodes[index].deletable);
    });

    result.edges.forEach((edge, index) => {
      expect(edge.deletable).toBe(flow.edges[index].deletable);
    });
  });
});