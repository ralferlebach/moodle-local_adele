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
 * draw Dropzone module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface StoreState {
  strings: {
    composables_drop_zone_parent: string;
    composables_drop_zone_child: string;
    composables_drop_zone_add: string;
    composables_drop_zone_or: string;
  };
}

interface Store {
  state: StoreState;
}

interface Position {
  x: number;
  y: number;
}

interface ClosestNode {
  id: string;
  position: Position;
  dimensions: {
    height: number;
  };
  childCourse?: string[];
  parentCourse?: string[];
  computedPosition: Position;
}

interface DropZoneNode {
  id: string;
  type: string;
  position: Position;
  label: string;
  data: {
    opacity: string;
    bgcolor: string;
    height: string;
    infotext?: string;
    width?: number;
  };
  style: {
    zIndex: number;
  };
}

interface DropZoneEdge {
  id: string;
  source: string;
  sourceHandle: string;
  target: string;
  targetHandle: string;
  type: string;
}

interface NewDrop {
  nodes: DropZoneNode[];
  edges: DropZoneEdge[];
}

interface NodeData {
  opacity: string;
  bgcolor: string;
  height: string;
  infotext?: string;
  width?: number;
}

interface DropZoneCourseNode {
  name: string;
  positionY: number;
  positionX: number;
  type: string;
  width?: number;
}

const drawDropzone = async (closestNode: ClosestNode, store: Store): Promise<NewDrop> => {
  const dropZoneCourseNodes: Record<string, DropZoneCourseNode> = {
    parent: {
      name: store.state.strings.composables_drop_zone_parent,
      positionY: -250,
      positionX: 0,
      type: 'dropzone',
    },
    child: {
      name: store.state.strings.composables_drop_zone_child,
      positionY: 50 + closestNode.dimensions.height,
      positionX: 0,
      type: 'dropzone',
    },
    and: {
      name: store.state.strings.composables_drop_zone_add,
      positionY: 200,
      positionX: 450,
      type: 'conditionaldropzone',
    },
    or: {
      name: store.state.strings.composables_drop_zone_or,
      positionY: 300,
      positionX: -350,
      type: 'conditionaldropzone',
    }
  };

  // Precompute reusable values
  const baseX = closestNode.position.x;
  const baseY = closestNode.position.y;
  const offsetY = getOffsetY(closestNode);
  const defaultOpacity = '0.6';
  const defaultBgColor = 'grey';
  const defaultHeight = '200px';

  const newDrop: NewDrop = { nodes: [], edges: [] };

  await Promise.all(Object.keys(dropZoneCourseNodes).map(async key => {
    const nodeInfo = dropZoneCourseNodes[key];
    const isConditional = key === 'and' || key === 'or';

    const position: Position = {
      x: isConditional ? baseX + nodeInfo.positionX : getOffsetX(closestNode, key),
      y: isConditional ? offsetY : baseY + nodeInfo.positionY,
    };

    const newNode: DropZoneNode = {
      id: `dropzone_${key}`,
      type: nodeInfo.type,
      position,
      label: 'default node',
      data: {
        opacity: defaultOpacity,
        bgcolor: defaultBgColor,
        height: defaultHeight,
        infotext: nodeInfo.name,
        width: nodeInfo.width,
      },
      style: { zIndex: 1000 },
    };

    newDrop.nodes.push(newNode);

    if (!isConditional) {
      const newEdge: DropZoneEdge = {
        id: `${closestNode.id}-${key}`,
        source: closestNode.id,
        sourceHandle: key === 'child' ? 'source' : 'target',
        target: newNode.id,
        targetHandle: key === 'child' ? 'target_and' : 'source_and',
        type: 'default',
      };
      newDrop.edges.push(newEdge);
    }
  }));
  return newDrop;
};



const getOffsetX = (closestNode: ClosestNode, relation: string): number => {
    let relationHandle = closestNode.childCourse
    if(relation == 'parent'){
      relationHandle = closestNode.parentCourse
    }
    if(relationHandle != undefined && (relationHandle.length == 0 ||
    relationHandle.indexOf('starting_node') != -1)){
      return closestNode.position.x
    }
    return closestNode.position.x + 500
}

const getOffsetY = (closestNode: ClosestNode): number => {
    return closestNode.computedPosition.y + closestNode.dimensions.height/2 - 100
}

export default drawDropzone;