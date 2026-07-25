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
 * manual check.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import manual_check from '../../../../../components/restriction/conditions/manual_check.vue';
import { createStore } from 'vuex';
import { mount } from '@vue/test-utils';

// The restriction manual_check is intentionally minimal: it just renders the
// restriction description text (unlike the completion variant, which has a
// textarea). This guards that contract.
describe('manual_check.vue (restriction)', () => {
  const factory = (restriction) =>
    mount(manual_check, {
      global: { plugins: [createStore({ state: { strings: {} } })] },
      props: { modelValue: {}, restriction },
    });

  it('renders the restriction description', () => {
    const wrapper = factory({ description: 'Teacher confirms manually' });
    expect(wrapper.find('.form-check').text()).toBe('Teacher confirms manually');
  });

  it('renders without crashing when the description is empty', () => {
    const wrapper = factory({ description: '' });
    expect(wrapper.find('.form-check').exists()).toBe(true);
    expect(wrapper.find('.form-check').text()).toBe('');
  });
});
