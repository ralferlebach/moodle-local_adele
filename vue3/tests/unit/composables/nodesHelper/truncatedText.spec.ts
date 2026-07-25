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
 * truncated Text.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import truncatedText from '../../../../composables/nodesHelper/truncatedText';

describe('truncatedText', () => {
  const short_text  = 'Testing Title';
  const exact_text = 'A very very long Testing Tit';
  const long_text = 'A very very long Testing Title must be cut smaller';
  const long_text_shorten = 'A very very long Testing Title must be...';
  const long_custom_text_shorten = 'A very very long Tes...';
  it('should return the text as it is because it is too short', () => {
    const result = truncatedText(short_text);
    expect(result).toEqual(short_text);
  });
  it('should return the text as it is because it is exact the lenght', () => {
    const result = truncatedText(exact_text);
    expect(result).toEqual(exact_text);
  });
  it('should return the text truncated', () => {
    const result = truncatedText(long_text);
    expect(result).toEqual(long_text_shorten);
  });
  it('should return custom sized text', () => {
    const result = truncatedText(long_text, 20);
    expect(result).toEqual(long_custom_text_shorten);
  });
});