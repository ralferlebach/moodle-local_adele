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
 * store.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// core/* are Moodle AMD modules with no physical file under test; virtual-mock them
// so store.js imports resolve and we can unit-test the exported guard.
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });

import { hasParentRestrictionOnFirstNode, invalidTimedConditionLabel } from '../../store.js';

describe('hasParentRestrictionOnFirstNode (#476)', () => {
  const firstNode = (label) => ({
    id: 'dndnode_1',
    parentCourse: ['starting_node'],
    restriction: { nodes: label ? [{ id: 'condition_1', data: { label } }] : [] },
  });
  const wrap = (node) => ({ tree: { nodes: [node] } });

  it('flags a first node that carries a parent_courses restriction', () => {
    expect(hasParentRestrictionOnFirstNode(wrap(firstNode('parent_courses')))).toBe(true);
  });

  it('allows a first node with a different restriction', () => {
    expect(hasParentRestrictionOnFirstNode(wrap(firstNode('manual')))).toBe(false);
  });

  it('allows parent_courses on a non-first node (it has a predecessor)', () => {
    const child = {
      id: 'dndnode_2',
      parentCourse: ['dndnode_1'],
      restriction: { nodes: [{ id: 'c1', data: { label: 'parent_courses' } }] },
    };
    expect(hasParentRestrictionOnFirstNode(wrap(child))).toBe(false);
  });

  it('is defensive against empty / missing structures', () => {
    expect(hasParentRestrictionOnFirstNode({})).toBe(false);
    expect(hasParentRestrictionOnFirstNode(wrap(firstNode()))).toBe(false);
  });
});

describe('invalidTimedConditionLabel (#494)', () => {
  const wrap = (conditionData) => ({
    tree: { nodes: [{ id: 'n1', restriction: { nodes: [{ id: 'c1', data: conditionData }] } }] },
  });

  // 'timed' (start/end): at least one of start/end is required.
  it('flags a "timed" criterion with neither start nor end', () => {
    expect(invalidTimedConditionLabel(wrap({ label: 'timed', value: { start: null, end: '' } }))).toBe('timed');
  });

  it('allows a "timed" criterion with only a start', () => {
    expect(invalidTimedConditionLabel(wrap({ label: 'timed', value: { start: '2026-01-01T10:00', end: null } }))).toBeNull();
  });

  it('allows a "timed" criterion with only an end', () => {
    expect(invalidTimedConditionLabel(wrap({ label: 'timed', value: { start: null, end: '2026-02-01T10:00' } }))).toBeNull();
  });

  // 'timed': a set start AND end must be in the right order (#494 retest).
  it('flags a "timed" whose end is not after its start (#494)', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed', value: { start: '2026-02-01T10:00', end: '2026-01-01T10:00' },
    }))).toBe('timed_order');
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed', value: { start: '2026-01-01T10:00', end: '2026-01-01T10:00' },
    }))).toBe('timed_order');
  });

  it('allows a "timed" with start before end', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed', value: { start: '2026-01-01T10:00', end: '2026-02-01T10:00' },
    }))).toBeNull();
  });

  // 'timed_duration' field semantics (#566): selectedDuration = the AMOUNT,
  // durationValue = the UNIT select (0 = days is a legitimate choice!),
  // selectedOption = the start mode (0 = learning path is legitimate).
  it('allows a fully filled "2 days from node opening" criterion (#566)', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedOption: '1', selectedDuration: '2', durationValue: 0 },
    }))).toBeNull();
  });

  it('allows the 0-valued selects (days unit, learning-path start) (#566)', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedOption: 0, selectedDuration: '1', durationValue: 0 },
    }))).toBeNull();
  });

  it('flags a missing or non-positive amount', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedOption: '1', selectedDuration: null, durationValue: 0 },
    }))).toBe('timed_duration');
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedOption: '1', selectedDuration: '0', durationValue: 2 },
    }))).toBe('timed_duration');
  });

  it('flags a missing unit or start mode', () => {
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedOption: '1', selectedDuration: '2', durationValue: '' },
    }))).toBe('timed_duration');
    expect(invalidTimedConditionLabel(wrap({
      label: 'timed_duration',
      value: { selectedDuration: '2', durationValue: 0 },
    }))).toBe('timed_duration');
  });

  it('ignores unrelated criteria and is defensive against empty structures', () => {
    expect(invalidTimedConditionLabel(wrap({ label: 'manual' }))).toBeNull();
    expect(invalidTimedConditionLabel({})).toBeNull();
  });
});
