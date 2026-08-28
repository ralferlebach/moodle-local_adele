import timed_duration from '../../../../components/restriction/conditions/timed_duration.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

jest.mock('vuex', () => ({ useStore: jest.fn() }));

describe('timed_duration prefill probe', () => {
  beforeEach(() => {
    useStore.mockReturnValue({ state: { strings: new Proxy({}, { get: (_t, k) => String(k) }) } });
  });

  it('prefills the amount from modelValue (stored criterion)', async () => {
    const wrapper = mount(timed_duration, {
      props: {
        modelValue: { selectedOption: '1', durationValue: '0', selectedDuration: '2' },
        restriction: { node_id: 'condition_1' },
      },
    });
    await wrapper.vm.$nextTick();
    const input = wrapper.find('#restriction-condition_1-duration');
    expect(input.element.value).toBe('2');
  });
});
