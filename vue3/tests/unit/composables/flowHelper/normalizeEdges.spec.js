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
 * One edge per connection (local_adele #603) - the same rule as the server's
 * local_adele\path_edges.
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import fs from 'fs';
import path from 'path';
import {
  edgeKey, hasEdge, normalizeEdges, uniqueEdges, withUniqueEdges,
} from '../../../../composables/flowHelper/normalizeEdges';
import addCustomEdge from '../../../../composables/addCustomEdge';
import loadFlowChart from '../../../../composables/loadFlowChart';
import recalculateParentChild from '../../../../composables/recalculateParentChild';
import standaloneNodeCheck from '../../../../composables/standaloneNodeCheck';

const fixtures = Object.fromEntries(
  JSON.parse(fs.readFileSync(
    path.resolve(__dirname, '../../../../../tests/playwright/fixtures/learningpaths/local_adele_learning_paths.json'),
    'utf8'
  )).map((row) => [row.name, JSON.parse(row.json)])
);

const edge = (id, source, target, more = {}) => ({
  id, source, target, sourceHandle: 'source', targetHandle: 'target', ...more,
});

describe('normalizeEdges', () => {
  it('(a) the reference fixtures: the four copies go, every id once, the first edge of a connection stays', () => {
    const expected = {
      'Verzweigt, gestapelt B1': [7, 5, ['dndnode_3dndnode_1', 'dndnode_5dndnode_4']],
      'Äquivalenzumformung (neu)': [14, 12, ['dndnode_4dndnode_3', 'dndnode_11dndnode_6']],
    };
    for (const [name, json] of Object.entries(fixtures)) {
      const before = json.tree.edges;
      const result = normalizeEdges(before);
      if (!expected[name]) {
        expect(result.edges).toEqual(before);
        expect(result.removed + result.renamed).toBe(0);
        continue;
      }
      const [n, m, doubled] = expected[name];
      expect(before).toHaveLength(n);
      expect(result.edges).toHaveLength(m);
      expect(result.removed).toBe(2);
      const ids = result.edges.map((e) => e.id);
      expect(new Set(ids).size).toBe(ids.length);
      doubled.forEach((id) => {
        expect(before.filter((e) => e.id === id)).toHaveLength(2);
        const kept = result.edges.filter((e) => e.id === id);
        expect(kept).toHaveLength(1);
        expect(kept[0].type).toBe('default');
      });
      // Idempotent.
      expect(normalizeEdges(result.edges).edges).toEqual(result.edges);
    }
  });

  it('(b) one connection twice - with and without a type - is one; (d) another handle is another connection', () => {
    const vueflow = edge('n2n1', 'n1', 'n2', { type: 'default', data: {} });
    const raw = addCustomEdge('n2', 'n1');
    expect(edgeKey(raw)).toBe(edgeKey(vueflow));
    const other = edge('x', 'n1', 'n2', { sourceHandle: 'source_b' });
    const result = normalizeEdges([vueflow, raw, other]);
    expect(result.edges).toEqual([vueflow, other]);
    expect(result.removed).toBe(1);
    expect(hasEdge([vueflow], raw)).toBe(true);
    expect(hasEdge([vueflow], other)).toBe(false);
    expect(hasEdge(null, raw)).toBe(false);
  });

  it('(c) the same id for another connection: both stay, the later one gets a free id, the input is not changed', () => {
    const a = edge('n2n1', 'n1', 'n2');
    const b = edge('n2n1', 'n2', 'n1');
    const c = edge('n2n1_2', 'n3', 'n4');
    const result = normalizeEdges([a, b, c]);
    expect(result.edges.map((e) => e.id)).toEqual(['n2n1', 'n2n1_2', 'n2n1_2_2']);
    expect(result.conflicts).toEqual([{ id: 'n2n1', newid: 'n2n1_2' }, { id: 'n2n1_2', newid: 'n2n1_2_2' }]);
    expect(b.id).toBe('n2n1');
    expect(result.edges[1]).toEqual({ ...b, id: 'n2n1_2' });
    const again = normalizeEdges(result.edges);
    expect(again.edges).toEqual(result.edges);
    expect(again.removed + again.renamed).toBe(0);
  });

  it('odd input; uniqueEdges warns about a conflict; withUniqueEdges for saving leaves the path as it is', () => {
    expect(normalizeEdges(null).edges).toEqual([]);
    expect(normalizeEdges([null, 'x', edge('a', '1', '2')]).edges).toHaveLength(1);
    const warn = jest.spyOn(console, 'warn').mockImplementation(() => {});
    expect(uniqueEdges([edge('a', '1', '2'), edge('a', '2', '1')]).map((e) => e.id)).toEqual(['a', 'a_2']);
    expect(warn).toHaveBeenCalledTimes(1);
    uniqueEdges([edge('a', '1', '2')]);
    expect(warn).toHaveBeenCalledTimes(1);
    warn.mockRestore();
    const json = fixtures['Verzweigt, gestapelt B1'];
    const saved = withUniqueEdges(json);
    expect(saved.tree.edges).toHaveLength(5);
    expect(json.tree.edges).toHaveLength(7);
    expect(saved.tree.nodes).toBe(json.tree.nodes);
    expect(withUniqueEdges(null)).toBeNull();
    expect(withUniqueEdges({ modules: [] })).toEqual({ modules: [] });
  });

  it('a stored path is drawn with one edge per connection (loadFlowChart)', () => {
    const tree = JSON.parse(JSON.stringify(fixtures['Äquivalenzumformung (neu)'].tree));
    const flow = loadFlowChart(tree, 'teacher');
    expect(flow.edges).toHaveLength(12);
    expect(flow.edges.every((e) => e.deletable === false)).toBe(true);
  });

  it('the evaluation of the edges is the same with and without the copies: parents, children, standalone check', () => {
    for (const name of ['Verzweigt, gestapelt B1', 'Äquivalenzumformung (neu)']) {
      const raw = JSON.parse(JSON.stringify(fixtures[name].tree));
      const clean = JSON.parse(JSON.stringify(fixtures[name].tree));
      clean.edges = normalizeEdges(clean.edges).edges;
      const relations = (tree) => recalculateParentChild(tree, 'parentCourse', 'childCourse', 'starting_node')
        .nodes.map((n) => [n.id, n.parentCourse, n.childCourse]);
      expect(relations(clean)).toEqual(relations(raw));
      expect(standaloneNodeCheck(clean)).toBe(standaloneNodeCheck(raw));
    }
  });
});
