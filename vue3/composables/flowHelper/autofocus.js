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
 * Role-dependent auto-focus after a refresh changed a learning path
 * (mod_adele #27), in three separate parts:
 *
 *   compareUserPathStates()  what changed between two stored states;
 *   resolveAutofocusTarget() where to look, by the role's mode;
 *   focusNodes()             the visual move, nothing else.
 *
 * Only a change of a node's state counts: its status (completed,
 * accessible, not accessible, closed), its completion or restriction
 * status. Texts, colours, layout or translations do not. The display
 * changes, nothing is stored or recalculated.
 *
 * @module     local_adele/autofocus
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { FOCUS_LASTCHANGE, FOCUS_NEXTOPEN, FOCUS_NONE } from './pathViewSettings';

const OPEN = ['accessible'];
const LOCKED = ['not_accessible', 'closed'];

/**
 * The nodes of a stored path state.
 *
 * @param {object|null} path The user path relation (json.tree.nodes).
 * @returns {object[]}
 */
const nodesOf = (path) => (path && path.json && path.json.tree && Array.isArray(path.json.tree.nodes)
  ? path.json.tree.nodes : []);

/**
 * The comparable state of a node, and the time of its last state change if
 * the server stores one (none of completedat, laststatechange,
 * statuschangedat is written today; they are used once they are).
 *
 * @param {object} node
 * @returns {{status: ?string, completion: ?string, restriction: ?string, time: number}}
 */
export function nodeState(node) {
  const data = node.data || {};
  const fb = (data.completion && data.completion.feedback) || {};
  const time = Number(data.laststatechange || data.statuschangedat || data.completedat ||
    fb.laststatechange || fb.statuschangedat || fb.completedat || 0);
  return {
    status: fb.status || null,
    completion: fb.status_completion || null,
    restriction: fb.status_restriction || null,
    time: Number.isFinite(time) ? time : 0,
  };
}

/**
 * Natural order of node ids (dndnode_2 before dndnode_10).
 *
 * @param {string} a
 * @param {string} b
 * @returns {number}
 */
const byId = (a, b) => String(a).localeCompare(String(b), undefined, { numeric: true });

/**
 * What changed between two stored states of a path.
 *
 * @param {object|null} previousPath
 * @param {object|null} currentPath
 * @returns {{changed: boolean, newlyCompletedNodeIds: string[], newlyOpenedNodeIds: string[],
 *   newlyClosedNodeIds: string[], latestChangedNodeId: ?string}}
 */
export function compareUserPathStates(previousPath, currentPath) {
  const before = {};
  nodesOf(previousPath).forEach((n) => {
    before[n.id] = nodeState(n);
  });
  const changes = {
    changed: false,
    newlyCompletedNodeIds: [],
    newlyOpenedNodeIds: [],
    newlyClosedNodeIds: [],
    latestChangedNodeId: null,
  };
  if (!previousPath) {
    return changes;
  }
  const changedIds = [];
  const times = {};
  nodesOf(currentPath).forEach((n) => {
    // A node seen for the first time is no change of state.
    if (!(n.id in before)) {
      return;
    }
    const was = before[n.id];
    const is = nodeState(n);
    if (was.status === is.status && was.completion === is.completion && was.restriction === is.restriction) {
      return;
    }
    changedIds.push(n.id);
    times[n.id] = is.time;
    if (is.status === 'completed' && was.status !== 'completed') {
      changes.newlyCompletedNodeIds.push(n.id);
    }
    if (OPEN.includes(is.status) && !OPEN.includes(was.status) && is.status !== was.status) {
      changes.newlyOpenedNodeIds.push(n.id);
    }
    if (LOCKED.includes(is.status) && !LOCKED.includes(was.status)) {
      changes.newlyClosedNodeIds.push(n.id);
    }
  });
  changes.changed = changedIds.length > 0;
  if (changes.changed) {
    // Latest change time first, then completion before other changes, then
    // the stable node id.
    const completed = new Set(changes.newlyCompletedNodeIds);
    changes.latestChangedNodeId = [...changedIds].sort((a, b) =>
      (times[b] - times[a]) || ((completed.has(b) ? 1 : 0) - (completed.has(a) ? 1 : 0)) || byId(a, b))[0];
  }
  ['newlyCompletedNodeIds', 'newlyOpenedNodeIds', 'newlyClosedNodeIds'].forEach((k) => changes[k].sort(byId));
  return changes;
}

/**
 * The open, not yet completed nodes of a path, without those that have an
 * open predecessor themselves (the earlier one is next, not the later).
 *
 * @param {object} currentPath
 * @returns {string[]}
 */
