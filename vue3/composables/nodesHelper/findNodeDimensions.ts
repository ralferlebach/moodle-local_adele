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
 * find Node Dimensions module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface Node {
  id: string;
  dimensions?: {
    height: number;
  };
}

interface CustomSVGElement {
  height: {
    animVal: {
      value: number;
    };
  };
}

const findNodeDimensions = (node: Node, findNode: (id: string) => Node): number => {
  const element = document.getElementById(node.id) as CustomSVGElement | null;

  if (element && element.height?.animVal) {
    return element.height.animVal.value;
  }

  const queryElement = document.querySelector('[data-id="' + node.id + '"]');

  if (queryElement) {
    const foundNode = findNode(node.id);

    if (foundNode.dimensions) {
      return foundNode.dimensions.height;
    }
  }
  return 200;
}

export default findNodeDimensions;