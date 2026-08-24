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
 * #570 reproduction: the lock->play "attention" animation on a freshly
 * accessible node. An accessible node must show a steady play symbol; today
 * it starts as a LOCK and flips lock->play->lock in an endless loop until
 * clicked - and the #462 tooltip flips along with it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CustomNodeEdit from '../../../../components/nodes/CustomNodeEdit.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

const strings = {
  nodes_collection: 'Collection',
  go_to_course: 'Go to course',
  node_not_accessible: 'Not accessible',
};

const buildStore = () =>
  createStore({
    state: {
      strings,
      view: 'student',
      wwwroot: 'https://example.test',
      availablecourses: [],
      lpuserpathrelation: { image: '' },
      feedbacksettings: { show_feedback: true, show_info: true },
    },
  });

// An ACCESSIBLE node the user has NOT seen yet - the animation trigger (#570).
const factory = () =>
  mount(CustomNodeEdit, {
    global: {
      plugins: [buildStore()],
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
      data: {
        node_id: 'node-1',
        fullname: 'My node',
        course_node_id: [10],
        progress: 50,
        animations: { seenrestriction: false, seencompletion: true },
        completion: {
          feedback: {
            status: 'accessible',
            status_restriction: 'before',
            completion: { before: null, inbetween: null, after: null, after_all: null },
          },
          restrictioncriteria: {},
          singlerestrictionnode: [],
        },
      },
      learningpath: {
        json: {
          tree: {
            nodes: [
              { id: 'node-1', parentCourse: ['starting_node'], restriction: { nodes: [] } },
            ],
          },
        },
      },
      zoomstep: 1,
    },
  });

describe('issue570: accessible-node play button animation', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });
  afterEach(() => {
    jest.useRealTimers();
  });

  // #570: an accessible node shows a steady play symbol from the first paint
  // on and never presents itself as locked. (Before the fix it started as a
  // lock and flipped lock->play->lock - tooltip included - until clicked.)
  it('shows a steady play symbol for an accessible unseen node (#570)', async () => {
    const wrapper = factory();
    await wrapper.vm.$nextTick();
    expect(wrapper.vm.iconClass).toBe('fa-play');
    expect(wrapper.vm.courseLinkTitle).toBe('Go to course');

    // And it stays a play button through every former animation checkpoint.
    for (const step of [750, 2000, 2000, 750, 2000, 2000]) {
      jest.advanceTimersByTime(step);
      await wrapper.vm.$nextTick();
      expect(wrapper.vm.iconClass).toBe('fa-play');
      expect(wrapper.vm.courseLinkTitle).toBe('Go to course');
    }
  });
});