export function nextOpenNodeIds(currentPath) {
  const nodes = nodesOf(currentPath);
  const open = new Set(nodes.filter((n) => OPEN.includes(nodeState(n).status)).map((n) => n.id));
  const parents = {};
  const edges = (currentPath && currentPath.json && currentPath.json.tree && currentPath.json.tree.edges) || [];
  edges.forEach((e) => {
    (parents[e.target] = parents[e.target] || []).push(e.source);
  });
  nodes.forEach((n) => {
    (Array.isArray(n.parentCourse) ? n.parentCourse : []).forEach((p) => {
      if (p !== 'starting_node') {
        (parents[n.id] = parents[n.id] || []).push(p);
      }
    });
  });
  const hasOpenAncestor = (id, seen = new Set()) => (parents[id] || []).some((p) => {
    if (seen.has(p)) {
      return false;
    }
    seen.add(p);
    return open.has(p) || hasOpenAncestor(p, seen);
  });
  return [...open].filter((id) => !hasOpenAncestor(id)).sort(byId);
}

/**
 * The starting nodes of a path.
 *
 * @param {object} currentPath
 * @returns {string[]}
 */
const startNodeIds = (currentPath) => nodesOf(currentPath)
  .filter((n) => Array.isArray(n.parentCourse) && n.parentCourse.includes('starting_node'))
  .map((n) => n.id).sort(byId);

/**
 * Where to look after a change, by the role's mode.
 *
 * @param {object} args
 * @param {number} args.mode FOCUS_NONE, FOCUS_LASTCHANGE or FOCUS_NEXTOPEN.
 * @param {object} args.changes The result of compareUserPathStates().
 * @param {object} args.currentPath The state now shown.
 * @returns {string[]} The nodes to show together; [] = do not move.
 */
export function resolveAutofocusTarget({ mode, changes, currentPath }) {
  if (mode === FOCUS_NONE || !changes || !changes.changed) {
    return [];
  }
  const lastCompleted = changes.newlyCompletedNodeIds.length
    ? [...changes.newlyCompletedNodeIds].sort((a, b) =>
      (nodeStateById(currentPath, b).time - nodeStateById(currentPath, a).time) || byId(a, b))[0]
    : null;
  if (mode === FOCUS_LASTCHANGE) {
    const target = lastCompleted || changes.latestChangedNodeId;
    return target ? [target] : [];
  }
  if (mode === FOCUS_NEXTOPEN) {
    const next = nextOpenNodeIds(currentPath);
    if (next.length) {
      return next;
    }
    if (lastCompleted) {
      return [lastCompleted];
    }
    if (changes.latestChangedNodeId) {
      return [changes.latestChangedNodeId];
    }
    return startNodeIds(currentPath);
  }
  return [];
}

/**
 * The state of one node of a path by id.
 *
 * @param {object} path
 * @param {string} id
 * @returns {object}
 */
function nodeStateById(path, id) {
  const node = nodesOf(path).find((n) => n.id === id);
  return node ? nodeState(node) : { time: 0 };
}

/**
 * Which role's mode applies: the teacher's when the server decided the
 * teacher view (from effective capabilities) or the path shown is somebody
 * else's; the learner's for one's own path in the student view.
 *
 * @param {object} args
 * @param {string} args.view The view the server chose ('teacher'/'student').
 * @param {number|string} args.currentUser The person looking.
 * @param {number|string} args.pathUser The person whose path is shown.
 * @returns {'teacher'|'learner'}
 */
export function autofocusRole({ view, currentUser, pathUser }) {
  if (view === 'teacher') {
    return 'teacher';
  }
  if (pathUser && String(pathUser) !== String(currentUser)) {
    return 'teacher';
  }
  return 'learner';
}

/**
 * The mode for a role from the settings; 0 when auto-focus is off.
 *
 * @param {object} settings parsePathViewSettings() result.
 * @param {'teacher'|'learner'} role
 * @returns {number}
 */
export function autofocusMode(settings, role) {
  if (!settings || !settings.autofocusenabled) {
    return FOCUS_NONE;
  }
  return role === 'teacher' ? settings.autofocusteachermode : settings.autofocuslearnermode;
}

/**
 * Show the given nodes together - the visual move only.
 *
 * @param {string[]} nodeIds
 * @param {Function} fitView VueFlow's fitView.
 * @returns {boolean} Whether the view moved.
 */
export function focusNodes(nodeIds, fitView) {
  if (!Array.isArray(nodeIds) || !nodeIds.length || typeof fitView !== 'function') {
    return false;
  }
  fitView({ nodes: nodeIds, padding: 0.3, duration: 600, maxZoom: 1 });
  return true;
}
