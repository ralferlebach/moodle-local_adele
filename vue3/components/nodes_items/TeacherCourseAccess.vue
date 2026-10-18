<!-- // This file is part of Moodle - http://moodle.org/
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
 * The courses of the active tile, for the teacher view of mod_adele
 * (issue "Kurszugriff in der Lehrendenansicht", TP1).
 *
 * Shown only on the tile the teacher has activated (click, or Enter on the
 * focused tile) and only in the teacher view of an embedding - never to
 * learners, never in the path editor. One row per course of the node, in the
 * node's own order: the course's short name, its full name (always present
 * as text, not only as a tooltip), the access status in words, and - only
 * when the server says the course is open to this person - a link that
 * opens it in a new tab; with the mode "request", a button that opens the
 * request dialog (TP2). What is open is decided by mod_adele on the server
 * (mod_adele_get_node_course_access); this component only renders it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useStore } from 'vuex';
import CourseAccessRequestDialog from './CourseAccessRequestDialog.vue';
import CourseEnrolConfirmDialog from './CourseEnrolConfirmDialog.vue';

const store = useStore();

const props = defineProps({
  // The vue-flow id of the tile (the id the activation stores).
  nodeId: {
    type: String,
    required: true,
  },
  // The courses of this tile, in the node's stored order.
  courseIds: {
    type: Array,
    default: () => [],
  },
});

const expanded = ref(true);
// The course whose request dialog is open, the button that opened it, and
// the courses a request was sent for in this view (course id => recipient).
const requestFor = ref(null);
const enrolFor = ref(null);
let requestOpener = null;
const sent = reactive({});
const toggle = ref(null);
const section = ref(null);

const shown = computed(() => store.state.view === 'teacher' && store.state.activeNodeId === props.nodeId);
const access = computed(() => store.state.nodeCourseAccess || { loading: true, courses: [] });
const strings = computed(() => store.state.strings || {});
const safeId = computed(() => String(props.nodeId).replace(/[^A-Za-z0-9_-]/g, '_'));
const headingId = computed(() => `adele-course-access-heading-${safeId.value}`);
const listId = computed(() => `adele-course-access-list-${safeId.value}`);

/**
 * Fill a Moodle string's {$a} / {$a->name} placeholders.
 *
 * @param {string} text
 * @param {object|string|number} a
 * @returns {string}
 */
function fill(text, a) {
  let out = String(text || '');
  if (a !== null && typeof a === 'object') {
    Object.keys(a).forEach((key) => {
      out = out.split(`{$a->${key}}`).join(String(a[key]));
    });
    return out;
  }
  return out.split('{$a}').join(String(a));
}

// The rows: this tile's courses as the server knows them, in the tile's
// order. A course the server does not list (deleted, not in the path) gets
// no row and no action. Equal short names are told apart by the course id.
const rows = computed(() => {
  const known = {};
  (access.value.courses || []).forEach((course) => {
    known[Number(course.courseid)] = course;
  });
  const list = [];
  (props.courseIds || []).forEach((id) => {
    const course = known[Number(id)];
    if (course && !list.some((row) => row.courseid === course.courseid)) {
      list.push(course);
    }
  });
  const counts = {};
  list.forEach((course) => {
    counts[course.shortname] = (counts[course.shortname] || 0) + 1;
  });
  return list.map((course) => ({
    ...course,
    label: counts[course.shortname] > 1
      ? `${course.shortname} (${fill(strings.value.teacher_course_id, course.courseid)})`
      : course.shortname,
  }));
});

const heading = computed(() => fill(strings.value.teacher_courses_heading, rows.value.length));

/**
 * The accessible name of a course link.
 *
 * @param {object} course
 * @returns {string}
 */
function openLabel(course) {
  return fill(strings.value.teacher_course_open_label, { shortname: course.label, fullname: course.fullname });
}

/**
 * The access status of a row, in words.
 *
 * @param {object} course
 * @returns {string}
 */
function statusText(course) {
  if (sent[course.courseid] !== undefined) {
    return strings.value.teacher_course_status_requested;
  }
  return {
    open: strings.value.teacher_course_status_open,
    request: strings.value.teacher_course_status_request,
    request_copy: strings.value.teacher_course_status_requestcopy,
    enrol: strings.value.teacher_course_status_enrol,
    unavailable: strings.value.teacher_course_status_unavailable,
  }[course.status] || strings.value.teacher_course_status_none;
}

/**
 * The accessible name of a row's request button.
 *
 * @param {object} course
 * @returns {string}
 */
