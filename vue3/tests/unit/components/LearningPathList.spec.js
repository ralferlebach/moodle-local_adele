import { mount } from '@vue/test-utils';
import LearningPathList from '../../../components/LearningPathList.vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

jest.mock('vuex', () => ({ useStore: jest.fn() }));
jest.mock('vue-router', () => ({ useRouter: jest.fn() }));
jest.mock('@kyvg/vue3-notification', () => ({ notify: jest.fn() }));

describe('LearningPathList.vue visibility toggle', () => {
  const makeStore = (lp) => ({
    // A new/copied path is NOT in the page-load editablepaths snapshot.
    state: {
      learningpaths: [lp],
      viewlearningpaths: [],
      editablepaths: {},
      view: 'assistant',
      undoNodes: [],
      contextid: 1,
      user: 2,
      learningPathID: 0,
      strings: new Proxy({}, { get: (_t, k) => String(k) }),
    },
    dispatch: jest.fn().mockResolvedValue(undefined),
    commit: jest.fn(),
  });

  const lpBase = { id: 42, name: '1 copy', description: '1', visibility: 1, image: '' };

  const doMount = () => mount(LearningPathList, {
    global: {
      stubs: { HelpingSlider: true },
      directives: { tooltip: {} },
    },
  });

  it('shows the toggle for an owned (self-created/copied) path missing from editablepaths', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'true' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('a.icon-link.position-absolute').exists()).toBe(true);
  });

  it('hides the toggle for a non-owned path missing from editablepaths', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'false' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('a.icon-link.position-absolute').exists()).toBe(false);
  });
});
