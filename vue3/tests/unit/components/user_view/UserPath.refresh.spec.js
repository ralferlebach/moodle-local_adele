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
 * The data flow of the open path view (mod_adele #26, #27, #32): one load,
 * the parent's data used as given, refresh by the site's settings, focus on
 * a real change only, the person's view kept otherwise, nothing left behind
 * on unmount.
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { shallowMount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { useStore } from 'vuex';

jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn(() => [Promise.resolve({})]) } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn() } }), { virtual: true });
jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('vue-router', () => ({
  useRoute: () => ({ params: { learningpathId: 7, userId: 9 } }),
  useRouter: () => ({ go: jest.fn() }),
}));
jest.mock('@kyvg/vue3-notification', () => ({ notify: jest.fn() }));
// The cards and controls of the canvas are not under test here (and are not
// loaded, so their code does not count as untested coverage).
jest.mock('../../../../components/edges/TransitionEdge.vue', () => ({ name: 'TransitionEdge', render: () => null }));
jest.mock('../../../../components/nodes/CustomNodeEdit.vue', () => ({ name: 'CustomNodeEdit', render: () => null }));
jest.mock('../../../../components/nodes/CustomStagNodeEdit.vue', () => ({ name: 'CustomStagNodeEdit', render: () => null }));
jest.mock('../../../../components/nodes/ExpandNodeEdit.vue', () => ({ name: 'ExpandNodeEdit', render: () => null }));
jest.mock('../../../../components/nodes/ModuleNode.vue', () => ({ name: 'ModuleNode', render: () => null }));
jest.mock('../../../../components/user_view/UserControls.vue', () => ({ name: 'UserControls', render: () => null }));
jest.mock('../../../../components/flowchart/LearningPathOutline.vue', () => ({ name: 'LearningPathOutline', render: () => null }));
jest.mock('../../../../components/nodes_items/ExpandedCourses.vue', () => ({ name: 'ExpandedCourses', render: () => null }));
// Navigation helpers of the canvas, not under test here.
jest.mock('../../../../composables/flowHelper/onWheel', () => ({ __esModule: true, default: jest.fn() }));
jest.mock('../../../../composables/flowHelper/onNodeClick', () => ({ __esModule: true, default: jest.fn() }));
jest.mock('../../../../composables/nodesHelper/drawModules', () => ({ __esModule: true, default: jest.fn() }));

const mockFlow = {
  fitView: jest.fn(), setViewport: jest.fn(), findNode: jest.fn(() => ({ dimensions: { width: 100 } })),
  addNodes: jest.fn(), removeNodes: jest.fn(), zoomTo: jest.fn(), setCenter: jest.fn(),
  viewport: { value: { x: 1, y: 2, zoom: 0.5 } },
  onMoveStart: jest.fn(), onMoveEnd: jest.fn(),
};
jest.mock('@vue-flow/core', () => {
  const { ref } = require('vue');
  return {
    VueFlow: { name: 'VueFlow', template: '<div class="vf"><slot /></div>' },
    Panel: { name: 'Panel', template: '<div><slot /></div>' },
    useVueFlow: () => ({ ...mockFlow, viewport: ref({ x: 1, y: 2, zoom: 0.5 }) }),
  };
});

// eslint-disable-next-line import/first
import UserPath from '../../../../components/user_view/UserPath.vue';

/**
 * A stored path state.
 *
 * @param {object} statuses id => status
 * @returns {object}
 */
const path = (statuses) => ({
  id: 1,
  user_id: 5,
  json: {
    tree: {
      nodes: Object.entries(statuses).map(([id, status], i) => ({
        id, type: 'custom', parentCourse: i === 0 ? ['starting_node'] : ['dndnode_1'], position: { x: 0, y: i * 100 },
        data: { node_id: id, completion: { feedback: { status } } },
      })),
      edges: [{ id: 'e1', source: 'dndnode_1', target: 'dndnode_2' }],
    },
  },
});

