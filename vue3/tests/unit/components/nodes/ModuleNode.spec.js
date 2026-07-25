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
 * Module Node.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModuleNode from '../../../../components/nodes/ModuleNode.vue';
import darkenColor from '../../../../composables/nodesHelper/darkenColor';
import { mount } from '@vue/test-utils';
import { Handle, Position } from '@vue-flow/core';

jest.mock('../../../../composables/nodesHelper/darkenColor', () => jest.fn());
jest.mock('../../../../composables/nodesHelper/truncatedText', () => jest.fn());


describe('ModuleNode.vue', () => {

  beforeEach(() => {
    darkenColor.mockClear();
  });

  it('renders correctly with given props', async () => {
    darkenColor.mockReturnValue('#104703'); // deterministic; the real darkenColor is covered by its own spec.
    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          name: 'Test Node',
          opacity: 0.5,
          height: '100px',
          width: '200px',
        },
        zoomstep: 1,
      },
    });

    // The module renders in its own colour (#FF5733) at the given opacity...
    expect(wrapper.find('.custom-node').exists()).toBe(true);
    expect(wrapper.find('.custom-node').attributes('style')).toContain('background-color: rgba(255, 87, 51, 0.5)');
    // ...and the module-name border binds the darkened colour.
    expect(wrapper.find('.module-name').attributes('style')).toContain('border: 5px solid #104703');
  });

  it('computes backgroundColor and darkerColor correctly on mount', async () => {
    darkenColor.mockReturnValue('#1047033');

    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          opacity: 0.5,
        },
        zoomstep: 1,
      },
    });

    // Wait for the onMounted hook to complete
    await wrapper.vm.$nextTick();

    expect(darkenColor).toHaveBeenCalledWith('#FF5733');
    expect(wrapper.vm.backgroundColor).toBe('rgba(255, 87, 51, 0.5)');
    expect(wrapper.vm.darkerColor).toBe('#1047033');
  });

  it('does not render Handle components when zoomstep is not 0.2', async () => {
    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          opacity: 0.5,
          name: 'Test Node',
        },
        zoomstep: 1,
      },
    });

    expect(wrapper.findComponent(Handle).exists()).toBe(false);
  });

  it('applies correct styles to the custom node and module name', () => {
    darkenColor.mockReturnValue('#104703'); // deterministic; the real darkenColor is covered by its own spec.
    const wrapper = mount(ModuleNode, {
      props: {
        data: {
          color: '#FF5733',
          opacity: 0.75,
          height: '150px',
          width: '250px',
          name: 'Test Node',
        },
        zoomstep: 1,
      },
    });

    const customNodeStyle = wrapper.find('.custom-node').attributes('style');
    expect(customNodeStyle).toContain('background-color: rgba(255, 87, 51, 0.75)');
    expect(customNodeStyle).toContain('height: 150px');
    expect(customNodeStyle).toContain('width: 250px');

    const moduleNameStyle = wrapper.find('.module-name').attributes('style');
    expect(moduleNameStyle).toContain('border: 5px solid #104703');
  });
});