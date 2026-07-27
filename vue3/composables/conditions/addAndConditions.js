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
 * add And Conditions module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import addCustomEdge from "../addCustomEdge";

const  addAndConditions = (intersectingnode, edges, id) => {
  // Get child and parent nodes and edges.
  const parentNodes = intersectingnode.closestnode.parentCourse
  const childNodes = intersectingnode.closestnode.childCourse
  let newEdges = [];
  let newRestrictions = intersectingnode.closestnode.restriction;
  let newOtherRestrictions = [];
  let newCompletions = intersectingnode.closestnode.completion;

  edges.value.forEach((edge) => {
    if (edge.id.indexOf('-') == -1 &&
      edge.id.indexOf(intersectingnode.closestnode.id) != -1) {
        const destination = edge.id.replace(intersectingnode.closestnode.id, '');
        if (intersectingnode.closestnode.id != edge.source) {
          newEdges.push(addCustomEdge(id,destination))
        }else {
          newEdges.push(addCustomEdge(destination, id))
          newOtherRestrictions.push(destination)
        }
    }
  })

  return {
    parentNodes: parentNodes,
    childNodes: childNodes,
    newEdges: newEdges,
    newRestrictions: newRestrictions,
    newCompletions: newCompletions,
    newOtherRestrictions: newOtherRestrictions
  };
}

export default addAndConditions;