function requestLabel(course) {
  const key = course.status === 'request' ? 'teacher_course_request_label' : 'teacher_course_requestcopy_label';
  return fill(strings.value[key], { shortname: course.label, fullname: course.fullname });
}

/**
 * The visible label of a row's enrol button: the activity's mode decides.
 *
 * @returns {string}
 */
function enrolText() {
  const mode = access.value.mode || '';
  return strings.value['teacher_course_' + mode] || strings.value.teacher_course_enrol_student;
}

/**
 * The accessible name of a row's enrol button.
 *
 * @param {object} course
 * @returns {string}
 */
function enrolLabel(course) {
  return fill(strings.value.teacher_course_enrol_label, {
    action: enrolText(),
    shortname: course.label,
    fullname: course.fullname,
  });
}

/**
 * Open the enrolment confirmation of a row.
 *
 * @param {object} course
 * @param {Event} event
 */
function openEnrol(course, event) {
  requestOpener = event && event.currentTarget ? event.currentTarget : null;
  enrolFor.value = course;
}

/**
 * Close the enrolment confirmation; the focus goes back to its button.
 */
function closeEnrol() {
  enrolFor.value = null;
  nextTick(() => {
    if (requestOpener && document.body.contains(requestOpener)) {
      requestOpener.focus();
    }
  });
}

/**
 * Enrolled: ask the server again what is open now, say so, and put the
 * focus on the course's new link.
 *
 * @param {string} role
 */
async function onEnrolled(role) {
  const course = enrolFor.value;
  enrolFor.value = null;
  if (!course) {
    return;
  }
  await store.dispatch('fetchNodeCourseAccess');
  store.commit('announce', fill(strings.value.teacher_enrol_done, { course: course.label, role }));
  await nextTick();
  const link = section.value
    ? section.value.querySelector(`[data-testid="node-course-${Number(course.courseid)}"] a`)
    : null;
  if (link) {
    link.focus();
  }
}

/**
 * Open the request dialog of a row.
 *
 * @param {object} course
 * @param {Event} event
 */
function openRequest(course, event) {
  requestOpener = event && event.currentTarget ? event.currentTarget : null;
  requestFor.value = course;
}

/**
 * Close the request dialog; the focus goes back to its button.
 */
function closeRequest() {
  requestFor.value = null;
  nextTick(() => {
    if (requestOpener && document.body.contains(requestOpener)) {
      requestOpener.focus();
    }
  });
}

/**
 * The server accepted the request: say so, in the row and aloud.
 *
 * @param {string} recipient
 */
function onSent(recipient) {
  const course = requestFor.value;
  if (course) {
    sent[course.courseid] = recipient;
    store.commit('announce', fill(strings.value.teacher_request_sent, { recipient, course: course.label }));
  }
  closeRequest();
}

/**
 * Close the course actions and give the focus back to the tile.
 */
