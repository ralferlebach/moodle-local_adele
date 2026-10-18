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
 * The store's part of the teacher course access (issue "Kurszugriff in der
 * Lehrendenansicht", TP1): it asks mod_adele with the module context and
 * keeps what the server said - and on a refusal keeps nothing as open.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

jest.mock('core/ajax', () => ({ __esModule: true, default: { call: jest.fn() } }), { virtual: true });
jest.mock('core/localstorage', () => ({ __esModule: true, default: { get: jest.fn(), set: jest.fn() } }), { virtual: true });
jest.mock('core/notification', () => ({ __esModule: true, default: { alert: jest.fn(), exception: jest.fn() } }), { virtual: true });

import moodleAjax from 'core/ajax';
import moodleNotification from 'core/notification';
import { createAppStore } from '../../store.js';

describe('store - node course access', () => {
  afterEach(() => jest.clearAllMocks());

  it('asks mod_adele with the module context and keeps the answer', async () => {
    const answer = { mode: 'existing_only', courses: [{ courseid: 11, status: 'open' }] };
    moodleAjax.call.mockReturnValue([Promise.resolve(answer)]);
    const store = createAppStore();
    store.state.contextid = 4711;
    await store.dispatch('fetchNodeCourseAccess');
    expect(moodleAjax.call).toHaveBeenCalledWith([{ methodname: 'mod_adele_get_node_course_access', args: { contextid: 4711 } }]);
    expect(store.state.nodeCourseAccess).toEqual({ loading: false, mode: 'existing_only', courses: answer.courses });
  });

  it('a refusal: no course is left as open, the error is kept, no raw exception dialog', async () => {
    moodleAjax.call.mockReturnValue([Promise.reject(new Error('nopermissions'))]);
    const store = createAppStore();
    store.state.nodeCourseAccess = { loading: false, courses: [{ courseid: 11, status: 'open' }] };
    await store.dispatch('fetchNodeCourseAccess');
    expect(store.state.nodeCourseAccess).toEqual({ loading: false, error: true, courses: [] });
    expect(moodleNotification.exception).not.toHaveBeenCalled();
  });

  it('while asking: loading, and the old answer is gone', () => {
    moodleAjax.call.mockReturnValue([new Promise(() => {})]);
    const store = createAppStore();
    store.state.nodeCourseAccess = { loading: false, courses: [{ courseid: 11, status: 'open' }] };
    store.dispatch('fetchNodeCourseAccess');
    expect(store.state.nodeCourseAccess).toEqual({ loading: true, courses: [] });
  });

  it('the active tile is one at a time, and can be cleared', () => {
    const store = createAppStore();
    store.commit('setActiveNode', 'dndnode_1');
    store.commit('setActiveNode', 'dndnode_2');
    expect(store.state.activeNodeId).toBe('dndnode_2');
    store.commit('setActiveNode', null);
    expect(store.state.activeNodeId).toBeNull();
  });
});
