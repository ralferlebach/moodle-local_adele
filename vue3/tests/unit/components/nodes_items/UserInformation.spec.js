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
 * User Information.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import UserInformation from '../../../../components/nodes_items/UserInformation.vue';
import UserFeedbackBlock from '../../../../components/nodes_items/UserFeedbackBlock.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// Mock the store

describe('UserInformation.vue', () => {
  let wrapper;
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          node_access_restriction_before : 'Sie haben keinen Zugang zu diesem Kurs/diesem Stapel. Eine Freischaltung erfolgt, wenn:',
          node_access_restriction_inbetween : 'Der Kurs/Der Stapel ist freigeschaltet:',
          node_access_restriction_after : 'Der Kurs/Der Stapel kann nicht (mehr) von Ihnen freigeschaltet werden.',
          node_access_closed: 'Access closed',
          node_access_nothing_defined: 'Nothing defined',
          course_description_before_condition_course_completed: 'Before condition description',
          node_access_completed: 'Node status completed',
          node_access_not_accessible: 'Node status not accessible',
          course_description_inbetween_condition_course_completed: 'Inbetween condition description',
          course_description_after_condition_course_completed: 'After condition description',
        },
      },
    });

    wrapper = mount(UserInformation, {
      global: {
        plugins: [store],
        components: {
          UserFeedbackBlock
        }
      },
      props: {
        data: {
          node_id: '123',
          completion: {
            feedback: {
              status: 'closed',
              restriction: {
                before: { message: 'Some restriction data' }, // Wrap the string in an object
              },
              completion: {
                before: { message: 'Completion before data' }, // Wrap the string in an object
                inbetween: { message: 'Completion in-between data' }, // Wrap the string in an object
                after: { message: 'Completion after data' }, // Wrap the string in an object
                higher: { message: 'Completion higher data' }, // Wrap the string in an object
              }
            }
          }
        },
        mobile: false
      }
    });
  });

  it('renders the feedback toggle button correctly', () => {
    const toggleButton = wrapper.find('.toggle-button');
    expect(toggleButton.exists()).toBe(true);
  });

  it('toggles feedback area when toggle button is clicked', async () => {
    const toggleButton = wrapper.find('.toggle-button');

    expect(wrapper.vm.showFeedbackarea).toBe(false);
    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(true);

    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(false);
  });

  // Reproduces issue #453: opening the feedback ("Sprachblase") on one node
  // permanently raised that node's z-index because closing emitted no signal
  // for the parent (CustomNodeEdit.zoomOnParent) to lower it again, leaving the
  // raised node overlapping and blocking clicks on the other nodes in a stack.
  // The parent can only reset the z-index if it is told the feedback closed,
  // so the component must emit focusChanged with an explicit open state.
  it('emits focusChanged with the open state on open AND on close', async () => {
    const toggleButton = wrapper.find('.toggle-button');

    // Open: parent is told to raise this node.
    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(true);

    // Close: parent MUST be told so it can lower the node and stop blocking
    // clicks on the neighbouring nodes' info / feedback controls.
    await toggleButton.trigger('click');
    expect(wrapper.vm.showFeedbackarea).toBe(false);

    const events = wrapper.emitted('focusChanged');
    expect(events).toBeTruthy();

    const openEvents = events.filter((e) => e[0] && e[0].open === true);
    const closeEvents = events.filter((e) => e[0] && e[0].open === false);
    expect(openEvents.length).toBeGreaterThan(0);
    expect(closeEvents.length).toBeGreaterThan(0);
  });

  it('renders the feedback area with correct styling when shown', async () => {
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const feedbackArea = wrapper.find('.selectable');
    expect(feedbackArea.exists()).toBe(true);
    expect(feedbackArea.attributes('style')).toContain('position: absolute');
  });

  it('shows restriction after string', async () => {
    await wrapper.setProps({
      data: {
        node_id: '123',
        completion: {
          feedback: {
            status: 'not_accessible', // Updated feedback status
            status_completion: 'before',
            status_restriction: 'after',
            restriction: {
              before_active: { message: 'Some restriction data active' },
              inbetween: { message: 'Some restriction data inbetween' },
              after: { message: 'Some restriction data after' },
              before: { message: 'Some restriction data' },
            },
            completion: {
              before: { message: 'Completion before data' },
              inbetween: { message: 'Completion in-between data' },
              after: { message: 'Completion after data' },
              higher: { message: 'Completion higher data' },
            }
          }
        }
      }
    });
    await wrapper.vm.$nextTick();
    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const statusText = wrapper.find('.status-text');
    expect(statusText.text()).toContain('Der Kurs/Der Stapel kann nicht (mehr) von Ihnen freigeschaltet werden');

    // Verify that the completion feedback blocks are rendered
    const completionBeforeBlock = wrapper.findComponent(UserFeedbackBlock);
    expect(completionBeforeBlock.exists()).toBe(true);
    expect(completionBeforeBlock.props('data')).toEqual({ message: 'Completion before data' });
  });

  it('shows the before restriction', async () => {
    await wrapper.setProps({
      data: {
        node_id: '123',
        completion: {
          feedback: {
            status: 'not_accessible', // Updated feedback status
            status_completion: 'inbetween',
            status_restriction: 'before',
            restriction: {
              before_valid: { message: 'Datum xyz erreicht wird'},
              before_active: { message: 'Some restriction data active' },
              inbetween: { message: 'Some restriction data inbetween' },
              after: { message: 'Some restriction data after' },
              before: { message: 'Some restriction data' },
            },
            completion: {
              before: { message: 'Completion before data' },
              inbetween: { message: 'Completion in-between data' },
              after: { message: 'Completion after data' },
              higher: { message: 'Completion higher data' },
            }
          }
        }
      }
    });
    await wrapper.vm.$nextTick();

    const toggleButton = wrapper.find('.toggle-button');
    await toggleButton.trigger('click');

    const statusText = wrapper.find('.status-text');
    expect(statusText.text()).toContain('Sie haben keinen Zugang zu diesem Kurs/diesem Stapel. Eine Freischaltung erfolgt, wenn:');

    // Verify that the completion feedback blocks are rendered
    const completionBeforeBlock = wrapper.findComponent(UserFeedbackBlock);
    expect(completionBeforeBlock.exists()).toBe(true);
    expect(completionBeforeBlock.props('data')).toEqual(Object.values({ message: 'Some restriction data active' }));
  });
});