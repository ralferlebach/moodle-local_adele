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
 * Identity of expanded course panels (mod_adele #17).
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
  domSafe,
  expandedCourseDescription,
  expandedCourseFullname,
  expandedCourseLabel,
  expandedCourseNodeId,
  expandedCoursePanelId,
  expandedCourseShortname,
  expandedCourseTitle,
  put,
  stackControls,
  stackToggleId,
} from '../../../../composables/nodesHelper/expandedCourse';

const strings = {
  expanded_course_label: '{$a->name} (Kurs-ID {$a->id})',
  expanded_course_label_noname: 'Kurs-ID {$a}',
};

describe('expandedCourse', () => {
  it('builds the node id from the course id, not from a position', () => {
    expect(expandedCourseNodeId('dndnode_1', 5)).toBe('dndnode_1_expanded_courses_5');
    expect(expandedCourseNodeId('dndnode_1', '5')).toBe(expandedCourseNodeId('dndnode_1', 5));
  });

  it('prefers the name the node gives the course, then the course name', () => {
    const data = { course_node_id_description: { 5: { fullname: 'Given name' } } };
    const courses = [{ course_node_id: [5], fullname: 'Course name' }, { course_node_id: [6], fullname: 'Other' }];
    expect(expandedCourseTitle(data, 5, courses)).toBe('Given name');
    expect(expandedCourseTitle({}, 5, courses)).toBe('Course name');
    expect(expandedCourseTitle({}, 6, courses)).toBe('Other');
    expect(expandedCourseTitle({}, 7, courses)).toBeNull();
    expect(expandedCourseTitle(null, 7, null)).toBeNull();
  });

  it('tells two courses of the same name apart by their id', () => {
    const a = expandedCourseLabel('Mathe', 4, strings);
    const b = expandedCourseLabel('Mathe', 5, strings);
    expect(a).toBe('Mathe (Kurs-ID 4)');
    expect(b).toBe('Mathe (Kurs-ID 5)');
    expect(a).not.toBe(b);
  });

  it('names a course without a name by its id', () => {
    expect(expandedCourseLabel(null, 9, strings)).toBe('Kurs-ID 9');
    expect(expandedCourseLabel(null, 9, {})).toBe('Course ID 9');
  });

  it('fills placeholders in one pass: "$&" and placeholders in a name stay as they are', () => {
    expect(put('{$a->name} ({$a->id})', { name: 'A $& {$a->id} B', id: 3 })).toBe('A $& {$a->id} B (3)');
    expect(put('Kurs-ID {$a}', { '': 4 })).toBe('Kurs-ID 4');
    expect(put('{$a->unknown}', {})).toBe('{$a->unknown}');
    expect(expandedCourseLabel('Mathe $1', 5, strings)).toBe('Mathe $1 (Kurs-ID 5)');
  });

  it('DOM ids: per stack and course, safe characters, the button controls the cards in stack order', () => {
    expect(domSafe('a b"c')).toBe('a_b_c');
    expect(expandedCoursePanelId('dndnode_1', 5)).toBe('adele-expanded-course-dndnode_1-5');
    expect(expandedCoursePanelId('dndnode_2', 5)).not.toBe(expandedCoursePanelId('dndnode_1', 5));
    expect(stackToggleId('dndnode_1')).toBe('adele-stack-toggle-dndnode_1');
    expect(stackControls('dndnode_1', [12, 11])).toBe('adele-expanded-course-dndnode_1-12 adele-expanded-course-dndnode_1-11');
    expect(stackControls('dndnode_1', [11])).toBe('adele-expanded-course-dndnode_1-11');
    expect(stackControls('dndnode_1', null)).toBe('');
  });

  it('short and full name from the node data, else from the course list, else none', () => {
    const data = { course_node_id_description: { 5: { fullname: 'Given', coursefullname: 'Real', shortname: 'R5' } } };
    const list = [{ course_node_id: [6], fullname: 'Six', shortname: 'S6' }, { course_node_id: [7], fullname: 'Seven' }];
    expect(expandedCourseShortname(data, 5, list)).toBe('R5');
    expect(expandedCourseFullname(data, 5, list)).toBe('Real');
    expect(expandedCourseShortname(data, 6, list)).toBe('S6');
    expect(expandedCourseFullname(data, 6, list)).toBe('Six');
    expect(expandedCourseShortname(data, 7, list)).toBeNull();
    expect(expandedCourseShortname(null, 8, null)).toBeNull();
    expect(expandedCourseFullname(null, 8, null)).toBeNull();
  });

  it('the label carries the short name when there is one', () => {
    const s = { ...strings, expanded_course_label_short: '{$a->name} ({$a->shortname}, Kurs-ID {$a->id})' };
    expect(expandedCourseLabel('Mathe', 5, s, 'MA')).toBe('Mathe (MA, Kurs-ID 5)');
    expect(expandedCourseLabel('Mathe', 5, {}, 'MA')).toBe('Mathe (MA, course ID 5)');
    expect(expandedCourseLabel('Mathe', 5, s, null)).toBe('Mathe (Kurs-ID 5)');
  });

  it('the description: short name, the own full name only when the heading shows another, id, status in words', () => {
    expect(expandedCourseDescription({ title: 'Given', shortname: 'R5', fullname: 'Real', courseid: 5, completed: true }, {}))
      .toBe('Short name: R5 · Course: Real · Course ID 5 · Completed');
    expect(expandedCourseDescription({ title: 'Real', shortname: null, fullname: 'Real', courseid: 5, completed: false }, {}))
      .toBe('Course ID 5 · Not completed');
    expect(expandedCourseDescription({ title: 'X', courseid: 5, completed: false }, {
      expanded_course_id: 'Kurs-ID {$a}', expanded_course_status_open: 'Nicht abgeschlossen',
    })).toBe('Kurs-ID 5 · Nicht abgeschlossen');
  });
});
