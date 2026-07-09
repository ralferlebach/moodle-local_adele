import { mount } from '@vue/test-utils';
import feedbackNodeRestriction from '../../../../components/nodes/feedbackNodeRestriction.vue';
import { useStore } from 'vuex';

// useVueFlow().findNode is used by renderFeedback to read the attached condition's default text.
const mockFindNode = jest.fn();
jest.mock('@vue-flow/core', () => ({
  useVueFlow: () => ({ findNode: (...args) => mockFindNode(...args) }),
  Handle: { name: 'Handle', render: () => null },
  Position: { Bottom: 'bottom', Top: 'top', Left: 'left', Right: 'right' },
}));

jest.mock('vuex', () => ({ useStore: jest.fn() }));

describe('feedbackNodeRestriction.vue', () => {
  const DEFAULT_TEXT = 'Der Zugang wird manuell gewährt';
  const CUSTOM_TEXT = 'toll tafel wischen';

  beforeEach(() => {
    useStore.mockReturnValue({
      state: { strings: { nodes_feedback_use_default: 'Use default', course_condition_concatination_and: ' und ' } },
    });
    mockFindNode.mockReset();
    // The single restriction condition this feedback is attached to (its default description).
    mockFindNode.mockReturnValue({
      data: { visibility: true, description_before: DEFAULT_TEXT, information: 'info' },
      childCondition: [],
    });
  });

  const mountWith = (dataOverrides = {}) => mount(feedbackNodeRestriction, {
    props: {
      data: {
        childCondition: 'c1',
        color: '#4a90d9',
        visibility: true,
        feedback_before_checkmark: false,
        feedback_before: CUSTOM_TEXT,
        feedback_after: '',
        information: '',
        ...dataOverrides,
      },
      learningpath: { json: { tree: { nodes: [] } } },
      visibility: true,
    },
  });

  it('preserves the custom feedback when "use default" is unchecked (re-render must not overwrite it)', async () => {
    const wrapper = mountWith();
    // Any re-render (e.g. toggling node visibility) re-runs renderFeedback('before').
    await wrapper.setProps({ visibility: false });

    const emitted = wrapper.emitted('updateFeedback');
    expect(emitted).toBeTruthy();
    const payload = emitted[emitted.length - 1][0];
    expect(payload.feedback_before).toBe(CUSTOM_TEXT);
  });

  it('renders the default description when "use default" is checked', async () => {
    const wrapper = mountWith({ feedback_before_checkmark: true });
    await wrapper.setProps({ visibility: false });

    const emitted = wrapper.emitted('updateFeedback');
    const payload = emitted[emitted.length - 1][0];
    expect(payload.feedback_before).toBe(DEFAULT_TEXT);
  });
});
