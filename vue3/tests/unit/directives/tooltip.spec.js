import { mount } from '@vue/test-utils';
import tooltipDirective from '../../../directives/tooltip';

// The custom v-tooltip directive builds a black box on document.body and fills it with
// binding.value. If that value is empty it must NOT render - otherwise a bare black
// square appears on hover. It should render (with the text) when a value is present.
describe('v-tooltip directive', () => {
  const factory = (val) =>
    mount(
      { template: '<button v-tooltip="val">x</button>', data: () => ({ val }) },
      { global: { directives: { tooltip: tooltipDirective } } }
    );

  beforeEach(() => {
    jest.useFakeTimers();
    // The directive positions itself inside requestAnimationFrame; run it synchronously.
    global.requestAnimationFrame = (cb) => cb();
  });

  afterEach(() => {
    jest.useRealTimers();
    document.querySelectorAll('.custom-tooltip').forEach((el) => el.remove());
  });

  it('does not render a tooltip (no black square) when the value is empty (#481-tooltip)', async () => {
    const wrapper = factory('');
    await wrapper.find('button').trigger('mouseenter');
    jest.advanceTimersByTime(300);
    expect(document.querySelector('.custom-tooltip')).toBeNull();
  });

  it('does not render a tooltip when the value is undefined', async () => {
    const wrapper = factory(undefined);
    await wrapper.find('button').trigger('mouseenter');
    jest.advanceTimersByTime(300);
    expect(document.querySelector('.custom-tooltip')).toBeNull();
  });

  it('renders a tooltip carrying the text when the value is present', async () => {
    const wrapper = factory('Duplicate');
    await wrapper.find('button').trigger('mouseenter');
    jest.advanceTimersByTime(300);
    const tip = document.querySelector('.custom-tooltip');
    expect(tip).not.toBeNull();
    expect(tip.textContent).toBe('Duplicate');
  });
});
