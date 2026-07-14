// core/* are Moodle AMD modules with no physical file under test; virtual-mock them
// so store.js imports resolve and we can unit-test the exported guard.
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });

import { hasParentRestrictionOnFirstNode } from '../../store.js';

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
