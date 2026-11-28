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
 * Answers for a group no longer chosen are dropped (found 2026-11-26 in
 * E2E G5: a late answer for an earlier group overwrote the list of the group
 * chosen last).
 */
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn() } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn(), exception: jest.fn() } }), { virtual: true });

import moodleAjax from 'core/ajax';
import { createAppStore } from '../../store.js';

/** Calls whose answers the test releases itself. */
function deferredCalls() {
  const pending = [];
  moodleAjax.call.mockImplementation(() => [new Promise((resolve) => pending.push(resolve))]);
  return pending;
}

describe('store - answers for a group no longer chosen', () => {
  afterEach(() => jest.clearAllMocks());

  it('participant list: the late answer for group 5 does not overwrite group 4', async () => {
    const pending = deferredCalls();
    const store = createAppStore();
    store.commit('setGroupId', 5);
    const first = store.dispatch('fetchUserPathRelations');
    store.commit('setGroupId', 4);
    const second = store.dispatch('fetchUserPathRelations');
    pending[1]([{ id: 4, lastname: 'Four' }]);
    await second;
    pending[0]([{ id: 5, lastname: 'Five' }]);
    await first;
    expect(store.state.lpuserpathrelations).toEqual([{ id: 4, lastname: 'Four' }]);
    expect(moodleAjax.call.mock.calls.map((c) => c[0][0].args.groupid)).toEqual([5, 4]);
  });

  it('group overview: the same', async () => {
    const pending = deferredCalls();
    const store = createAppStore();
    store.commit('setGroupId', 5);
    const first = store.dispatch('fetchGroupOverview');
    store.commit('setGroupId', 4);
    const second = store.dispatch('fetchGroupOverview');
    pending[1]({ groupid: 4, groups: [] });
    await second;
    pending[0]({ groupid: 5, groups: [] });
    await first;
    expect(store.state.groupoverview).toMatchObject({ groupid: 4 });
  });

  it('the overview resolving the default group is no choice: the first list is kept (18 E2E red, 2026-11-26)', async () => {
    const pending = deferredCalls();
    const store = createAppStore();
    expect(store.state.groupid).toBe(-1);
    const relations = store.dispatch('fetchUserPathRelations');
    const overview = store.dispatch('fetchGroupOverview');
    pending[1]({ groupid: 0, groups: [] });
    await overview;
    expect(store.state.groupid).toBe(0);
    pending[0]([{ id: 7, lastname: 'Own row' }]);
    await relations;
    expect(store.state.lpuserpathrelations).toEqual([{ id: 7, lastname: 'Own row' }]);
  });

  it('an answer for the group still chosen is kept', async () => {
    const pending = deferredCalls();
    const store = createAppStore();
    store.commit('setGroupId', 4);
    const call = store.dispatch('fetchUserPathRelations');
    pending[0]([{ id: 1 }]);
    await call;
    expect(store.state.lpuserpathrelations).toEqual([{ id: 1 }]);
  });
});
