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
 * User List.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import UserList from '../../../../components/user_view/UserList.vue';
import ProgressBar from '../../../../components/nodes_items/ProgressBar.vue';
import tooltipDirective from '../../../../directives/tooltip';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  user_view_user_list_hide: 'Hide',
  user_view_user_list_show: 'Show',
  user_view_user_list: 'User list',
  user_view_id: 'ID',
  user_view_firstname: 'First name',
  user_view_lastname: 'Last name',
  user_view_progress: 'Progress',
  user_view_nodes: 'Nodes',
  user_view_nodes_tooltip: '{$a->completed} of {$a->total} nodes completed in total',
  userlistranking: 'Rank',
  nodes_items_no_progress: 'No progress',
};

// Progress carries the best-route numbers (#461 revisit): route_completed /
// route_total drive the x/y cell, completed_nodes / total_nodes the tooltip.
const relations = [
  { id: 3, firstname: 'Cara', lastname: 'Zeal', rank: 2,
    progress: { progress: 40, completed_nodes: 2, route_completed: 2, route_total: 5, total_nodes: 6 } },
  { id: 1, firstname: 'Alan', lastname: 'Young', rank: 1,
    progress: { progress: 90, completed_nodes: 5, route_completed: 9, route_total: 10, total_nodes: 12 } },
  // Bea's raw count (6) is HIGHER than Cara's/Alan's route numerators on
  // purpose: sorting the column by the old raw key would order her last,
  // sorting by the best-route numerator orders her first.
  { id: 2, firstname: 'Bea', lastname: 'Xu', rank: 3,
    progress: { progress: 50, completed_nodes: 6, route_completed: 1, route_total: 2, total_nodes: 12 } },
];

const buildStore = (overrides = {}) =>
  createStore({
    state: {
      strings,
      view: overrides.view || 'teacher',
      userlist: overrides.userlist,
      user: overrides.user,
      learningPathID: 7,
      lpuserpathrelations: overrides.relations || relations,
      lpuserpathrelation: overrides.lpuserpathrelation || { user_id: null, image: '' },
      focususer: overrides.focususer,
      userlistcollapsed: false,
    },
    mutations: {
      setUserlistCollapsed(state, collapsed) {
        state.userlistcollapsed = collapsed;
      },
    },
  });

const factory = (storeOverrides) =>
  mount(UserList, {
    global: {
      plugins: [buildStore(storeOverrides)],
      components: { ProgressBar },
      directives: { tooltip: tooltipDirective },
      stubs: {
        'router-link': {
          props: ['to'],
          template: '<a class="stub-link"><slot /></a>',
        },
        // transition-group wraps the rows; without a passthrough stub the
        // <tr> children are not rendered under jsdom.
        transition: false,
        'transition-group': {
          template: '<tbody><slot /></tbody>',
        },
      },
    },
  });

