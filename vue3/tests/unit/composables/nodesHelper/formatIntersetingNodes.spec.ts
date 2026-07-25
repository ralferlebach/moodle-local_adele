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
 * format Interseting Nodes.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import formatIntersetingNodes from '../../../../composables/nodesHelper/formatIntersetingNodes';

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

describe('formatIntersetingNodes', () => {
  let node: Node, intersectingNode: IntersectingNode | null, closestNode: string, insideStartingNode: boolean, store: Store;

  beforeEach(() => {
    node = {
      id: 'node1',
      data: {}
    };

    intersectingNode = null;
    closestNode = 'closestNode1';
    insideStartingNode = false;

    store = {
      state: {
        strings: {
          completion_drop_here: 'Drop here!',
          composables_new_node: 'New node!',
          composables_drop_zone_parent: 'Drop Zone Parent',
          composables_drop_zone_child: 'Drop Zone Child',
          composables_drop_zone_add: 'Drop Zone Add',
          composables_drop_zone_or: 'Drop Zone Or',
        }
      }
    };
  });

  it('should format the node when nodes are intersecting', () => {
    const nodesIntersecting = true;
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.75',
      bgcolor: 'chartreuse',
      infotext: 'Drop here!',
    });
    expect(result.intersectingNode).toEqual({ closestnode: closestNode, dropzone: node });
    expect(result.insideStartingNode).toBe(false);
  });

  it('should format the node when nodes are not intersecting and set infotext for dropzone_parent', () => {
    const nodesIntersecting = false;
    node.id = 'dropzone_parent';
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'Drop Zone Parent',
    });
    expect(result.intersectingNode).toBeNull();
    expect(result.insideStartingNode).toBe(false);
  });

  it('should format the node when nodes are not intersecting and set infotext for dropzone_child', () => {
    const nodesIntersecting = false;
    node.id = 'dropzone_child';
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'Drop Zone Child',
    });
    expect(result.intersectingNode).toBeNull();
    expect(result.insideStartingNode).toBe(false);
  });

  it('should format the node when nodes are not intersecting and set infotext for dropzone_and', () => {
    const nodesIntersecting = false;
    node.id = 'dropzone_and';
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'Drop Zone Add',
    });
    expect(result.intersectingNode).toBeNull();
    expect(result.insideStartingNode).toBe(false);
  });

  it('should format the node when nodes are not intersecting and set infotext for dropzone_or', () => {
    const nodesIntersecting = false;
    node.id = 'dropzone_or';
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'Drop Zone Or',
    });
    expect(result.intersectingNode).toBeNull();
    expect(result.insideStartingNode).toBe(false);
  });

  it('should format the node when nodes are not intersecting and node is not a dropzone', () => {
    const nodesIntersecting = false;
    const result = formatIntersetingNodes(nodesIntersecting, node, intersectingNode, closestNode, insideStartingNode, store);

    expect(result.node.data).toEqual({
      opacity: '0.6',
      bgcolor: 'grey',
      infotext: 'New node!',
    });
    expect(result.intersectingNode).toBeNull();
    expect(result.insideStartingNode).toBe(true);
  });


});