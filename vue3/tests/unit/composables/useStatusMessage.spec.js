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
 * use Status Message.spec module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { computed } from 'vue';
import { useStatusMessage } from '../../../composables/useStatusMessage';

// Helper: wrap a plain completion.feedback object in the shape the composable
// expects and return the resolved statusMessage.
const resolve = (feedback) => {
  const data = computed(() => ({ completion: { feedback } }));
  const { statusMessage } = useStatusMessage(data);
  return statusMessage.value;
};

describe('useStatusMessage', () => {
  it('returns "0" when no completion buckets are defined', () => {
    const status = resolve({
      status_restriction: 'before',
      completion: {
        after: null,
        after_all: null,
        before: null,
        inbetween: null,
      },
    });
    expect(status).toBe('0');
  });

  it('returns "a1" for a "before" restriction with pending before/inbetween completions', () => {
    const status = resolve({
      status_restriction: 'before',
      completion: { after: [], before: ['x'], inbetween: [] },
    });
    expect(status).toBe('a1');
  });

  it('returns "a2" for an "inbetween" restriction with no after completions', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: [], before: ['x'], inbetween: [] },
    });
    expect(status).toBe('a2');
  });

  it('returns "b" for "inbetween" restriction with after + after_all completions', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: ['done'], after_all: { a: 1 } },
    });
    expect(status).toBe('b');
  });

  it('returns "c" for "inbetween" restriction with after but empty after_all', () => {
    const status = resolve({
      status_restriction: 'inbetween',
      completion: { after: ['done'], after_all: {} },
    });
    expect(status).toBe('c');
  });

  it('returns "d" for "after" restriction with a non-empty after_all', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: ['done'], after_all: ['x'] },
    });
    expect(status).toBe('d');
  });

  it('returns "e" for "after" restriction with after but empty after_all array', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: ['done'], after_all: [] },
    });
    expect(status).toBe('e');
  });

  it('returns "f" for "after" restriction with no after completions', () => {
    const status = resolve({
      status_restriction: 'after',
      completion: { after: [], after_all: null },
    });
    expect(status).toBe('f');
  });

  it('returns "" for an unrecognised restriction status', () => {
    const status = resolve({
      status_restriction: 'something-else',
      completion: { after: ['x'], before: ['y'] },
    });
    expect(status).toBe('');
  });

  it('does not crash when completion.feedback is missing entirely', () => {
    const data = computed(() => ({ completion: {} }));
    const { statusMessage } = useStatusMessage(data);
    // completion buckets are all undefined -> "Status 0" branch.
    expect(statusMessage.value).toBe('0');
  });
});
