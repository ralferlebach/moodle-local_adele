import UserList from '../../../../components/user_view/UserList.vue';
import ProgressBar from '../../../../components/nodes_items/ProgressBar.vue';
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
  userlistranking: 'Rank',
  nodes_items_no_progress: 'No progress',
};

const relations = [
  { id: 3, firstname: 'Cara', lastname: 'Zeal', rank: 2, progress: { progress: 40, completed_nodes: 2 } },
  { id: 1, firstname: 'Alan', lastname: 'Young', rank: 1, progress: { progress: 90, completed_nodes: 5 } },
  { id: 2, firstname: 'Bea', lastname: 'Xu', rank: 3, progress: { progress: 10, completed_nodes: 1 } },
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
    },
  });

const factory = (storeOverrides) =>
  mount(UserList, {
    global: {
      plugins: [buildStore(storeOverrides)],
      components: { ProgressBar },
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

  it('filters to the current user only when student view and userlist === 2', async () => {
    const wrapper = factory({
      view: 'student',
      userlist: 2,
      user: 2,
      lpuserpathrelation: { user_id: 2 },
    });
    // Initially the un-filtered slice is shown; the own-only filter runs in the
    // watcher, which fires only on a reference change of lpuserpathrelations.
    const store = wrapper.vm.$store;
    store.state.lpuserpathrelations = [...relations];
    await wrapper.vm.$nextTick();

    const rows = wrapper.findAll('tbody tr');
    expect(rows).toHaveLength(1);
    // Student view drops the ID column, so first cell is the first name.
    expect(rows[0].find('td').text()).toBe('Bea'); // user id 2 -> Bea
  });
});