describe('UserList.vue', () => {
  it('renders one row per relation with the ID column for teachers', () => {
    const wrapper = factory();
    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(relations.length);
    // Teacher view keeps the ID header/column.
    expect(wrapper.find('#adele-userlist-header-row').text()).toContain('ID');
  });

  it('hides the ID column for the student view', () => {
    const wrapper = factory({ view: 'student', userlist: 1 });
    const headerText = wrapper.find('#adele-userlist-header-row').text();
    expect(headerText).not.toContain('ID');
    // No id-link cells rendered.
    expect(wrapper.findAll('.stub-link')).toHaveLength(0);
  });

  it('toggles the table visibility and button label', async () => {
    const wrapper = factory();
    const button = wrapper.find('#adele-userlist-toggle');
    expect(wrapper.vm.isTableVisible).toBe(true);
    expect(button.text()).toContain('Hide');

    await button.trigger('click');
    expect(wrapper.vm.isTableVisible).toBe(false);
    expect(button.text()).toContain('Show');
  });

  it('marks the list collapsed in the store so the graph can grow when hidden (#480)', async () => {
    const wrapper = factory();
    const store = wrapper.vm.$store;
    expect(store.state.userlistcollapsed).toBe(false); // starts expanded
    await wrapper.find('#adele-userlist-toggle').trigger('click');
    expect(store.state.userlistcollapsed).toBe(true); // hidden -> collapsed
    await wrapper.find('#adele-userlist-toggle').trigger('click');
    expect(store.state.userlistcollapsed).toBe(false); // shown -> expanded
  });

  it('sorts ascending then descending when a column header is clicked', async () => {
    const wrapper = factory();
    const firstNameHeader = wrapper.findAll('th')[1];

    await firstNameHeader.trigger('click');
    let names = wrapper.findAll('tbody tr td:nth-child(2)').map((td) => td.text());
    expect(names).toEqual(['Alan', 'Bea', 'Cara']);
    expect(firstNameHeader.classes()).toContain('ascending');

    await firstNameHeader.trigger('click');
    names = wrapper.findAll('tbody tr td:nth-child(2)').map((td) => td.text());
    expect(names).toEqual(['Cara', 'Bea', 'Alan']);
    expect(firstNameHeader.classes()).toContain('descending');
  });

  it('highlights the last-opened participant so the teacher returns to that position (#481)', async () => {
    // route params are strings; focususer stores the opened row's id.
    const wrapper = factory({ focususer: '2' });
    await wrapper.vm.$nextTick(); // focusEntry is set in onMounted -> flush the re-render
    const highlighted = wrapper.findAll('tbody tr.highlighted-row');
    expect(highlighted).toHaveLength(1);
    expect(highlighted[0].text()).toContain('Bea'); // relation id 2 -> Bea
  });

  it('highlights no row for a teacher who has not opened anyone yet (#481)', () => {
    const wrapper = factory(); // focususer undefined
    expect(wrapper.findAll('tbody tr.highlighted-row')).toHaveLength(0);
  });

  it('filters to the current user only when student view and userlist is 2', async () => {
    // Userlist and user reach the store as STRINGS (HTML getAttribute in
    // main.js) while the WS returns numeric ids; the old strict === filter
    // never fired on real data, so students saw the whole list (#569).
    const wrapper = factory({
      view: 'student',
      userlist: '2',
      user: '2',
      lpuserpathrelation: { user_id: 2 },
    });
    const store = wrapper.vm.$store;
    store.state.lpuserpathrelations = [...relations];
    await wrapper.vm.$nextTick();

    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(1);
    // Student view drops the ID column, so first cell is the first name.
    expect(rows[0].find('td').text()).toBe('Bea'); // user id 2 -> Bea
  });

  it('shows the best route as x/y in the completed-nodes cell (#461)', () => {
    const wrapper = factory();
    // Teacher view: td5 is the completed-nodes cell. Bea's best route is 1/2.
    const beaRow = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('Bea'));
    expect(beaRow.findAll('td')[4].text()).toBe('1/2');
    // Cara's best route 2/5 - independent of her raw count of 2/6.
    const caraRow = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('Cara'));
    expect(caraRow.findAll('td')[4].text()).toBe('2/5');
  });

  it('reveals the raw totals in a tooltip on the completed-nodes cell (#461)', async () => {
    jest.useFakeTimers();
    const raf = global.requestAnimationFrame;
    // The directive positions itself inside requestAnimationFrame; run it synchronously.
    global.requestAnimationFrame = (cb) => cb();
    try {
      const wrapper = factory();
      const beaRow = wrapper.findAll('tbody tr').find((tr) => tr.text().includes('Bea'));
      await beaRow.findAll('td')[4].trigger('mouseenter');
      jest.advanceTimersByTime(300);
      const tooltip = document.querySelector('.custom-tooltip');
      expect(tooltip).not.toBeNull();
      expect(tooltip.textContent).toBe('6 of 12 nodes completed in total');
    } finally {
      jest.useRealTimers();
      global.requestAnimationFrame = raf;
      document.querySelectorAll('.custom-tooltip').forEach((el) => el.remove());
    }
  });

  it('sorts the completed-nodes column by the best-route numerator (#461)', async () => {
    const wrapper = factory();
    const nodesHeader = wrapper.findAll('th')[4];
    await nodesHeader.trigger('click');
    // Ascending by route_completed: Bea (1), Cara (2), Alan (9).
    const names = wrapper.findAll('tbody tr td:nth-child(2)').map((td) => td.text());
    expect(names).toEqual(['Bea', 'Cara', 'Alan']);
  });

  it('applies the own-results filter to the initial render too (#569)', () => {
    // Relations already in the store when the component mounts must not flash
    // the full list before the watcher fires.
    const wrapper = factory({
      view: 'student',
      userlist: '2',
      user: '1',
      lpuserpathrelation: { user_id: 1 },
    });
    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(1);
    expect(rows[0].find('td').text()).toBe('Alan'); // user id 1 -> Alan
  });
});
