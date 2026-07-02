import ProgressBar from '../../../../components/nodes_items/ProgressBar.vue';
import * as nodeColors from '../../../../config/nodeColors';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const buildStore = () =>
  createStore({
    state: {
      strings: {
        nodes_items_no_progress: 'No progress yet',
      },
    },
  });

const factory = (props) =>
  mount(ProgressBar, {
    global: { plugins: [buildStore()] },
    props,
  });

describe('ProgressBar.vue', () => {
  it('shows the percentage label when progress is greater than zero', () => {
    const wrapper = factory({ progress: 42, status: 'b' });
    const label = wrapper.find('.progress-label');
    expect(label.text()).toBe('42%');
  });

  it('shows the "no progress" string when progress is zero', () => {
    const wrapper = factory({ progress: 0, status: 'b' });
    const label = wrapper.find('.progress-label');
    expect(label.text()).toBe('No progress yet');
  });

  it('sets the bar width from the progress prop', () => {
    const wrapper = factory({ progress: 73, status: 'c' });
    const bar = wrapper.find('.progress-bar');
    expect(bar.attributes('style')).toContain('width: 73%');
  });

  it('maps each status to its configured progress-bar colour', () => {
    const cases = {
      '0': nodeColors.progressBarColorCase0,
      a1: nodeColors.progressBarColorCaseA1,
      a2: nodeColors.progressBarColorCaseA2,
      b: nodeColors.progressBarColorCaseB,
      c: nodeColors.progressBarColorCaseC,
      d: nodeColors.progressBarColorCaseD,
      e: nodeColors.progressBarColorCaseE,
      f: nodeColors.progressBarColorCaseF,
    };
    Object.entries(cases).forEach(([status, expected]) => {
      const wrapper = factory({ progress: 10, status });
      expect(wrapper.vm.progressStyle.backgroundColor).toBe(expected);
    });
  });

  it('falls back to the default colour for an unknown status', () => {
    const wrapper = factory({ progress: 10, status: 'not-a-status' });
    expect(wrapper.vm.progressStyle.backgroundColor).toBe(
      nodeColors.progressBarColorCaseDefault,
    );
  });
});
