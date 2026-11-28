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
 * Auto-focus after a change (mod_adele #27, #32): comparison, target and
 * the visual move, each on its own; settings as the page hands them over.
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
  autofocusMode, autofocusRole, compareUserPathStates, focusNodes, nextOpenNodeIds, resolveAutofocusTarget,
} from '../../../../composables/flowHelper/autofocus';
import { DEFAULTS, parsePathViewSettings } from '../../../../composables/flowHelper/pathViewSettings';

/**
 * A stored path state: node id => status (plus optional extras).
 *
 * @param {object} statuses id => status or [status, extra data]
 * @param {Array} edges [source, target] pairs
 * @returns {object}
 */
function path(statuses, edges = []) {
  return {
    json: {
      tree: {
        nodes: Object.entries(statuses).map(([id, value]) => {
          const [status, extra] = Array.isArray(value) ? value : [value, {}];
          return {
            id,
            parentCourse: extra.start ? ['starting_node'] : (extra.parents || []),
            data: {
              fullname: extra.name || id,
              completion: { feedback: { status, status_completion: extra.completion || null, ...(extra.fb || {}) } },
              ...(extra.data || {}),
            },
          };
        }),
        edges: edges.map(([source, target]) => ({ source, target })),
      },
    },
  };
}

// A -> B -> D, A -> C -> D (B and C parallel).
const EDGES = [['dndnode_1', 'dndnode_2'], ['dndnode_1', 'dndnode_3'], ['dndnode_2', 'dndnode_4'], ['dndnode_3', 'dndnode_4']];
const START = { dndnode_1: ['accessible', { start: true }], dndnode_2: 'not_accessible', dndnode_3: 'not_accessible',
  dndnode_4: 'not_accessible' };
const AFTER_A = { dndnode_1: ['completed', { start: true }], dndnode_2: 'accessible', dndnode_3: 'accessible',
  dndnode_4: 'not_accessible' };

describe('compareUserPathStates', () => {
  it('names completed, opened and closed nodes and the latest change', () => {
    const changes = compareUserPathStates(path(START, EDGES), path(AFTER_A, EDGES));
    expect(changes).toEqual({
      changed: true,
      newlyCompletedNodeIds: ['dndnode_1'],
      newlyOpenedNodeIds: ['dndnode_2', 'dndnode_3'],
      newlyClosedNodeIds: [],
      latestChangedNodeId: 'dndnode_1',
    });
    const back = compareUserPathStates(path(AFTER_A, EDGES), path(START, EDGES));
    expect(back.newlyClosedNodeIds).toEqual(['dndnode_2', 'dndnode_3']);
    expect(back.newlyOpenedNodeIds).toEqual(['dndnode_1']);
  });

  it('an unchanged state, or changed texts, colours or names only, is no change', () => {
    expect(compareUserPathStates(path(START), path(START)).changed).toBe(false);
    const renamed = path({ ...START, dndnode_2: ['not_accessible', { name: 'Other text', fb: { completion: { before: 'x' } } }] });
    expect(compareUserPathStates(path(START), renamed).changed).toBe(false);
  });

  it('the first load (no previous state) and nodes seen for the first time are no change', () => {
    expect(compareUserPathStates(null, path(AFTER_A)).changed).toBe(false);
    expect(compareUserPathStates(path({ dndnode_1: 'accessible' }), path({ dndnode_1: 'accessible', dndnode_9: 'completed' })).changed)
      .toBe(false);
  });

  it('a change of the completion status alone counts', () => {
    const was = path({ dndnode_1: ['accessible', { completion: 'before' }] });
    const is = path({ dndnode_1: ['accessible', { completion: 'after' }] });
    expect(compareUserPathStates(was, is)).toMatchObject({ changed: true, latestChangedNodeId: 'dndnode_1' });
  });

  it('latest change: a stored time first, then completion before other changes, then the node id', () => {
    const was = path({ dndnode_2: 'not_accessible', dndnode_10: 'accessible', dndnode_3: 'accessible' });
    const is = path({ dndnode_2: 'accessible', dndnode_10: 'completed', dndnode_3: 'completed' });
    expect(compareUserPathStates(was, is).latestChangedNodeId).toBe('dndnode_3');
    const timed = path({ dndnode_2: ['accessible', { data: { laststatechange: 200 } }], dndnode_10: 'completed',
      dndnode_3: ['completed', { data: { completedat: 100 } }] });
    expect(compareUserPathStates(was, timed).latestChangedNodeId).toBe('dndnode_2');
  });

  it('does not modify the states it compares', () => {
    const was = path(START, EDGES);
    const is = path(AFTER_A, EDGES);
    const copies = JSON.stringify([was, is]);
    compareUserPathStates(was, is);
    resolveAutofocusTarget({ mode: 2, changes: compareUserPathStates(was, is), currentPath: is });
    expect(JSON.stringify([was, is])).toBe(copies);
  });
});

