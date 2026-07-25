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
 * outer Graph Display module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// stepwise set the zomm level
import { MarkerType } from '@vue-flow/core'

interface Edge {
  id?: string;
  source: string;
  target: string;
  sourceHandle?: string;
  targetHandle?: string;
  style?: Record<string, any>;
  markerEnd?: MarkerType;
  data: EdgeData;
}

interface EdgeData {
  hidden: boolean;
}

interface Node {
  id: string;
  data: NodeData;
}

interface NodeData {
  module: string;
  hidden: boolean;
}

type FindNode = (id: string) => Node  | undefined;
type AddEdges = (edges: Edge[]) => void;

const outerGraphDisplay = (edges: Edge[], findNode: FindNode, addEdges: AddEdges) => {
  let newmoduleedgesnames: string[] = [];
  edges.forEach(
    (edge) => {
      edge.data.hidden = true
      const source = findNode(edge.source)
      const target = findNode(edge.target)
      if (!source || !target) {
        return edges;
      }

      const edgename = source.data.module + '_module' + target.data.module + '_module'
      if (
        source.data.module !== target.data.module &&
        !newmoduleedgesnames.includes(edgename) &&
        !edgename.includes('undefined')
      ) {
        newmoduleedgesnames.push(edgename)
        const newEdge: Edge = {
          id: edgename,
          source: source.data.module + '_module',
          target: target.data.module + '_module',
          sourceHandle: 'source',
          targetHandle: 'target',
          style: {
            'stroke-width': 5,
          },
          markerEnd: MarkerType.ArrowClosed,
          data: { hidden: false },
        };
        // Add the new edge
        addEdges([newEdge]);
        edges.push(newEdge)
      }
    }
  )
  return edges
}

export default outerGraphDisplay;