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
 * The teachers' overview polls the live snapshot (#14 AK2-L6, DoD2-L3).
 *
 * Fake timers: one snapshot on mount, then one per server interval; never two
 * in parallel; paused while the page is hidden; stopped when the view goes;
 * the graph is not mounted again.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';
import TeacherView from '../../../../components/teacher_view/TeacherView.vue';

// The graph and its helpers are not under test here (and pull in modules
// that only build in the bundle): replaced by a stub that counts its mounts.
const mockGraphMounts = { count: 0 };
jest.mock('../../../../components/flowchart/LearningPath.vue', () => ({
  name: 'LearningPath',
  template: '<div class="graph-stub" />',
  mounted() { mockGraphMounts.count++; },
}));
jest.mock('../../../../components/charthelper/textInputs.vue', () => ({ template: '<div />' }));
jest.mock('../../../../components/user_view/UserList.vue', () => ({ template: '<div />' }));
const graphMounts = mockGraphMounts;

/**
 * A store whose live snapshot answers when the test says so.
 *
 * @param {object} control { pending: Array of resolvers, calls: number }
 * @returns {object}
 */
const makeStore = (control) => createStore({
  state: { strings: { live_error: 'Live data could not be loaded.' }, groupoverview: null, livesnapshot: null, livenodes: {}, groupid: -1 },
  mutations: {
    setLiveSnapshot(s, snap) { s.livesnapshot = snap; },
    setGroupOverview(s, o) { s.groupoverview = o; },
  },
  actions: {
    fetchLearningpath: jest.fn(async () => ({ id: 1, json: { tree: { nodes: [] } } })),
    fetchGroupOverview: jest.fn(async () => null),
    fetchUserPathRelations: jest.fn(async () => []),
    fetchLiveSnapshot: () => new Promise((resolve) => {
      control.calls++;
      control.pending.push(resolve);
    }),
  },
});

const snapshot = (n) => ({ enabled: true, window: 300, pollinterval: 30, servertime: n, nodes: [] });

describe('TeacherView live polling (#14)', () => {
  let visibility;

  beforeEach(() => {
    jest.useFakeTimers();
    graphMounts.count = 0;
    visibility = 'visible';
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => visibility });
    document.hasFocus = () => true;
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  const mountView = (control) => mount(TeacherView, {
    global: {
      plugins: [makeStore(control)],
      stubs: {
        TextInputs: true, UserList: true,
        GroupSelector: true, GroupAverages: true, NonLearners: true,
      },
    },
  });

  it('one snapshot on mount, then one per interval, never two at a time, the graph mounted once', async () => {
    const control = { pending: [], calls: 0 };
    const wrapper = mountView(control);
    await flushPromises();
    expect(control.calls).toBe(1);
    control.pending.shift()(snapshot(1));
    await flushPromises();
    expect(wrapper.vm.$store.state.livesnapshot.servertime).toBe(1);

    jest.advanceTimersByTime(29000);
    await flushPromises();
    expect(control.calls).toBe(1);
    jest.advanceTimersByTime(1000);
    await flushPromises();
    expect(control.calls).toBe(2);
    // The answer is late: no second request meanwhile.
    jest.advanceTimersByTime(30000);
    await flushPromises();
    expect(control.calls).toBe(2);
    control.pending.shift()(snapshot(2));
    await flushPromises();
    expect(wrapper.vm.$store.state.livesnapshot.servertime).toBe(2);
    jest.advanceTimersByTime(30000);
    await flushPromises();
    expect(control.calls).toBe(3);
    expect(graphMounts.count).toBe(1);
    wrapper.unmount();
  });

  it('paused while hidden; stopped and cleared when the view goes', async () => {
    const control = { pending: [], calls: 0 };
    const wrapper = mountView(control);
    await flushPromises();
    control.pending.shift()(snapshot(1));
    await flushPromises();
    visibility = 'hidden';
    document.dispatchEvent(new Event('visibilitychange'));
    jest.advanceTimersByTime(120000);
    await flushPromises();
    expect(control.calls).toBe(1);
    const store = wrapper.vm.$store;
    wrapper.unmount();
    expect(store.state.livesnapshot).toBeNull();
    visibility = 'visible';
    document.dispatchEvent(new Event('visibilitychange'));
    jest.advanceTimersByTime(120000);
    await flushPromises();
    expect(control.calls).toBe(1);
  });

  it('no live data (refused): the view works without and does not poll', async () => {
    const control = { pending: [], calls: 0 };
    const wrapper = mountView(control);
    await flushPromises();
    control.pending.shift();
    // Simulate a refusal by making the first request fail.
    wrapper.unmount();
    const failing = { pending: [], calls: 0 };
    const store = makeStore(failing);
    store._actions.fetchLiveSnapshot = [async () => { failing.calls++; throw new Error('refused'); }];
    const view = mount(TeacherView, { global: { plugins: [store], stubs: { TextInputs: true,
      UserList: true, GroupSelector: true, GroupAverages: true, NonLearners: true } } });
    await flushPromises();
    jest.advanceTimersByTime(120000);
    await flushPromises();
    expect(failing.calls).toBe(1);
    expect(store.state.livesnapshot).toBeNull();
    view.unmount();
  });
});
