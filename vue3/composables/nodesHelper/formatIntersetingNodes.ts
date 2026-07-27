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
 * format Interseting Nodes module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface NodeData {
  opacity?: string;
  bgcolor?: string;
  infotext?: string;
}

interface Node {
  id: string;
  data: NodeData
}

interface StoreState {
  strings: {
    completion_drop_here: string;
    composables_new_node: string;
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface IntersectingNode {
  closestnode: string;
  dropzone: Node;
}

const  formatIntersetingNodes = (
    nodesIntersecting: boolean, node: Node, intersectingNode: IntersectingNode | null,
    closestNode: string, insideStartingNode: boolean, store: Store
  ) => {
    if(nodesIntersecting){
        intersectingNode = { closestnode: closestNode, dropzone: node};
        node.data = { ...node.data, ...{
            opacity: '0.75',
            bgcolor: 'chartreuse',
            infotext: store.state.strings.completion_drop_here,
          }
        }
    }else{
        node.data = { ...node.data, ...{
            opacity: '0.6',
            bgcolor: 'grey',
            infotext: store.state.strings.composables_new_node,
          }
        }
        if(node.id == 'dropzone_parent'){
            node.data.infotext = store.state.strings.composables_drop_zone_parent
        }else if(node.id == 'dropzone_child'){
            node.data.infotext = store.state.strings.composables_drop_zone_child
        }else if(node.id == 'dropzone_and'){
            node.data.infotext = store.state.strings.composables_drop_zone_add
        }else if(node.id == 'dropzone_or'){
            node.data.infotext = store.state.strings.composables_drop_zone_or
        }else {
            insideStartingNode = true;
        }
    }
    return {
        node: node,
        insideStartingNode: insideStartingNode,
        intersectingNode: intersectingNode,
    }
}

export default formatIntersetingNodes;