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
 * Groups (#12), non-learners (#13), live markers and counters (#14, #15).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { mount, flushPromises } from '@vue/test-utils';
import { createStore } from 'vuex';
import { nextTick } from 'vue';
import GroupRanking from '../../../../components/groups/GroupRanking.vue';
import GroupSelector from '../../../../components/groups/GroupSelector.vue';
import GroupAverages from '../../../../components/groups/GroupAverages.vue';
import NonLearners from '../../../../components/groups/NonLearners.vue';
import NodeLive from '../../../../components/live/NodeLive.vue';
import { averageText, fill } from '../../../../composables/groupProgress';

const mockPush = jest.fn();
jest.mock('vue-router', () => ({ useRouter: () => ({ push: mockPush }) }));

const strings = {
  group_select_label: 'Group',
  group_all_participants: 'All participants',
  group_ranking_heading: 'Ranking of group {$a}',
  group_averages_heading: 'Group averages',
  group_average_own: 'Average of your group {$a}',
  group_other_groups: 'Other groups',
  group_average_value: '{$a->value} % ({$a->measured} of {$a->members} learners with progress)',
  group_average_nodata: 'No data ({$a} learners without progress)',
  group_you: '(you)',
  group_rank: 'Rank {$a}',
  group_progress_value: '{$a} % progress',
  group_nogroup: 'You are in no group of this activity.',
  group_error: 'The group overview could not be loaded.',
  nonlearners_show: 'Show other enrolled people',
  nonlearners_hide: 'Hide other enrolled people',
  nonlearners_heading: 'Other enrolled people',
  nonlearners_reason_nonlearner: 'Takes no part as a learner ({$a})',
  nonlearners_reason_nopath: 'No learning path - no progress',
  nonlearners_none: 'Nobody else is enrolled.',
  nonlearners_error: 'The list could not be loaded.',
  nonlearners_loading: 'Loading ...',
  live_state_active: 'active now',
  live_state_inactive: 'last seen here',
  live_marker_label: '{$a->name}, {$a->state}',
  live_more: '+{$a}',
  live_more_label: '{$a} more',
  live_counter_active: '{$a} active',
  live_counter_completed: '{$a} completed',
  live_counter_active_label: 'Learners active on {$a->node}: {$a->count}. Show names',
  live_counter_completed_label: 'Learners who completed {$a->node}: {$a->count}. Show names',
  live_people_active: 'Active on {$a}',
  live_people_completed: 'Completed {$a}',
  live_people_empty: 'Nobody.',
  live_people_prev: 'Previous',
  live_people_next: 'Next',
  live_people_page: 'Page {$a->page} of {$a->pages}',
  live_people_open: 'Open the learning path of {$a}',
  live_error: 'Live data could not be loaded.',
  modals_close: 'Close',
};

/**
 * A store with the given state and action mocks.
 *
 * @param {object} state
 * @param {object} actions
 * @returns {object}
 */
const makeStore = (state = {}, actions = {}) => createStore({
  state: { strings, domPrefix: 'adele1', groupid: -1, groupoverview: null, livesnapshot: null, livenodes: {},
    learningPathID: 7, ...state },
  mutations: {
    setGroupId(s, id) { s.groupid = Number(id); },
    setGroupOverview(s, o) { s.groupoverview = o; },
  },
  actions: {
    fetchGroupOverview: jest.fn(),
    fetchNonLearners: jest.fn(async () => []),
    fetchNodePeople: jest.fn(),
    ...actions,
  },
});

describe('texts (#12)', () => {
  it('fills {$a} and {$a->name}', () => {
    expect(fill('Rank {$a}', 3)).toBe('Rank 3');
    expect(fill('{$a->a} of {$a->b}', { a: 1, b: 2 })).toBe('1 of 2');
  });

  it('an average names its learners; a group without measured learners has no data - never 0 % or NaN', () => {
    expect(averageText({ value: 41.7, measured: 3, members: 4 }, strings)).toBe('41.7 % (3 of 4 learners with progress)');
    expect(averageText({ value: null, measured: 0, members: 2 }, strings)).toBe('No data (2 learners without progress)');
    expect(averageText({ value: null, measured: 0, members: 0 }, strings)).not.toMatch(/NaN|0 %/);
  });
});

describe('GroupRanking.vue (#12)', () => {
  const overview = {
    groupmode: 2,
    groupid: 5,
    groups: [],
    allowall: false,
    rankings: [{
      groupid: 5,
      groupname: 'Blue',
      members: [
        { rank: 1, fullname: 'Anna A', progress: 100, self: false },
        { rank: 2, fullname: 'Bert B', progress: 33.33, self: true },
        { rank: 2, fullname: 'Emil E', progress: 33.33, self: false },
        { rank: 4, fullname: 'Carl C', progress: 0, self: false },
      ],
    }],
    averages: [
      { groupid: 5, groupname: 'Blue', own: true, value: 41.7, measured: 4, members: 4 },
      { groupid: 6, groupname: 'Red', own: false, value: 50, measured: 2, members: 2 },
      { groupid: 7, groupname: 'Empty', own: false, value: null, measured: 0, members: 0 },
    ],
  };

  const mountWith = (o, failing = false) => {
    const store = makeStore({}, {
      fetchGroupOverview: jest.fn(async ({ commit }) => {
        if (failing) {
          throw new Error('nope');
        }
        commit('setGroupOverview', o);
      }),
    });
    return mount(GroupRanking, { global: { plugins: [store] } });
  };

  it('the members in the order the server ranked them, with rank, progress bar and "(you)"', async () => {
    const wrapper = mountWith(overview);
    await flushPromises();
    const rows = wrapper.findAll('[data-testid="adele-ranking-row"]');
    expect(rows.map((r) => r.find('.adele-ranking-name').text())).toEqual(['Anna A', 'Bert B (you)', 'Emil E', 'Carl C']);
    expect(rows.map((r) => r.find('.adele-ranking-rank').text())).toEqual(['Rank 1', 'Rank 2', 'Rank 2', 'Rank 4']);
    const bar = rows[1].find('[role="progressbar"]');
    expect(bar.attributes('aria-valuenow')).toBe('33.33');
    expect(bar.attributes('aria-valuetext')).toBe('33.33 % progress');
  });

  it('names are plain text: no link, no button, no router link', async () => {
    const wrapper = mountWith(overview);
    await flushPromises();
    const list = wrapper.find('.adele-ranking-list');
    expect(list.findAll('a').length).toBe(0);
    expect(list.findAll('button').length).toBe(0);
    expect(list.html()).not.toMatch(/router-link|href/);
  });

  it('the own group average sits above the own list; the other groups only as averages', async () => {
    const wrapper = mountWith(overview);
    await flushPromises();
    const section = wrapper.find('[data-testid="adele-group-ranking-5"]');
    const html = section.html();
    expect(html.indexOf('adele-own-average-5')).toBeLessThan(html.indexOf('adele-ranking-list'));
    expect(section.find('[data-testid="adele-own-average-5"]').text()).toContain('41.7 % (4 of 4');
    const others = wrapper.findAll('[data-testid^="adele-group-average-"]');
    expect(others.map((o) => o.attributes('data-testid'))).toEqual(['adele-group-average-6', 'adele-group-average-7']);
    expect(others[1].text()).toContain('No data');
  });

  it('no group: a note; "only own results": nothing; a load error is said', async () => {
    let wrapper = mountWith({ ...overview, groupmode: 1, rankings: [], averages: [], closed: true });
    await flushPromises();
    expect(wrapper.find('[data-testid="adele-ranking-row"]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('You are in no group');
    wrapper = mountWith({ ...overview, rankings: [], closed: false,
      averages: [{ groupid: 6, groupname: 'Red', own: false, value: 1, measured: 1, members: 1 }] });
    await flushPromises();
    expect(wrapper.text()).toContain('You are in no group of this activity.');
    expect(wrapper.text()).toContain('Red: 1 %');
    wrapper = mountWith(overview, true);
    await flushPromises();
    expect(wrapper.text()).toContain('The group overview could not be loaded.');
  });

  it('nothing without group mode', async () => {
    const wrapper = mountWith({ ...overview, groupmode: 0, rankings: [], averages: [] });
    await flushPromises();
    expect(wrapper.find('[data-testid="adele-group-ranking"]').exists()).toBe(false);
  });
});

describe('GroupSelector.vue and GroupAverages.vue (#12)', () => {
  const overview = { groupmode: 1, groupid: 0, allowall: true, groups: [{ id: 5, name: 'Blue' }, { id: 6, name: 'Red' }],
    rankings: [], averages: [] };

  it('offers what the server allows; a choice is committed and announced', async () => {
    const store = makeStore({ groupoverview: overview, groupid: 0 });
    const wrapper = mount(GroupSelector, { global: { plugins: [store] } });
    const options = wrapper.findAll('option');
    expect(options.map((o) => o.text())).toEqual(['All participants', 'Blue', 'Red']);
    const select = wrapper.find('select');
    expect(wrapper.find('label').attributes('for')).toBe(select.attributes('id'));
    await select.setValue('6');
    expect(store.state.groupid).toBe(6);
    expect(wrapper.emitted('changed')[0]).toEqual([6]);
  });

  it('no "all participants" where not allowed; nothing without groups', () => {
    let wrapper = mount(GroupSelector, { global: { plugins: [makeStore({ groupoverview: { ...overview, allowall: false } })] } });
    expect(wrapper.findAll('option').map((o) => o.text())).toEqual(['Blue', 'Red']);
    wrapper = mount(GroupSelector, { global: { plugins: [makeStore({ groupoverview: { ...overview, groupmode: 0 } })] } });
    expect(wrapper.find('select').exists()).toBe(false);
  });

  it('averages: own group bold and first as given, labelled by its heading', () => {
    const wrapper = mount(GroupAverages, {
      props: { averages: [
        { groupid: 5, groupname: 'Blue', own: true, value: 20, measured: 1, members: 2 },
        { groupid: 6, groupname: 'Red', own: false, value: null, measured: 0, members: 3 },
      ] },
      global: { plugins: [makeStore()] },
    });
    const section = wrapper.find('section');
    expect(wrapper.find(`#${section.attributes('aria-labelledby')}`).text()).toBe('Group averages');
    const rows = wrapper.findAll('li');
    expect(rows[0].classes()).toContain('font-weight-bold');
    expect(rows[0].text()).toBe('Blue: 20 % (1 of 2 learners with progress)');
    expect(rows[1].text()).toBe('Red: No data (3 learners without progress)');
  });
});

describe('NonLearners.vue (#13)', () => {
  it('hidden by default and nothing loaded; the toggle loads and shows the reasons apart', async () => {
    const fetchNonLearners = jest.fn(async () => [
      { userid: 3, fullname: 'Tina Tutor', reason: 'nonlearner', roles: 'Non-editing teacher' },
      { userid: 4, fullname: 'Nora Nopath', reason: 'nopath', roles: 'Student' },
    ]);
    const store = makeStore({}, { fetchNonLearners });
    const wrapper = mount(NonLearners, { global: { plugins: [store] } });
    const toggle = wrapper.find('[data-testid="adele-non-learners-toggle"]');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    expect(toggle.attributes('aria-controls')).toBeUndefined();
    expect(fetchNonLearners).not.toHaveBeenCalled();
    await toggle.trigger('click');
    await flushPromises();
    expect(fetchNonLearners).toHaveBeenCalledTimes(1);
    expect(toggle.attributes('aria-expanded')).toBe('true');
    expect(wrapper.find(`#${toggle.attributes('aria-controls')}`).exists()).toBe(true);
    const rows = wrapper.findAll('[data-testid="adele-non-learner"]');
    expect(rows.map((r) => r.text())).toEqual([
      'Tina Tutor - Takes no part as a learner (Non-editing teacher)',
      'Nora Nopath - No learning path - no progress',
    ]);
    expect(wrapper.text()).not.toMatch(/%/);
    await toggle.trigger('click');
    expect(wrapper.find('[data-testid="adele-non-learner"]').exists()).toBe(false);
  });

  it('a failed load is said; another group reloads an open list', async () => {
    const fetchNonLearners = jest.fn(async () => {
      throw new Error('refused');
    });
    const store = makeStore({}, { fetchNonLearners });
    const wrapper = mount(NonLearners, { global: { plugins: [store] } });
    await wrapper.find('button').trigger('click');
    await flushPromises();
    expect(wrapper.find('[role="status"]').text()).toBe('The list could not be loaded.');
    store.commit('setGroupId', 9);
    await flushPromises();
    expect(fetchNonLearners).toHaveBeenCalledTimes(2);
  });
});

describe('NodeLive.vue (#14, #15)', () => {
  const node = {
    nodeid: 'n1', completed: 2, active: 3, moremarkers: 1,
    markers: [
      { userid: 11, fullname: 'Ann Active', avatar: 'https://x/a.png', state: 'active', lastseen: 1 },
      { userid: 12, fullname: 'Ian Idle', avatar: 'https://x/i.png', state: 'inactive', lastseen: 0 },
    ],
  };
  const mountNode = (enabled = true, actions = {}) => {
    mockPush.mockClear();
    const store = makeStore({ livesnapshot: { enabled, nodes: [node] }, livenodes: { n1: enabled ? node : { ...node, active: null, markers: [], moremarkers: 0 } } }, actions);
    return mount(NodeLive, { props: { nodeid: 'n1', nodename: 'Node 1' }, global: { plugins: [store] }, attachTo: document.body });
  };

  it('two counters with names; markers say their state in text', () => {
    const wrapper = mountNode();
    expect(wrapper.find('[data-testid="adele-counter-active-n1"]').text()).toBe('3 active');
    expect(wrapper.find('[data-testid="adele-counter-active-n1"]').attributes('aria-label')).toBe('Learners active on Node 1: 3. Show names');
    expect(wrapper.find('[data-testid="adele-counter-completed-n1"]').text()).toBe('2 completed');
    const markers = wrapper.findAll('.adele-marker');
    expect(markers.map((m) => m.attributes('aria-label'))).toEqual(['Ann Active, active now', 'Ian Idle, last seen here']);
    expect(markers[1].classes()).toContain('adele-marker-inactive');
    expect(markers[1].find('.sr-only').text()).toBe('last seen here');
    expect(markers[0].find('img').attributes('alt')).toBe('');
    expect(wrapper.find('[data-testid="adele-marker-more-n1"]').text()).toBe('+1');
    wrapper.unmount();
  });

  it('switched off: no active counter, no markers; the completed counter stays', () => {
    const wrapper = mountNode(false);
    expect(wrapper.find('[data-testid="adele-counter-active-n1"]').exists()).toBe(false);
    expect(wrapper.find('.adele-marker').exists()).toBe(false);
    expect(wrapper.find('[data-testid="adele-counter-completed-n1"]').exists()).toBe(true);
    wrapper.unmount();
  });

  it('a counter opens a page of names (aria-expanded, focus), pages on, Escape closes and gives the focus back', async () => {
    const fetchNodePeople = jest.fn(async (ctx, { page }) => ({
      total: 23, perpage: 20, page,
      people: page === 0 ? [{ userid: 11, fullname: 'Ann Active' }, { userid: 13, fullname: 'Bo B' }] : [{ userid: 14, fullname: 'Cy C' }],
    }));
    const wrapper = mountNode(true, { fetchNodePeople });
    const counter = wrapper.find('[data-testid="adele-counter-completed-n1"]');
    await counter.trigger('click');
    await flushPromises();
    expect(fetchNodePeople).toHaveBeenCalledWith(expect.anything(), { nodeid: 'n1', kind: 'completed', page: 0 });
    expect(counter.attributes('aria-expanded')).toBe('true');
    const popover = wrapper.find('[data-testid="adele-live-popover-n1"]');
    expect(popover.attributes('id')).toBe(counter.attributes('aria-controls'));
    expect(popover.attributes('role')).toBe('dialog');
    expect(document.activeElement.textContent.trim()).toBe('Ann Active');
    expect(popover.text()).toContain('Page 1 of 2');
    await popover.findAll('button').find((b) => b.text() === 'Next').trigger('click');
    await flushPromises();
    expect(fetchNodePeople).toHaveBeenLastCalledWith(expect.anything(), { nodeid: 'n1', kind: 'completed', page: 1 });
    await wrapper.find('[data-testid="adele-live-popover-n1"]').trigger('keydown', { key: 'Escape' });
    await nextTick();
    await nextTick();
    expect(wrapper.find('[data-testid="adele-live-popover-n1"]').exists()).toBe(false);
    expect(counter.attributes('aria-expanded')).toBe('false');
    expect(document.activeElement).toBe(counter.element);
    wrapper.unmount();
  });

  it('a name and a marker open the learner centred on this node', async () => {
    const fetchNodePeople = jest.fn(async () => ({ total: 1, perpage: 20, page: 0, people: [{ userid: 13, fullname: 'Bo B' }] }));
    const wrapper = mountNode(true, { fetchNodePeople });
    await wrapper.find('[data-testid="adele-counter-active-n1"]').trigger('click');
    await flushPromises();
    await wrapper.find('[data-testid="adele-live-person"]').trigger('click');
    expect(mockPush).toHaveBeenCalledWith({ name: 'userDetails', params: { learningpathId: 7, userId: 13 }, query: { node: 'n1' } });
    await wrapper.find('[data-testid="adele-marker-n1-12"]').trigger('click');
    expect(mockPush).toHaveBeenLastCalledWith({ name: 'userDetails', params: { learningpathId: 7, userId: 12 }, query: { node: 'n1' } });
    wrapper.unmount();
  });

  it('an error loading the names is said, not an empty list', async () => {
    const wrapper = mountNode(true, { fetchNodePeople: jest.fn(async () => { throw new Error('x'); }) });
    await wrapper.find('[data-testid="adele-counter-completed-n1"]').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-testid="adele-live-popover-n1"] [role="status"]').text()).toBe('Live data could not be loaded.');
    wrapper.unmount();
  });
});
