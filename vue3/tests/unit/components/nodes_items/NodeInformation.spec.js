import NodeInformation from '../../../../components/nodes_items/NodeInformation.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  completion_description_feedback: 'Description',
  completion_estimated_duration_feedback: 'Estimated duration',
  completion_restriction_feedback: 'Restrictions',
  completion_completion_feedback: 'Completions',
  completion_nothing_defined_feedback: 'Nothing defined',
  completion_edge_or: 'OR',
  DARK_RED: '#8b0000',
  LIGHT_GRAY: '#eeeeee',
};

const buildStore = () => createStore({ state: { strings, view: 'student' } });

// Minimal completion object; individual tests override the information lists.
const baseData = (overrides = {}) => ({
  node_id: 'n1',
  description: overrides.description,
  estimate_duration: overrides.estimate_duration,
  animations: { seencompletion: true },
  completion: {
    restrictioncriteria: {},
    feedback: {
      status: 'accessible',
      restriction: {
        before: null,
        information: overrides.restrictionInformation,
      },
      completion: {
        before: null,
        inbetween: null,
        information: overrides.completionInformation,
      },
    },
  },
});

const factory = (dataOverrides) =>
  mount(NodeInformation, {
    global: { plugins: [buildStore()] },
    props: {
      data: baseData(dataOverrides),
      parentnode: {},
      status: 'b',
    },
  });

describe('NodeInformation.vue', () => {
  it('renders the info toggle icon and opens the card on click', async () => {
    const wrapper = factory();
    expect(wrapper.find('.additional-card').exists()).toBe(false);
    await wrapper.find('.information').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);
  });

  it('renders each restriction information entry as a list item', async () => {
    const wrapper = factory({
      restrictionInformation: ['Finish course A', 'Reach date X'],
    });
    await wrapper.find('.information').trigger('click');
    const text = wrapper.find('.additional-card').text();
    expect(text).toContain('Finish course A');
    expect(text).toContain('Reach date X');
  });

  it('skips empty-string information entries', async () => {
    const wrapper = factory({
      restrictionInformation: ['Real entry', ''],
    });
    await wrapper.find('.information').trigger('click');
    const entries = wrapper
      .find('.additional-card')
      .findAll('span')
      .map((s) => s.text());
    expect(entries).toContain('Real entry');
    expect(entries).not.toContain('');
  });

  it('shows the OR separator between multiple completion entries', async () => {
    const wrapper = factory({
      completionInformation: ['Option 1', 'Option 2'],
    });
    await wrapper.find('.information').trigger('click');
    expect(wrapper.findAll('.or-separator')).toHaveLength(1);
    expect(wrapper.find('.or-separator').text()).toBe('OR');
  });

  it('renders "nothing defined" and does NOT crash when information lists are undefined', async () => {
    const wrapper = factory(); // no information arrays supplied
    await wrapper.find('.information').trigger('click');
    const text = wrapper.find('.additional-card').text();
    // Both restriction and completion sections fall back to the empty message.
    expect(text).toContain('Nothing defined');
    expect(wrapper.findAll('.or-separator')).toHaveLength(0);
  });

  it('renders description / duration list items only when provided', async () => {
    const withMeta = factory({
      description: 'A helpful description',
      estimate_duration: '2 hours',
    });
    await withMeta.find('.information').trigger('click');
    const metaText = withMeta.find('.additional-card').text();
    expect(metaText).toContain('A helpful description');
    expect(metaText).toContain('2 hours');

    const withoutMeta = factory();
    await withoutMeta.find('.information').trigger('click');
    const bareText = withoutMeta.find('.additional-card').text();
    expect(bareText).not.toContain('A helpful description');
  });
});
