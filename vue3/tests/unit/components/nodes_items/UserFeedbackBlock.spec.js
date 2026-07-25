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
 * User Feedback Block.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import UserFeedbackBlock from '../../../../components/nodes_items/UserFeedbackBlock.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('UserFeedbackBlock.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          nodes_feedback_completion_before: 'Completion Before Feedback',
          nodes_feedback_completion_higher: 'Higher Completion Feedback',
          course_condition_concatination_or: 'or',
        },
      },
    });

    wrapper = mount(UserFeedbackBlock, {
      global: {
        plugins: [store],
      },
      props: {
        data: ['First feedback', 'Second feedback', 'Third feedback'],
        title: 'completion_higher',
      }
    });
  });

  it('renders the component when data is provided', () => {
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.exists()).toBe(true);
  });

  it('displays the correct title based on "completion_higher"', async () => {
    // Check that the correct title is displayed
    const feedbackTitle = wrapper.find('.feedback-title');
    expect(feedbackTitle.text()).toBe('Higher Completion Feedback');
  });

  it('renders all feedback items with correct capitalization and concatenation', async () => {
    await wrapper.setProps({ data: [] });
    await wrapper.vm.$nextTick();
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.text()).toBe('');

    // Assert that no feedback items are rendered
    const feedbackItems = wrapper.findAll('.feedback-item');
    expect(feedbackItems).toHaveLength(0);
  });

  it('renders all feedback items with correct capitalization and concatenation', async () => {
    await wrapper.setProps({ props: {
      data: ['First feedback', 'Second feedback', 'Third feedback'],
      title: 'restriction_completion_before',
    } });
    await wrapper.vm.$nextTick();
    const feedbackList = wrapper.find('.feedback-list');
    expect(feedbackList.exists()).toBe(true);

    // Assert that no feedback items are rendered
    const feedbackItems = wrapper.findAll('.feedback-item');
    expect(feedbackItems).toHaveLength(3);
  });

});