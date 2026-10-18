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
 * Confirm an enrolment into a node course (local_adele #600, TP4).
 *
 * Before anything happens the teacher sees the course, the role (with what
 * it may do), the source of the enrolment, and that it ends with its basis.
 * Only "Enrol" enrols; the server decides and names the role given. A
 * refusal keeps the dialog open with the server's reason. Modal like the
 * request dialog: focus inside and kept there, Escape cancels.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { useStore } from 'vuex';
import { modalKeydown } from './modalFocus';

const store = useStore();

const props = defineProps({
  // The row of the course: courseid, label, fullname, role, roledescription.
  course: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['close', 'enrolled']);

const dialog = ref(null);
const confirm = ref(null);
const busy = ref(false);
const error = ref('');

const strings = computed(() => store.state.strings || {});
const source = computed(() => (store.state.nodeCourseAccess && store.state.nodeCourseAccess.source) || '');
const ids = computed(() => {
  const base = `adele-enrol-confirm-${Number(props.course.courseid)}`;
  return { title: `${base}-title`, body: `${base}-body` };
});

/**
 * Fill a Moodle string's {$a} placeholder.
 *
 * @param {string} key
 * @param {string} value
 * @returns {string}
 */
function fill(key, value) {
  return String(strings.value[key] || '').split('{$a}').join(String(value));
}

/**
 * Close without enrolling.
 */
function cancel() {
  if (!busy.value) {
    emit('close');
  }
}

/**
 * Keep Tab inside the dialog; Escape cancels.
 *
 * @param {KeyboardEvent} event
 */
function onKeydown(event) {
  modalKeydown(event, dialog.value, cancel);
}

/**
 * Enrol - accepted by the server or not.
 */
async function enrol() {
  if (busy.value) {
    return;
  }
  busy.value = true;
  error.value = '';
  try {
    const answer = await store.dispatch('enrolInNodeCourse', props.course.courseid);
    emit('enrolled', answer && answer.role ? answer.role : props.course.role);
  } catch (e) {
    const message = e && (e.message || (e.error && e.error.message));
    error.value = message ? String(message) : strings.value.teacher_enrol_error;
  } finally {
    busy.value = false;
  }
}

onMounted(async() => {
  await nextTick();
  if (confirm.value) {
    confirm.value.focus();
  }
});

defineExpose({ enrol, cancel });
</script>

<template>
  <Teleport to="body">
    <div
      class="adele-access-request-backdrop"
      @mousedown.self="cancel"
    >
      <div
        ref="dialog"
        class="adele-access-request"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="ids.title"
        :aria-describedby="ids.body"
        tabindex="-1"
        data-testid="enrol-confirm-dialog"
        @keydown="onKeydown"
      >
        <h5
          :id="ids.title"
          class="adele-access-request-title"
        >
          {{ fill('teacher_enrol_title', course.fullname) }}
        </h5>
        <div :id="ids.body">
          <p data-testid="enrol-confirm-role">
            {{ fill('teacher_enrol_role', course.role) }}
          </p>
          <!-- eslint-disable vue/no-v-html -->
          <div
            v-if="course.roledescription"
            class="adele-enrol-confirm-rights"
            data-testid="enrol-confirm-rights"
            v-html="course.roledescription"
          />
          <!-- eslint-enable vue/no-v-html -->
          <p data-testid="enrol-confirm-source">
            {{ fill('teacher_enrol_source', source) }}
          </p>
          <p>{{ strings.teacher_enrol_revocable }}</p>
        </div>
        <p
          v-if="error"
          role="alert"
          class="adele-access-request-error"
          data-testid="enrol-confirm-error"
        >
          {{ error }}
        </p>
        <div class="adele-access-request-actions">
          <button
            ref="confirm"
            type="button"
            class="btn btn-primary"
            :disabled="busy"
            data-testid="enrol-confirm"
            @click="enrol"
          >
            {{ strings.teacher_enrol_confirm }}
          </button>
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            data-testid="enrol-cancel"
            @click="cancel"
          >
            {{ strings.teacher_request_cancel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.adele-access-request-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1060;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}
.adele-access-request {
  background: #ffffff;
  color: #1d2125;
  border-radius: 0.5rem;
  max-width: 36rem;
  width: 100%;
  max-height: 100%;
  overflow: auto;
  padding: 1rem 1.25rem;
  text-align: left;
}
.adele-access-request:focus {
  outline: 3px solid #0f6cbf;
  outline-offset: 2px;
}
.adele-access-request-title {
  font-size: 1.15rem;
  margin: 0 0 0.5rem;
}
.adele-enrol-confirm-rights {
  font-size: 0.9rem;
  border-left: 3px solid #8f959e;
  padding-left: 0.5rem;
  margin-bottom: 0.5rem;
}
.adele-access-request-error {
  color: #a1281f;
  margin-top: 0.5rem;
}
.adele-access-request-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.75rem;
}
.adele-access-request-actions .btn:focus-visible {
  outline: 3px solid #0f6cbf;
  outline-offset: 2px;
}
</style>
