import manual_check from '../../../../../components/restriction/conditions/manual_check.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// The restriction manual_check is intentionally minimal: it just renders the
// restriction description text (unlike the completion variant, which has a
// textarea). This guards that contract.
describe('manual_check.vue (restriction)', () => {
  const factory = (restriction) =>
    mount(manual_check, {
      global: { plugins: [createStore({ state: { strings: {} } })] },
      props: { modelValue: {}, restriction },
    });

  it('renders the restriction description', () => {
    const wrapper = factory({ description: 'Teacher confirms manually' });
    expect(wrapper.find('.form-check').text()).toBe('Teacher confirms manually');
  });

  it('renders without crashing when the description is empty', () => {
    const wrapper = factory({ description: '' });
    expect(wrapper.find('.form-check').exists()).toBe(true);
    expect(wrapper.find('.form-check').text()).toBe('');
  });
});
