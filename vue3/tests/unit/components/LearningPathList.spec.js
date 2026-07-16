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

  it('shows the duplicate button for an assistant on their own path (#471)', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'true' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.fa-copy').exists()).toBe(true);
  });

  it('hides the duplicate button for an assistant on a path they do not own (#471)', async () => {
    useStore.mockReturnValue(makeStore({ ...lpBase, isowner: 'false' }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.fa-copy').exists()).toBe(false);
  });

  it('shows the owner (name + email) on the tile (#487)', async () => {
    useStore.mockReturnValue(makeStore({
      ...lpBase,
      isowner: 'false',
      owner: { name: 'Olive Owner', email: 'olive@example.com' },
    }));
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    const text = wrapper.text();
    expect(text).toContain('Olive Owner');
    expect(text).toContain('olive@example.com');
  });

  it('shows the owner on a view-only tile too (#487)', async () => {
    const store = makeStore(lpBase);
    store.state.learningpaths = [];
    store.state.viewlearningpaths = [
      { ...lpBase, visibility: 1, owner: { name: 'Vic Viewer', email: 'vic@example.com' } },
    ];
    useStore.mockReturnValue(store);
    useRouter.mockReturnValue({ push: jest.fn() });
    const wrapper = doMount();
    await wrapper.vm.$nextTick();
    const text = wrapper.text();
    expect(text).toContain('Vic Viewer');
    expect(text).toContain('vic@example.com');
  });
});
