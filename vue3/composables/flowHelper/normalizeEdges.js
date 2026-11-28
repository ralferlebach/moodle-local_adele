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
 * One identity per connection of a learning path (local_adele #603).
 *
 * Two edges are the same connection when they agree in type, source, source
 * handle, target and target handle - not because their ids happen to be equal
 * (an id is "source + target" of the editor and may collide). A learning path
 * knows no parallel connections: between two handles of two nodes there is
 * one relation or none (the parent/child lists of the nodes are sets).
 *
 * normalizeEdges() keeps the first edge of each connection in its place,
 * drops the later copies and gives an edge whose id is already taken by
 * another connection a free id (`<id>_2`, `<id>_3`, ...); every other edge is
 * returned as it came. It is deterministic and idempotent and never changes
 * its input. The same rule runs on the server
 * (local_adele\path_edges::normalize()).
 *
 * @module     local_adele/normalizeEdges
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** VueFlow's type of an edge without one. */
export const DEFAULT_EDGE_TYPE = 'default';

/**
 * The identity of a connection.
 *
 * @param {object} edge
 * @returns {string}
 */
export function edgeKey(edge) {
  const e = edge || {};
  return JSON.stringify([
    e.type || DEFAULT_EDGE_TYPE,
    String(e.source ?? ''),
    String(e.sourceHandle ?? ''),
    String(e.target ?? ''),
    String(e.targetHandle ?? ''),
  ]);
}

/**
 * Whether a list already holds this connection.
 *
 * @param {Array} edges
 * @param {object} edge
 * @returns {boolean}
 */
export function hasEdge(edges, edge) {
  const key = edgeKey(edge);
  return (edges || []).some((e) => edgeKey(e) === key);
}

/**
 * One edge per connection, one connection per id.
 *
 * @param {Array|null} edges
 * @returns {{edges: Array, removed: number, renamed: number, conflicts: Array}}
 *   conflicts: {id, newid} for each edge that had to take another id.
 */
export function normalizeEdges(edges) {
  const out = [];
  const keys = new Set();
  const ids = new Set();
  const conflicts = [];
  let removed = 0;
  for (const edge of Array.isArray(edges) ? edges : []) {
    if (!edge || typeof edge !== 'object') {
      continue;
    }
    const key = edgeKey(edge);
    if (keys.has(key)) {
      removed++;
      continue;
    }
    keys.add(key);
    let kept = edge;
    const id = String(edge.id ?? '');
    if (ids.has(id)) {
      let n = 2;
      while (ids.has(`${id}_${n}`)) {
        n++;
      }
      kept = { ...edge, id: `${id}_${n}` };
      conflicts.push({ id, newid: kept.id });
    }
    ids.add(String(kept.id ?? ''));
    out.push(kept);
  }
  return { edges: out, removed, renamed: conflicts.length, conflicts };
}

/**
 * The edges of a tree, normalized; the input stays as it is.
 *
 * @param {Array|null} edges
 * @returns {Array}
 */
export function uniqueEdges(edges) {
  const result = normalizeEdges(edges);
  if (result.renamed && typeof window !== 'undefined' && window.console) {
    // eslint-disable-next-line no-console
    console.warn('local_adele: edges with the same id but another connection got a new id', result.conflicts);
  }
  return result.edges;
}

/**
 * A learning path's json with its tree's edges normalized, for saving; the
 * input stays as it is.
 *
 * @param {object|null} json
 * @returns {object|null}
 */
export function withUniqueEdges(json) {
  if (!json || typeof json !== 'object' || !json.tree || !Array.isArray(json.tree.edges)) {
    return json;
  }
  return { ...json, tree: { ...json.tree, edges: uniqueEdges(json.tree.edges) } };
}