describe('resolveAutofocusTarget', () => {
  const changes = compareUserPathStates(path(START, EDGES), path(AFTER_A, EDGES));

  it('teacher default (1): the node completed last', () => {
    expect(resolveAutofocusTarget({ mode: 1, changes, currentPath: path(AFTER_A, EDGES) })).toEqual(['dndnode_1']);
  });

  it('learner default (2): all next open nodes together, parallel ones included', () => {
    expect(resolveAutofocusTarget({ mode: 2, changes, currentPath: path(AFTER_A, EDGES) })).toEqual(['dndnode_2', 'dndnode_3']);
  });

  it('a later open node waits behind an earlier open one', () => {
    const now = { ...AFTER_A, dndnode_4: 'accessible' };
    expect(nextOpenNodeIds(path(now, EDGES))).toEqual(['dndnode_2', 'dndnode_3']);
  });

  it('no open node: the node completed last, else the last change, else the start', () => {
    const done = { dndnode_1: ['completed', { start: true }], dndnode_2: 'completed', dndnode_3: 'completed', dndnode_4: 'completed' };
    const before = { ...done, dndnode_4: 'accessible' };
    const c = compareUserPathStates(path(before, EDGES), path(done, EDGES));
    expect(resolveAutofocusTarget({ mode: 2, changes: c, currentPath: path(done, EDGES) })).toEqual(['dndnode_4']);
    const closed = { ...done, dndnode_4: 'closed' };
    const c2 = compareUserPathStates(path(done, EDGES), path(closed, EDGES));
    expect(resolveAutofocusTarget({ mode: 2, changes: c2, currentPath: path(closed, EDGES) })).toEqual(['dndnode_4']);
  });

  it('mode 0, or no change: no move', () => {
    expect(resolveAutofocusTarget({ mode: 0, changes, currentPath: path(AFTER_A, EDGES) })).toEqual([]);
    const none = compareUserPathStates(path(AFTER_A, EDGES), path(AFTER_A, EDGES));
    expect(resolveAutofocusTarget({ mode: 2, changes: none, currentPath: path(AFTER_A, EDGES) })).toEqual([]);
    expect(resolveAutofocusTarget({ mode: 1, changes: none, currentPath: path(AFTER_A, EDGES) })).toEqual([]);
  });
});

describe('role and mode', () => {
  it('the teacher view, or somebody else\'s path, is the teacher\'s; one\'s own in the student view the learner\'s', () => {
    expect(autofocusRole({ view: 'teacher', currentUser: 5, pathUser: 5 })).toBe('teacher');
    expect(autofocusRole({ view: 'student', currentUser: 5, pathUser: 7 })).toBe('teacher');
    expect(autofocusRole({ view: 'student', currentUser: 5, pathUser: '5' })).toBe('learner');
  });

  it('the role\'s mode, 0 when auto-focus is off', () => {
    const s = parsePathViewSettings(null);
    expect(autofocusMode(s, 'teacher')).toBe(1);
    expect(autofocusMode(s, 'learner')).toBe(2);
    expect(autofocusMode({ ...s, autofocusenabled: 0 }, 'learner')).toBe(0);
    expect(autofocusMode({ ...s, autofocuslearnermode: 1, autofocusteachermode: 2 }, 'teacher')).toBe(2);
  });
});

describe('focusNodes', () => {
  it('moves the view to the nodes, together, and nothing else', () => {
    const fitView = jest.fn();
    expect(focusNodes(['dndnode_2', 'dndnode_3'], fitView)).toBe(true);
    expect(fitView).toHaveBeenCalledTimes(1);
    expect(fitView.mock.calls[0][0].nodes).toEqual(['dndnode_2', 'dndnode_3']);
  });

  it('moves the view only: the keyboard focus stays where it is', () => {
    const input = document.createElement('input');
    document.body.appendChild(input);
    input.focus();
    focusNodes(['dndnode_2'], jest.fn());
    expect(document.activeElement).toBe(input);
    input.remove();
  });

  it('no node: no move', () => {
    const fitView = jest.fn();
    expect(focusNodes([], fitView)).toBe(false);
    expect(focusNodes(null, fitView)).toBe(false);
    expect(fitView).not.toHaveBeenCalled();
  });
});

