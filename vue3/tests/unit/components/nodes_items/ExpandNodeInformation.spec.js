import ExpandNodeInformation from '../../../../components/nodes_items/ExpandNodeInformation.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

// Mock the store
jest.mock('vuex', () => ({
  useStore: jest.fn(),
}));

describe('ExpandNodeInformation.vue', () => {
  let storeMock;

  beforeEach(() => {
    storeMock = {
      state: {
        strings: {
          LIGHT_GRAY: '#f0f0f0',
          nodes_no_description: 'No description available',
        },
      },
    };
    useStore.mockReturnValue(storeMock);
  })

  it('colours the info badge by course completion status', async () => {
    const courses = [{ id: 1, description: 'This is a test course description.' }];

    // Not completed -> the "not finished" status colour (courseNodeNotFinishedColor, #db8d31).
    const notDone = mount(ExpandNodeInformation, { props: { courses } });
    expect(notDone.find('.icon-container').exists()).toBe(true);
    expect(notDone.find('.information').element.style.backgroundColor).toBe('rgb(219, 141, 49)');

    // Completed -> the "finished" status colour (courseNodeFinishedColor, #63aa43).
    const done = mount(ExpandNodeInformation, {
      props: {
        courses,
        data: { course_id: 1, completion: { completioncriteria: { course_completed: { completed: { 1: true } } } } },
      },
    });
    expect(done.find('.information').element.style.backgroundColor).toBe('rgb(99, 170, 67)');
  });

  it('toggles the additional card visibility on click', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // Initially, the card should not be visible
    expect(wrapper.find('.additional-card').exists()).toBe(false);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(false);
  });

  it('renders the course description if available', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].description);
  });

  it('renders the course summary if description is not available', async () => {
    const courses = [
      {
        id: 1,
        summary: 'This is a test course summary.',
      },
    ];
    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].summary);
  });

  it('renders a fallback text if neither description nor summary is available', async () => {
    const courses = [
      {
        id: 1,
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });
    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('renders the first course description if multiple courses are provided', async () => {
    const courses = [
      { id: 1, description: 'First course description.' },
      { id: 2, description: 'Second course description.' },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').element.innerHTML).toContain(courses[0].description);
  });

  it('renders the fallback text if courses array is empty', async () => {
    const courses = [];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('handles null or undefined description/summary gracefully', async () => {
    const courses = [
      { id: 1, description: null },
      { id: 2, summary: undefined },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.list-group-text').text()).toBe(storeMock.state.strings.nodes_no_description);
  });

  it('correctly toggles card visibility using toggleCard method', async () => {
    const courses = [{ id: 1, description: 'Test description.' }];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    expect(wrapper.vm.showCard).toBe(false);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.vm.showCard).toBe(true);
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.vm.showCard).toBe(false);
  });

  it('renders additional-card correctly based on showCard', async () => {
    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // Initially, the additional card should not be visible
    expect(wrapper.find('.additional-card').exists()).toBe(false);

    // Trigger click to show the card
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(true);

    // Trigger click again to hide the card
    await wrapper.find('.icon-container').trigger('click');
    expect(wrapper.find('.additional-card').exists()).toBe(false);
  });

  it('renders the node description defined in the editor, even without a course description (#484)', async () => {
    const wrapper = mount(ExpandNodeInformation, {
      props: {
        courses: [{ id: 1 }], // no course description or summary
        data: { description: 'Node description from the learning-path editor' },
      },
    });

    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    const text = wrapper.find('.list-group-text').text();
    expect(text).toContain('Node description from the learning-path editor');
    // Must NOT fall back to the "no course description" hint when a node description exists.
    expect(text).not.toBe(storeMock.state.strings.nodes_no_description);
  });

  it('prioritises the node description over the course description (#484)', async () => {
    const wrapper = mount(ExpandNodeInformation, {
      props: {
        courses: [{ id: 1, description: 'Course level description' }],
        data: { description: 'Node level description' },
      },
    });

    await wrapper.find('.icon-container').trigger('click');
    await wrapper.vm.$nextTick();
    const text = wrapper.find('.list-group-text').text();
    expect(text).toContain('Node level description');
    expect(text).not.toContain('Course level description');
  });

  it('uses the store LIGHT_GRAY for the popup card background', async () => {
    storeMock.state.strings.LIGHT_GRAY = '#aaaaaa';

    const courses = [
      {
        id: 1,
        description: 'This is a test course description.',
      },
    ];

    const wrapper = mount(ExpandNodeInformation, {
      props: { courses },
    });

    // LIGHT_GRAY drives the popup card (.additional-card), not the status badge; reveal it first.
    await wrapper.find('.icon-container').trigger('click');
    const card = wrapper.find('.additional-card');
    expect(card.element.style.backgroundColor).toBe('rgb(170, 170, 170)'); // #aaaaaa
  });

});