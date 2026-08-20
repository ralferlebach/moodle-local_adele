import ConditionNode from '../../../../components/nodes/ConditionNode.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('@vue-flow/core', () => ({
  Handle: { template: '<div />' },
  Position: { Left: 'left', Right: 'right', Top: 'top', Bottom: 'bottom' },
  useVueFlow: () => ({
    findNode: jest.fn(),
    nodes: { value: [] },
    edges: { value: [] },
    addNodes: jest.fn(),
    addEdges: jest.fn(),
    removeNodes: jest.fn(),
  }),
}));

describe('ConditionNode prefill probe (stored timed_duration)', () => {
  beforeEach(() => {
    useStore.mockReturnValue({ state: { strings: new Proxy({}, { get: (_t, k) => String(k) }), view: 'manager' } });
  });

  it('prefills the duration amount through the full chain', async () => {
    const stored = {
      id: 140,
      name: 'Bearbeitungszeitraum',
      label: 'timed_duration',
      visibility: true,
      node_id: 'condition_1',
      value: { selectedOption: '1', durationValue: '0', selectedDuration: '2' },
    };
    const wrapper = mount(ConditionNode, {
      props: { data: stored, type: 'Restriction', learningpath: { json: { tree: { nodes: [] } } } },
    });
    await wrapper.vm.$nextTick();
    await wrapper.vm.$nextTick();
    const input = wrapper.find('#restriction-condition_1-duration');
    expect(input.exists()).toBe(true);
    expect(input.element.value).toBe('2');
  });
});