function close() {
  store.commit('setActiveNode', null);
  nextTick(() => {
    const id = String(props.nodeId).replace(/["\\]/g, '\\$&');
    const wrapper = document.querySelector(`.vue-flow__node[data-id="${id}"]`);
    if (wrapper) {
      wrapper.focus();
    }
  });
}

// Every activation starts expanded; the status line announces the result.
watch(shown, (now) => {
  if (now) {
    expanded.value = true;
  }
});
watch(() => [shown.value, access.value.loading], ([now, loading]) => {
  if (now && !loading) {
    const open = rows.value.filter((row) => row.status === 'open').length;
    store.commit('announce', fill(strings.value.teacher_courses_announce, { open, all: rows.value.length }));
  }
});

defineExpose({ close, rows, requestFor, enrolFor });
</script>

<template>
  <section
    v-if="shown"
    ref="section"
    class="adele-course-access nodrag nopan"
    data-testid="node-course-access"
    :aria-labelledby="headingId"
    @click.stop
    @mousedown.stop
    @keydown.esc.stop.prevent="close"
  >
    <div class="adele-course-access-head">
      <h6
        :id="headingId"
        class="adele-course-access-title"
      >
        {{ heading }}
      </h6>
      <button
        ref="toggle"
        type="button"
        class="btn btn-sm btn-outline-secondary adele-course-access-toggle"
        :aria-expanded="expanded ? 'true' : 'false'"
        :aria-controls="listId"
        @click="expanded = !expanded"
      >
        {{ expanded ? strings.teacher_courses_collapse : strings.teacher_courses_expand }}
      </button>
    </div>
    <p
      v-if="access.loading"
      class="adele-course-access-note"
      role="status"
    >
      {{ strings.teacher_courses_loading }}
    </p>
    <p
      v-else-if="access.error"
      class="adele-course-access-note"
      role="alert"
    >
      {{ strings.teacher_courses_error }}
    </p>
    <p
      v-else-if="rows.length === 0"
      class="adele-course-access-note"
    >
      {{ strings.teacher_courses_none }}
    </p>
    <ul
      v-show="expanded && !access.loading"
      :id="listId"
      class="adele-course-access-list"
      :aria-labelledby="headingId"
    >
      <li
        v-for="course in rows"
        :key="course.courseid"
        class="adele-course-access-row"
        :data-testid="'node-course-' + course.courseid"
        :data-access="course.status"
      >
        <div class="adele-course-access-line">
          <span
            class="adele-course-access-short"
            :title="course.fullname"
          >{{ course.label }}</span>
          <span class="adele-course-access-status">
            {{ statusText(course) }}
          </span>
          <a
            v-if="course.status === 'open' && course.url"
            :href="course.url"
            target="_blank"
            rel="noopener noreferrer"
            class="btn btn-sm btn-primary adele-course-access-open"
            :aria-label="openLabel(course)"
            :aria-describedby="'adele-course-full-' + safeId + '-' + course.courseid"
          >
            <i
              class="fa fa-external-link"
              aria-hidden="true"
            />
            {{ strings.teacher_course_open }}
          </a>
          <button
            v-else-if="course.status === 'request' || course.status === 'request_copy'"
            type="button"
            class="btn btn-sm btn-outline-primary adele-course-access-request"
            :aria-label="requestLabel(course)"
            :aria-describedby="'adele-course-full-' + safeId + '-' + course.courseid"
            :data-testid="'node-course-request-' + course.courseid"
            @click="openRequest(course, $event)"
          >
            {{ course.status === 'request' ? strings.teacher_course_request : strings.teacher_course_requestcopy }}
          </button>
          <button
            v-else-if="course.status === 'enrol'"
            type="button"
            class="btn btn-sm btn-outline-primary adele-course-access-request"
            :aria-label="enrolLabel(course)"
            :aria-describedby="'adele-course-full-' + safeId + '-' + course.courseid"
            :data-testid="'node-course-enrol-' + course.courseid"
            @click="openEnrol(course, $event)"
          >
            {{ enrolText() }}
          </button>
        </div>
        <div
          :id="'adele-course-full-' + safeId + '-' + course.courseid"
          class="adele-course-access-full"
        >
          {{ course.fullname }}
        </div>
        <div
          v-if="course.status === 'unavailable' && course.reason"
          class="adele-course-access-full"
          :data-testid="'node-course-reason-' + course.courseid"
        >
          {{ course.reason }}
        </div>
      </li>
    </ul>
    <CourseAccessRequestDialog
      v-if="requestFor"
      :course="requestFor"
      @close="closeRequest"
      @sent="onSent"
    />
    <CourseEnrolConfirmDialog
      v-if="enrolFor"
      :course="enrolFor"
      @close="closeEnrol"
      @enrolled="onEnrolled"
    />
  </section>
</template>

<style scoped>
/* Opaque, neutral surface: the tile's colour must not lower the contrast of
   the text and controls on it (issue, section 3: no faint ghost buttons). */
.adele-course-access {
  background: #ffffff;
  color: #1d2125;
  border: 1px solid #8f959e;
  border-radius: 0.4rem;
  padding: 0.5rem 0.6rem;
  margin: 0.5rem;
  text-align: left;
  cursor: default;
}
.adele-course-access-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.adele-course-access-title {
  margin: 0;
  font-size: 1rem;
  color: #1d2125;
}
.adele-course-access-note {
  margin: 0.4rem 0 0;
}
.adele-course-access-list {
  list-style: none;
  margin: 0.4rem 0 0;
  padding: 0;
}
.adele-course-access-row + .adele-course-access-row {
  border-top: 1px solid #dee2e6;
  margin-top: 0.35rem;
  padding-top: 0.35rem;
}
.adele-course-access-line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.adele-course-access-short {
  font-weight: 600;
  max-width: 9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.adele-course-access-status {
  font-size: 0.85rem;
  border: 1px solid #6a737b;
  border-radius: 0.25rem;
  padding: 0 0.3rem;
}
.adele-course-access-open,
.adele-course-access-request {
  margin-left: auto;
}
.adele-course-access-full {
  font-size: 0.85rem;
  color: #343a40;
}
.adele-course-access-toggle:focus-visible,
.adele-course-access-open:focus-visible,
.adele-course-access-request:focus-visible {
  outline: 3px solid #0f6cbf;
  outline-offset: 2px;
}
</style>