describe('UserPath.vue - refresh and auto-focus', () => {
  let store;
  let fetched;

  /**
   * Mount the view.
   *
   * @param {object} opts view, settings, parent data.
   * @returns {object}
   */
  const mountView = async ({ view = 'student', settings = null, parent = null, answers = [] } = {}) => {
    fetched = [];
    const queue = [...answers];
    store = {
      state: {
        view, user: 5, learningPathID: 7, availablecourses: [], strings: { path_refresh_failed: 'x' },
        lpuserpathrelation: { user_id: 99, id: 1 }, pathviewsettings: settings,
      },
      dispatch: jest.fn(async (action, params) => {
        if (action === 'fetchUserPathRelation') {
          fetched.push(params);
          return queue.length ? queue.shift() : path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' });
        }
        return null;
      }),
      commit: jest.fn(),
    };
    useStore.mockReturnValue(store);
    const wrapper = shallowMount(UserPath, {
      props: parent ? { userlearningpathparent: parent } : {},
      global: { stubs: { VueFlow: true, Panel: true }, directives: { tooltip: {} } },
    });
    await flushPromises();
    return wrapper;
  };

  const flushPromises = async () => {
    for (let i = 0; i < 10; i++) {
      await nextTick();
      await Promise.resolve();
    }
  };

  beforeEach(() => {
    jest.useFakeTimers();
    // The page is visible and focused (jsdom says neither by itself).
    jest.spyOn(document, 'hasFocus').mockReturnValue(true);
    Object.values(mockFlow).forEach((f) => typeof f === 'function' && f.mockClear && f.mockClear());
  });
  afterEach(() => {
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('uses the path the parent loaded: no load of its own (the prop name mismatch, #32)', async () => {
    const wrapper = await mountView({ parent: path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' }),
      settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    expect(fetched).toEqual([]);
    wrapper.unmount();
  });

  it('without a parent: exactly one load', async () => {
    const wrapper = await mountView({ settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300,
      autofocusenabled: 1, autofocusteachermode: 1, autofocuslearnermode: 2 } });
    expect(fetched).toHaveLength(1);
    jest.advanceTimersByTime(60000);
    await flushPromises();
    expect(fetched).toHaveLength(1);
    wrapper.unmount();
  });

  it('mode 2: a poll that brings a change focuses the next open nodes (learner default)', async () => {
    const before = path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' });
    const after = path({ dndnode_1: 'completed', dndnode_2: 'accessible' });
    const wrapper = await mountView({
      parent: before,
      settings: { pollingmode: 2, pollinginterval: 1000, pollingdebounce: 0, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 },
      answers: [after, after],
    });
    mockFlow.fitView.mockClear();
    jest.advanceTimersByTime(1000);
    await flushPromises();
    expect(fetched).toHaveLength(1);
    jest.advanceTimersByTime(400);
    await flushPromises();
    jest.advanceTimersByTime(400);
    await flushPromises();
    const focus = mockFlow.fitView.mock.calls.find((c) => c[0] && c[0].nodes);
    expect(focus && focus[0].nodes).toEqual(['dndnode_2']);

    // The same state again: no further move.
    mockFlow.fitView.mockClear();
    mockFlow.setViewport.mockClear();
    jest.advanceTimersByTime(1000);
    await flushPromises();
    jest.advanceTimersByTime(800);
    await flushPromises();
    expect(fetched).toHaveLength(2);
    expect(mockFlow.fitView).not.toHaveBeenCalled();
    expect(mockFlow.setViewport).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('auto-focus off: a change keeps the person\'s view', async () => {
    const before = path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' });
    const after = path({ dndnode_1: 'completed', dndnode_2: 'accessible' });
    const wrapper = await mountView({
      parent: before,
      settings: { pollingmode: 2, pollinginterval: 1000, pollingdebounce: 0, autofocusenabled: 0,
        autofocusteachermode: 1, autofocuslearnermode: 2 },
      answers: [after],
    });
    mockFlow.fitView.mockClear();
    mockFlow.setViewport.mockClear();
    jest.advanceTimersByTime(1000);
    await flushPromises();
    jest.advanceTimersByTime(400);
    await flushPromises();
    expect(mockFlow.fitView.mock.calls.some((c) => c[0] && c[0].nodes)).toBe(false);
    expect(mockFlow.setViewport).toHaveBeenCalledWith({ x: 1, y: 2, zoom: 0.5 }, { duration: 0 });
    wrapper.unmount();
  });

  it('unmount: no listener, no timer, no further request', async () => {
    const add = jest.spyOn(window, 'addEventListener');
    const remove = jest.spyOn(window, 'removeEventListener');
    const wrapper = await mountView({ parent: path({ dndnode_1: 'accessible' }),
      settings: { pollingmode: 3, pollinginterval: 1000, pollingdebounce: 0, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    const added = add.mock.calls.filter((c) => ['focus', 'blur', 'pageshow'].includes(c[0])).length;
    expect(added).toBe(3);
    wrapper.unmount();
    const removed = remove.mock.calls.filter((c) => ['focus', 'blur', 'pageshow'].includes(c[0])).length;
    expect(removed).toBe(3);
    jest.advanceTimersByTime(60000);
    await flushPromises();
    expect(fetched).toHaveLength(0);
  });

  it('the person at work: a dialog, a field in the canvas or a move keeps a refresh from moving the view', async () => {
    const wrapper = await mountView({ parent: path({ dndnode_1: 'accessible' }),
      settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    const vm = wrapper.vm;
    expect(vm.interactionBusy()).toBe(false);
    const modal = document.createElement('div');
    modal.className = 'modal show';
    document.body.appendChild(modal);
    expect(vm.interactionBusy()).toBe(true);
    modal.remove();
    const moveStart = mockFlow.onMoveStart.mock.calls[0][0];
    const moveEnd = mockFlow.onMoveEnd.mock.calls[0][0];
    moveStart();
    expect(vm.interactionBusy()).toBe(true);
    moveEnd();
    expect(vm.interactionBusy()).toBe(false);
    wrapper.unmount();
  });

  it('the role: a teacher view, somebody else\'s path, one\'s own path', async () => {
    const wrapper = await mountView({ view: 'teacher', parent: path({ dndnode_1: 'accessible' }),
      settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    expect(wrapper.vm.focusRole()).toBe('teacher');
    expect(wrapper.vm.refreshParams()).toEqual({ learningpathId: 7, userId: 9 });
    store.state.view = 'student';
    expect(wrapper.vm.focusRole()).toBe('learner');
    expect(wrapper.vm.refreshParams()).toEqual({ learningpathId: 7, userId: 5 });
    wrapper.unmount();
  });

  it('a return to the page brings the list of the others along; a poll does not', async () => {
    const wrapper = await mountView({ parent: path({ dndnode_1: 'accessible' }),
      settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    store.dispatch.mockClear();
    await wrapper.vm.onRefreshResult(path({ dndnode_1: 'accessible' }), 'poll');
    expect(store.dispatch).not.toHaveBeenCalledWith('fetchUserPathRelations');
    await wrapper.vm.onRefreshResult(path({ dndnode_1: 'accessible' }), 'focus');
    expect(store.dispatch).toHaveBeenCalledWith('fetchUserPathRelations');
    wrapper.unmount();
  });

  it('graph or list; resize refits only outside the student view', async () => {
    const wrapper = await mountView({ view: 'teacher', parent: path({ dndnode_1: 'accessible' }),
      settings: { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
        autofocusteachermode: 1, autofocuslearnermode: 2 } });
    wrapper.vm.toggleOutline();
    expect(wrapper.vm.showOutline).toBe(true);
    expect(store.commit).toHaveBeenCalledWith('announce', undefined);
    mockFlow.fitView.mockClear();
    wrapper.vm.onContainerResize();
    jest.advanceTimersByTime(200);
    expect(mockFlow.fitView).toHaveBeenCalled();
    store.state.view = 'student';
    mockFlow.fitView.mockClear();
    wrapper.vm.onContainerResize();
    jest.advanceTimersByTime(200);
    expect(mockFlow.fitView).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  const settings = { pollingmode: 0, pollinginterval: 3000, pollingdebounce: 300, autofocusenabled: 1,
    autofocusteachermode: 1, autofocuslearnermode: 2 };
  const focusCalls = () => mockFlow.fitView.mock.calls.filter((c) => c[0] && c[0].nodes).map((c) => c[0].nodes);

  it('gone before its delayed step: a refresh moves nothing after unmount (re-audit 2026100736, B)', async () => {
    const before = path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible' });
    const wrapper = await mountView({ parent: before, settings });
    jest.advanceTimersByTime(1000);
    await flushPromises();
    mockFlow.fitView.mockClear();
    mockFlow.setViewport.mockClear();
    const drawn = require('../../../../composables/nodesHelper/drawModules').default;
    drawn.mockClear();
    await wrapper.vm.onRefreshResult(path({ dndnode_1: 'completed', dndnode_2: 'accessible' }), 'poll');
    wrapper.unmount();
    jest.advanceTimersByTime(10000);
    await flushPromises();
    expect(mockFlow.fitView).not.toHaveBeenCalled();
    expect(mockFlow.setViewport).not.toHaveBeenCalled();
    expect(drawn).not.toHaveBeenCalled();
    expect(jest.getTimerCount()).toBe(0);
  });

  it('two refreshes in quick succession: only the later one moves the view, once', async () => {
    const wrapper = await mountView({
      parent: path({ dndnode_1: 'accessible', dndnode_2: 'not_accessible', dndnode_3: 'not_accessible' }),
      settings,
    });
    jest.advanceTimersByTime(1000);
    await flushPromises();
    mockFlow.fitView.mockClear();
    await wrapper.vm.onRefreshResult(path({ dndnode_1: 'completed', dndnode_2: 'accessible', dndnode_3: 'not_accessible' }), 'poll');
    await wrapper.vm.onRefreshResult(path({ dndnode_1: 'completed', dndnode_2: 'completed', dndnode_3: 'accessible' }), 'poll');
    jest.advanceTimersByTime(400);
    await flushPromises();
    jest.advanceTimersByTime(400);
    await flushPromises();
    expect(focusCalls()).toEqual([['dndnode_3']]);
    wrapper.unmount();
  });
});
