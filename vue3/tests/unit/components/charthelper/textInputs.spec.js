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
 * text Inputs.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import textInputs from '../../../../components/charthelper/textInputs.vue';
import { mount} from '@vue/test-utils';
import { createStore } from 'vuex';
import { nextTick } from 'vue';

jest.mock('../../../../components/charthelper/userSearch.vue', () => ({
  template: '<div></div>',
}));

jest.mock('@kyvg/vue3-notification', () => ({
  notify: jest.fn(),
}));

const mockDirectives = {
  autowidth: () => {}
};

global.FileReader = class {
  readAsDataURL() {
    // Simulate the Base64 content of the uploaded image
    this.onload({ target: { result: 'data:image/jpeg;base64,dummydata' } });
  }
};


describe('textInputs.vue', () => {
  let store;

  beforeEach(() => {
    store = createStore({
      state: {
        strings: {
          fromlearningtitel: 'Goal Title',
          fromlearningdescription: 'Goal Description',
          goalnameplaceholder: 'Enter goal name',
          goalsubjectplaceholder: 'Enter goal description',
          charthelper_no_name: 'No Name Available',
          modals_edit: 'Edit',
          image_title_save: 'Image Saved',
          image_description_save: 'Your image has been saved.',
        },
        learningpath: {
          name: 'Learning Path 1',
          description: 'Learning Path Description',
          image: '/path/to/image.jpg',
        },
        lpimages: {
          node_background_image: [
            { path: '/path/to/image1.jpg' },
            { path: '/path/to/image2.jpg' },
          ],
        },
        learningPathID: 1,
        view: 'teacher',
      },
      actions: {
        uploadNewLpImage: jest.fn(() => Promise.resolve({ filename: '/path/to/newImage.jpg' })),
        getLpEditUsers: jest.fn(),
      },
    });
  });

  it('initializes with the correct goal data from props', async () => {
    const goal = {
      name: 'Test Goal',
      description: 'Test Description',
      image: '/test/image.jpg',
    };

    const wrapper = mount(textInputs, {
      global: {
        plugins: [store],
      },
      props: {
        goal: goal,
      },
    });

    // Verify that the initial goal data is rendered
    await nextTick();
    expect(wrapper.find('h4').text()).toBe('Test Goal');
    expect(wrapper.find('p.card-text').text()).toBe('Test Description');
    expect(wrapper.find('button.btn-outline-primary').text()).toBe('Edit');
  });

  it('emits change-GoalName when goal name input is changed', async () => {
    const goal = {
      name: 'Test Goal',
      description: 'Test Description',
      image: '/test/image.jpg',
    };
    store.state.view = 'admin'
    const wrapper = mount(textInputs, {
      global: {
        plugins: [store],
      },
      props: {
        goal,
      },
    });
    await nextTick();

    const input = wrapper.find('input#goalnameplaceholder');
    await input.setValue('Updated Goal Name');

    await nextTick();
    expect(wrapper.emitted('change-GoalName')[1]).toEqual(['Updated Goal Name']);
  });

  it('emits change-GoalDescription when goal description is changed', async () => {
    const goal = {
      name: 'Test Goal',
      description: 'Test Description',
      image: '/test/image.jpg',
    };
    store.state.view = 'admin';
    const wrapper = mount(textInputs, {
      global: {
        plugins: [store],
      },
      props: {
        goal,
      },
    });
    await nextTick();

    const textarea = wrapper.find('textarea#goalsubjectplaceholder');
    await textarea.setValue('Updated Goal Description');

    await nextTick();
    expect(wrapper.emitted('change-GoalDescription')[1]).toEqual(['Updated Goal Description']);
  });

  it('handles image selection and emits change-LpImage', async () => {
    const goal = {
      name: 'Test Goal',
      description: 'Test Description',
      image: '/test/image.jpg',
    };
    store.state.view = 'admin';
    const wrapper = mount(textInputs, {
      global: {
        plugins: [store],
      },
      props: {
        goal,
      },
    });
    await nextTick();
    let imageOption = wrapper.find('.deselect-btn');
    expect(imageOption.exists()).toBe(true); // Ensure the image option exists before triggering the event
    await imageOption.trigger('click');
    imageOption = wrapper.find('.btn-info');
    expect(imageOption.exists()).toBe(true); // Ensure the image option exists before triggering the event
    await imageOption.trigger('click');
    expect(wrapper.emitted('change-LpImage')).toBeTruthy();

  });

  it('uploads an image and emits change-LpImage after the upload', async () => {
    const goal = {
      name: 'Test Goal',
      description: 'Test Description',
      image: '/test/image.jpg',
    };

    store.state.view = 'admin';

    const wrapper = mount(textInputs, {
      global: {
        plugins: [store],
      },
      props: {
        goal,
      },
    });

    await nextTick();

    // Simulate file upload
    const fileInput = wrapper.find('input[type="file"]');
    const file = new File(['dummy content'], 'test.jpg', { type: 'image/jpeg' });

    Object.defineProperty(fileInput.element, 'files', {
      value: [file],
    });

    await fileInput.trigger('change');
    await nextTick();

    // Simulate the upload process
    await wrapper.vm.uploadNewImage();

  });

});