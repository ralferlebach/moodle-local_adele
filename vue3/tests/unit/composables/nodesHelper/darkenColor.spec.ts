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
 * darken Color.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import darkenColor from '../../../../composables/nodesHelper/darkenColor';

describe('darkenColor', () => {
  it('should darken the color', () => {
    const color = '#FF5733'; // A shade of orange
    const darken = 0.5; // A value to darken the color
    const expectedOutput = '#73514A'; // Expected darker color

    expect(darkenColor(color, darken)).toBe(expectedOutput);
  });
});