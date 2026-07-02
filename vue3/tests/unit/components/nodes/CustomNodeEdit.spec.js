import CustomNodeEdit from '../../../../components/nodes/CustomNodeEdit.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  nodes_collection: 'Collection',
  go_to_course: 'Go to course',
  node_not_accessible: 'Not accessible',
};

// Build a learningpath whose single node carries the given restriction nodes.
const learningpathWith = (restrictionNodes) => ({
  json: {
    tree: {
      nodes: [
        {
          id: 'node-1',
          parentCourse: ['starting_node'],
          restriction: { nodes: restrictionNodes },
        },
      ],
    },
  },
});

const buildStore = (view = 'student') =>
  createStore({
    state: {
      strings,
      view,
      wwwroot: 'https://example.test',
      availablecourses: [],
      lpuserpathrelation: { image: '' },
    },
  });

// A node data object that is "accessible" so the play icon path is taken.
const nodeData = (overrides = {}) => ({
  node_id: 'node-1',
  fullname: 'My node',
  course_node_id: [10],
  progress: 50,
  animations: { seenrestriction: true, seencompletion: true },
  completion: {
    feedback: {
      status: 'accessible',
      status_restriction: 'before',
      completion: {
        before: null,
        inbetween: null,
        after: null,
        after_all: null,
      },
    },
    restrictioncriteria: {},
    singlerestrictionnode: [],
  },
  ...overrides,
});

const factory = (restrictionNodes, dataOverrides, view) =>
  mount(CustomNodeEdit, {
    global: {
      plugins: [buildStore(view)],
      stubs: {
        Handle: true,
        NodeInformation: true,
        ProgressBar: true,
        UserInformation: true,
        MasterConditions: true,
        RestrictionOutPutItem: true,
        CompletionOutPutItem: true,
      },
    },
    props: {
      data: nodeData(dataOverrides),
      learningpath: learningpathWith(restrictionNodes),
      zoomstep: 1,
    },
  });

describe('CustomNodeEdit.vue', () => {
  it('shows the play icon (fa-play) for an accessible node', async () => {
    const wrapper = factory([]);
    // iconClass is flipped to fa-play synchronously in onMounted; let the DOM
    // re-render before asserting on it.
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.iconClass).toBe('fa-play');
    expect(wrapper.find('i.fa-play').exists()).toBe(true);
  });

  it('keeps the locked icon (fa-lock) for a closed / not-accessible node', () => {
    const wrapper = factory([], {
      completion: {
        feedback: {
          status: 'closed',
          status_restriction: 'before',
          completion: { before: null, inbetween: null, after: null, after_all: null },
        },
        restrictioncriteria: {},
        singlerestrictionnode: [],
      },
    });
    // triggerAnimation does not flip to fa-play for a closed node.
    expect(wrapper.vm.iconClass).toBe('fa-lock');
  });

  // Regression for #463: the timed "ring" affordance must appear ONLY for real
  // timed restrictions (labels 'timed' / 'timed_duration'), not for any other
  // restriction node.
  it('shows the timed ring when a "timed" restriction node exists', async () => {
    const wrapper = factory([
      { data: { label: 'timed', value: { some: 'date' } } },
    ]);
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.hasTimedCondition).toBe(true);
    expect(wrapper.find('.icon-with-ring').exists()).toBe(true);
  });

  it('shows the timed ring for a "timed_duration" restriction node', () => {
    const wrapper = factory([
      { data: { label: 'timed_duration', value: {} } },
    ]);
    expect(wrapper.vm.hasTimedCondition).toBe(true);
  });

  it('does NOT show the timed ring for a non-timed restriction node', () => {
    const wrapper = factory([
      { data: { label: 'parent_node_completed', value: {} } },
    ]);
    expect(wrapper.vm.hasTimedCondition).toBe(false);
    expect(wrapper.find('.icon-with-ring').exists()).toBe(false);
  });

  it('uses the "go to course" title when the node is reachable (fa-play)', async () => {
    const wrapper = factory([]);
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.courseLinkTitle).toBe('Go to course');
    expect(wrapper.find('.icon-link').attributes('title')).toBe('Go to course');
  });

  it('uses the "not accessible" title when the node is locked (fa-lock)', () => {
    const wrapper = factory([], {
      completion: {
        feedback: {
          status: 'closed',
          status_restriction: 'before',
          completion: { before: null, inbetween: null, after: null, after_all: null },
        },
        restrictioncriteria: {},
        singlerestrictionnode: [],
      },
    });
    expect(wrapper.vm.courseLinkTitle).toBe('Not accessible');
  });

  // Regression for #453 / #425: closing the feedback must lower the node's
  // z-index again. zoomOnParent({open:false}) clears the inline z-index it set.
  it('raises this node\'s z-index on open and clears it on close', () => {
    const wrapper = factory([]);
    // Simulate the vue-flow node wrapper around the component root.
    const flowNode = document.createElement('div');
    flowNode.classList.add('vue-flow__node');
    flowNode.appendChild(wrapper.vm.$el);

    wrapper.vm.zoomOnParent({ open: true });
    expect(flowNode.style.zIndex).toBe('1001');

    wrapper.vm.zoomOnParent({ open: false });
    expect(flowNode.style.zIndex).toBe('');
  });

  it('re-emits zoomOnParent to the parent', () => {
    const wrapper = factory([]);
    wrapper.vm.zoomOnParent({ open: true });
    expect(wrapper.emitted('zoomOnParent')).toBeTruthy();
  });
});
