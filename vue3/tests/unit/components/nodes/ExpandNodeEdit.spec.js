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
 * Expand Node Edit.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ExpandNodeEdit from '../../../../components/nodes/ExpandNodeEdit.vue';
import { mount } from '@vue/test-utils';
import { useStore } from 'vuex';

// Mock the store
jest.mock('vuex', () => ({
  useStore: jest.fn(),
}));

describe('ExpandNodeEdit.vue', () => {
  let storeMock;

  beforeEach(() => {
    storeMock = {
      state: {
        availablecourses: [
          {
            course_node_id: [1],
            fullname: 'Test Course',
            summary: 'This is a test course',
          },
        ],
        strings: {
          LIGHT_GRAY: 'ffffff',
        },
        wwwroot: 'wwwroot',
      },
    };

    useStore.mockReturnValue(storeMock);
  });

  it('renders correctly with given props', async () => {
    const wrapper = mount(ExpandNodeEdit, {
      props: {
        data: {
          course_id: 1,
          course_node_id_description: {
            1: {
              fullname: 'Test Course Fullname',
              description: 'Test Course Description',
            },
          },
          imagepaths: {
            1: '/path/to/image.jpg',
          },
          showCard: false,
        },
        zoomstep: 1,
      },
    });
    expect(wrapper.find('.card').exists()).toBe(false);
    wrapper.setProps({ data: { ...wrapper.props().data, showCard: true } });
    await wrapper.vm.$nextTick();
    expect(wrapper.find('.card').exists()).toBe(true);
    expect(wrapper.find('.card-img').element.style.backgroundImage).toContain('/path/to/image.jpg');
    const courseName = wrapper.find('h5').text();
    expect(courseName).toBe('Test Course Fullname');
  });

  it('calls goToCourse when play button is clicked', async () => {
    const wrapper = mount(ExpandNodeEdit, {
      props: {
        data: {
          course_id: 1,
          course_node_id_description: {},
          imagepaths: {},
          showCard: true,
        },
        zoomstep: 1,
      },
    });

    window.open = jest.fn();
    await wrapper.find('.icon-link').trigger('click');
    expect(window.open).toHaveBeenCalledWith('wwwroot/course/view.php?id=1', '_blank');
  });

});