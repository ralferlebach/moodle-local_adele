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
 * opens it in a new tab. What is open is decided by mod_adele on the server
 * (mod_adele_get_node_course_access); this component only renders it.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useStore } from 'vuex';

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

defineExpose({ close, rows });
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
            {{ course.status === 'open' ? strings.teacher_course_status_open : strings.teacher_course_status_none }}
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
        </div>
        <div
          :id="'adele-course-full-' + safeId + '-' + course.courseid"
          class="adele-course-access-full"
        >
          {{ course.fullname }}
        </div>
      </li>
    </ul>
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
.adele-course-access-open {
  margin-left: auto;
}
.adele-course-access-full {
  font-size: 0.85rem;
  color: #343a40;
}
.adele-course-access-toggle:focus-visible,
.adele-course-access-open:focus-visible {
  outline: 3px solid #0f6cbf;
  outline-offset: 2px;
}
</style>
