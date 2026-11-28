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
 * Identity of an expanded course panel of a stacked node (mod_adele #17).
 *
 * Each panel is one course of the stack. Its identity is the real course
 * id - never its position in the stack or the order it was rendered in -
 * and its readable name is the course's name, with the course id beside it
 * so two courses of the same name are told apart.
 *
 * @module     local_adele/expandedCourse
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fill the placeholders of a language string ({$a}, {$a->key}) in one pass:
 * a name that contains "$&" or a placeholder itself stays as it is.
 *
 * @param {string} template
 * @param {object} values key => value; '' for {$a}.
 * @returns {string}
 */
export function put(template, values) {
  return String(template).replace(/\{\$a(?:->(\w+))?\}/g, (match, key) => {
    const k = key || '';
    return Object.prototype.hasOwnProperty.call(values, k) ? String(values[k]) : match;
  });
}

/**
 * A value usable in a DOM id.
 *
 * @param {number|string} value
 * @returns {string}
 */
export function domSafe(value) {
  return String(value).replace(/[^A-Za-z0-9_-]/g, '_');
}

/**
 * The DOM id of the card of a course's panel: unique on the page, also when
 * two stacks hold the same course.
 *
 * @param {string} parentid The stacked node.
 * @param {number|string} courseid The course.
 * @returns {string}
 */
export function expandedCoursePanelId(parentid, courseid) {
  return `adele-expanded-course-${domSafe(parentid)}-${domSafe(courseid)}`;
}

/**
 * The DOM id of the button that expands and collapses a stack.
 *
 * @param {string} parentid The stacked node.
 * @returns {string}
 */
export function stackToggleId(parentid) {
  return `adele-stack-toggle-${domSafe(parentid)}`;
}

/**
 * The DOM ids the expand button of a stack controls: the cards of its
 * courses, in the order of the stack.
 *
 * @param {string} parentid The stacked node.
 * @param {Array} courseids The courses of the stack.
 * @returns {string}
 */
export function stackControls(parentid, courseids) {
  return (courseids || []).map((id) => expandedCoursePanelId(parentid, id)).join(' ');
}

/**
 * The VueFlow node id of the panel of a course.
 *
 * @param {string} parentid The stacked node.
 * @param {number|string} courseid The course.
 * @returns {string}
 */
export function expandedCourseNodeId(parentid, courseid) {
  return `${parentid}_expanded_courses_${courseid}`;
}

/**
 * The name of a course of a node: the name the node gives it, else the
 * course's own name, else null.
 *
 * @param {object} data The node data (course_node_id_description).
 * @param {number|string} courseid The course.
 * @param {Array|null} availablecourses The store's course list.
 * @returns {string|null}
 */
export function expandedCourseTitle(data, courseid, availablecourses) {
  const desc = data && data.course_node_id_description;
  if (desc && desc[courseid] && desc[courseid].fullname) {
    return desc[courseid].fullname;
  }
  const course = (availablecourses || []).find((c) => c.course_node_id && c.course_node_id[0] == courseid);
  return course ? course.fullname : null;
}

/**
 * The accessible name of the panel: "<name> (<short name>, course ID <id>)",
 * without a short name "<name> (course ID <id>)".
 *
 * @param {string|null} title The course name.
 * @param {number|string} courseid The course.
 * @param {object} strings The store's strings (expanded_course_label, expanded_course_label_short,
 *     expanded_course_label_noname).
 * @param {string|null} shortname The course's short name.
 * @returns {string}
 */
export function expandedCourseLabel(title, courseid, strings, shortname = null) {
  const s = strings || {};
  if (title && shortname) {
    return put(s.expanded_course_label_short || '{$a->name} ({$a->shortname}, course ID {$a->id})',
      { name: title, shortname, id: courseid });
  }
  if (title) {
    return put(s.expanded_course_label || '{$a->name} (course ID {$a->id})', { name: title, id: courseid });
  }
  return put(s.expanded_course_label_noname || 'Course ID {$a}', { '': courseid });
}

/**
 * The entry of a course in the node's course list of the store, if any.
 *
 * @param {number|string} courseid The course.
 * @param {Array|null} availablecourses The store's course list.
 * @returns {object|null}
 */
function availableCourse(courseid, availablecourses) {
  return (availablecourses || []).find((c) => c.course_node_id && c.course_node_id[0] == courseid) || null;
}

/**
 * The short name of a course: from the node data (set by the server for
 * every view), else from the store's course list, else null.
 *
 * @param {object} data The node data.
 * @param {number|string} courseid The course.
 * @param {Array|null} availablecourses The store's course list.
 * @returns {string|null}
 */
export function expandedCourseShortname(data, courseid, availablecourses) {
  const desc = data && data.course_node_id_description;
  if (desc && desc[courseid] && desc[courseid].shortname) {
    return desc[courseid].shortname;
  }
  const course = availableCourse(courseid, availablecourses);
  return course && course.shortname ? course.shortname : null;
}

/**
 * The course's own full name - not a name a teacher gave it in the node.
 *
 * @param {object} data The node data.
 * @param {number|string} courseid The course.
 * @param {Array|null} availablecourses The store's course list.
 * @returns {string|null}
 */
export function expandedCourseFullname(data, courseid, availablecourses) {
  const desc = data && data.course_node_id_description;
  if (desc && desc[courseid] && desc[courseid].coursefullname) {
    return desc[courseid].coursefullname;
  }
  const course = availableCourse(courseid, availablecourses);
  return course && course.fullname ? course.fullname : null;
}

/**
 * The visible description line of a panel, which is also its
 * aria-describedby text: short name, the course's full name (when the
 * heading shows another name), course id and the completion status - the
 * status in words, not only by colour.
 *
 * @param {object} what {title, shortname, fullname, courseid, completed}.
 * @param {object} strings The store's strings.
 * @returns {string}
 */
export function expandedCourseDescription(what, strings) {
  const s = strings || {};
  const fill = (key, fallback, value) => put(s[key] || fallback, { '': value });
  const parts = [];
  if (what.shortname) {
    parts.push(fill('expanded_course_shortname', 'Short name: {$a}', what.shortname));
  }
  if (what.fullname && what.fullname !== what.title) {
    parts.push(fill('expanded_course_fullname', 'Course: {$a}', what.fullname));
  }
  parts.push(fill('expanded_course_id', 'Course ID {$a}', what.courseid));
  parts.push(what.completed
    ? (s.expanded_course_status_completed || 'Completed')
    : (s.expanded_course_status_open || 'Not completed'));
  return parts.join(' · ');
}
