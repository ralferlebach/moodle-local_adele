// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Node Feedback Area.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import NodeFeedbackArea from '../../../../components/nodes_items/NodeFeedbackArea.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('NodeFeedbackArea.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        view: 'admin', // default view for the test
        strings: { edit_feedback: 'Edit feedback' },
      },
    });

    wrapper = mount(NodeFeedbackArea, {
      global: {
        plugins: [store],
      },
      props: {
        data: {
          feedback: 'Initial feedback',
        },
      },
    });
  });

  it('renders the component correctly', () => {
    expect(wrapper.exists()).toBe(true);
    const commentIcon = wrapper.find('.fa-comment');
    expect(commentIcon.exists()).toBe(true);
  });

  it('toggles feedback area when clicked', async () => {
    const cardContainer = wrapper.find('.card-container');
    const feedbackContainerBeforeClick = wrapper.find('.feedback-container');
    expect(feedbackContainerBeforeClick.exists()).toBe(false);

    // Trigger click event to show feedback area
    await cardContainer.trigger('click');
    const feedbackContainerAfterClick = wrapper.find('.feedback-container');
    expect(feedbackContainerAfterClick.exists()).toBe(true);

    // Click again to hide feedback area
    await cardContainer.trigger('click');
    const feedbackContainerHidden = wrapper.find('.feedback-container');
    expect(feedbackContainerHidden.exists()).toBe(false);
  });

  it('renders a textarea if the view is not student or teacher', async () => {
    const cardContainer = wrapper.find('.card-container');
    await cardContainer.trigger('click');

    const textarea = wrapper.find('textarea');
    expect(textarea.exists()).toBe(true);
    expect(textarea.element.value).toBe('Initial feedback');
  });

  it('renders a paragraph if the view is student or teacher', async () => {
    // Update the store to set the view to 'student'
    store.replaceState({
      ...store.state,
      view: 'student',
    });
    await wrapper.vm.$nextTick();
    // Trigger the click event to show the feedback area
    const cardContainer = wrapper.find('.card-container');
    await cardContainer.trigger('click');

    // Assert that a paragraph is rendered instead of a textarea
    const paragraph = wrapper.find('p');
    expect(paragraph.exists()).toBe(true);
    expect(paragraph.text()).toBe('Initial feedback');
  });

  it('cleans up event listeners on unmount', () => {
    const removeEventListenerSpy = jest.spyOn(document, 'removeEventListener');

    wrapper.unmount();

    expect(removeEventListenerSpy).toHaveBeenCalledWith('click', expect.any(Function));
    removeEventListenerSpy.mockRestore();
  });

});