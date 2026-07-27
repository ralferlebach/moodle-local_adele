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
 * Not Found.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @jest-environment jsdom
 */
const path = require('path');
const resolvedPath = require.resolve('@vue/test-utils');
console.log(resolvedPath);
import { createApp } from 'vue';
import NotFound from '../../../components/NotFound.vue';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createRouter, createMemoryHistory } from 'vue-router';

describe('NotFound.vue', () => {
  let store;
  let router;

  beforeEach(async () => {
    store = createStore({
      state: {
        strings: {
          route_not_found_site_name: 'Page Not Found',
          route_not_found: 'The page you are looking for does not exist.',
          btnreload: 'Reload',
        },
        view: true,
      },
    });

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        {
          path: '/overview',
          name: 'learningpaths-edit-overview',
          component: { template: '<div>Learning Path Overview</div>' }, // Mock component
        },
      ],
    });
    router.push('/overview');
    await router.isReady();
  });

  it('renders the correct text based on Vuex store', () => {
    const wrapper = mount(NotFound, {
      global: {
        plugins: [store, router],
      },
    });

    expect(wrapper.find('h2').text()).toBe(store.state.strings.route_not_found_site_name);
    expect(wrapper.find('h3').text()).toBe(store.state.strings.route_not_found);
    const button = wrapper.find('.btn.btn-primary');
    expect(button.text()).toBe(store.state.strings.btnreload);
  });

  it('redirects to the overview page on mount if router is undefined', async () => {
    router.push = jest.fn(); // Mock router.push
    const wrapper = mount(NotFound, {
      global: {
        plugins: [store, router],
      },
    });

    // Simulate the condition where router is undefined (mock this behavior)
    wrapper.vm.$router = undefined;

    // Trigger the onMounted lifecycle hook
    await wrapper.vm.$nextTick();

    // Check if the router.push was called
    expect(router.push).toHaveBeenCalledWith({ name: 'learningpaths-edit-overview' });
  });
});