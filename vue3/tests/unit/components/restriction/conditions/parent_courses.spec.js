import parent_courses from '../../../../../components/restriction/conditions/parent_courses.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  restriction_select_number: 'Select number',
  restriction_choose_number: 'Choose a number',
  restriction_no_select_number: 'No parent courses available',
};

const buildStore = (nodes, currentNodeId) =>
  createStore({
    state: {
      strings,
      node: { node_id: currentNodeId },
      learningpath: { json: { tree: { nodes } } },
    },
  });

const factory = (nodes, currentNodeId, restriction = {}) =>
  mount(parent_courses, {
    global: { plugins: [buildStore(nodes, currentNodeId)] },
    props: {
      restriction: { node_id: 'r1', description: 'Parent courses', ...restriction },
    },
  });

describe('parent_courses.vue (restriction)', () => {
  it('shows the "no parent courses" message for a starting node', () => {
    const wrapper = factory(
      [{ id: 'n1', parentCourse: ['starting_node'] }],
      'n1',
    );
    expect(wrapper.text()).toContain('No parent courses available');
    expect(wrapper.find('select').exists()).toBe(false);
  });

  it('renders a number selector sized to the number of parent courses', async () => {
    const wrapper = factory(
      [{ id: 'n1', parentCourse: ['c1', 'c2', 'c3'] }],
      'n1',
    );
    await wrapper.vm.$nextTick();
    expect(wrapper.find('select').exists()).toBe(true);
    // 3 parent courses -> option values 1..3 plus the disabled placeholder.
    const numberOptions = wrapper
      .findAll('option')
      .filter((o) => o.attributes('disabled') === undefined);
    expect(numberOptions).toHaveLength(3);
    expect(wrapper.text()).toContain('/ 3');
  });

  it('emits the condition value shape on mount and on change', async () => {
    const wrapper = factory(
      [{ id: 'n1', parentCourse: ['c1', 'c2'] }],
      'n1',
    );
    // onMounted emits an initial value.
    let events = wrapper.emitted('update:modelValue');
    expect(events).toBeTruthy();
    expect(events[0][0]).toEqual(
      expect.objectContaining({ min_courses: expect.any(Number), courses_id: ['c1', 'c2'] }),
    );

    await wrapper.vm.$nextTick();
    await wrapper.find('select').setValue(2);
    events = wrapper.emitted('update:modelValue');
    expect(events[events.length - 1][0].min_courses).toBe(2);
  });

  it('initialises min_courses from an existing restriction value', () => {
    const wrapper = factory(
      [{ id: 'n1', parentCourse: ['c1', 'c2', 'c3'] }],
      'n1',
      { value: { min_courses: 2 } },
    );
    expect(wrapper.vm.data.min_courses).toBe(2);
  });
});