describe('parsePathViewSettings', () => {
  it('defaults: mode 2, 3000 ms, 300 ms, auto-focus on, teacher 1, learner 2', () => {
    expect(parsePathViewSettings(null)).toEqual(DEFAULTS);
    expect(DEFAULTS).toEqual({ pollingmode: 2, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
      autofocusteachermode: 1, autofocuslearnermode: 2 });
    expect(parsePathViewSettings('not json')).toEqual(DEFAULTS);
  });

  it('valid values are taken, invalid ones fall back to the default', () => {
    expect(parsePathViewSettings(JSON.stringify({ pollingmode: 3, pollinginterval: 1000, pollingdebounce: 0,
      autofocusenabled: 0, autofocusteachermode: 2, autofocuslearnermode: 0 })))
      .toEqual({ pollingmode: 3, pollinginterval: 1000, pollingdebounce: 0, autofocusenabled: 0,
        autofocusteachermode: 2, autofocuslearnermode: 0 });
    expect(parsePathViewSettings({ pollingmode: 4, pollinginterval: 999, pollingdebounce: 10001,
      autofocusteachermode: -1, autofocuslearnermode: 1.5 })).toEqual(DEFAULTS);
    expect(parsePathViewSettings({ pollinginterval: 300000, pollingdebounce: 10000 }))
      .toMatchObject({ pollinginterval: 300000, pollingdebounce: 10000 });
  });
});

describe('resolveAutofocusTarget - the rest of the rules', () => {
  it('several nodes completed at once: the one with the latest stored time, else the first by id', () => {
    const was = path({ dndnode_1: 'accessible', dndnode_2: 'accessible', dndnode_3: 'accessible' });
    const is = path({ dndnode_1: 'completed', dndnode_2: ['completed', { data: { completedat: 50 } }],
      dndnode_3: ['completed', { data: { completedat: 70 } }] });
    const c = compareUserPathStates(was, is);
    expect(resolveAutofocusTarget({ mode: 1, changes: c, currentPath: is })).toEqual(['dndnode_3']);
    const untimed = path({ dndnode_1: 'completed', dndnode_2: 'completed', dndnode_3: 'completed' });
    const c2 = compareUserPathStates(was, untimed);
    expect(resolveAutofocusTarget({ mode: 1, changes: c2, currentPath: untimed })).toEqual(['dndnode_1']);
  });

  it('teacher mode 1 without a completion: the node changed last', () => {
    const was = path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' });
    const is = path({ dndnode_1: 'accessible', dndnode_2: 'accessible' });
    const c = compareUserPathStates(was, is);
    expect(resolveAutofocusTarget({ mode: 1, changes: c, currentPath: is })).toEqual(['dndnode_2']);
  });

  it('no open node and nothing named: the starting nodes', () => {
    const now = path({ dndnode_1: ['completed', { start: true }], dndnode_2: 'closed' });
    const changes = { changed: true, newlyCompletedNodeIds: [], newlyOpenedNodeIds: [], newlyClosedNodeIds: [],
      latestChangedNodeId: null };
    expect(resolveAutofocusTarget({ mode: 2, changes, currentPath: now })).toEqual(['dndnode_1']);
  });

  it('an unknown mode does not move', () => {
    expect(resolveAutofocusTarget({ mode: 7, changes: { changed: true, newlyCompletedNodeIds: [] },
      currentPath: path({}) })).toEqual([]);
  });

  it('parents named on the nodes count like edges; a cycle does not hang', () => {
    const now = path({ dndnode_1: ['accessible', { parents: ['dndnode_2'] }], dndnode_2: ['accessible', { parents: ['dndnode_1'] }],
      dndnode_3: ['accessible', { parents: ['dndnode_1'] }] });
    expect(nextOpenNodeIds(now)).toEqual([]);
    const chain = path({ dndnode_1: ['accessible', { start: true }], dndnode_2: ['accessible', { parents: ['dndnode_1'] }] });
    expect(nextOpenNodeIds(chain)).toEqual(['dndnode_1']);
  });
});
