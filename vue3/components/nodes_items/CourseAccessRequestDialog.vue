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
 * "Request access" to one node course (local_adele #600, TP2).
 *
 * A modal dialog, outside the graph (teleported to the page body, so zoom
 * and panning do not touch it). The server names the people who can be
 * asked and prefills the message; the teacher edits it and sends it with
 * "Send" - nothing goes out before. Without anybody to ask there is no
 * "Send", only the text to copy and the site's contact. Keyboard: the focus
 * starts in the dialog and stays there (Tab cycles), Escape cancels, and the
 * opener gets the focus back.
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
const domPrefix = () => (store.state.domPrefix ? store.state.domPrefix + '-' : '');

const props = defineProps({
  // The row of the course: courseid, label (short name), fullname.
  course: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['close', 'sent']);

const dialog = ref(null);
const field = ref(null);
const loading = ref(true);
const sending = ref(false);
const error = ref('');
const copied = ref(false);
const data = ref(null);
const text = ref('');
const recipientid = ref(0);

const strings = computed(() => store.state.strings || {});
const ids = computed(() => {
  // Page-unique (#18), the rule of composables/domIds.js::appDomId().
  const base = `${domPrefix()}adele-access-request-${Number(props.course.courseid)}`;
  return { title: `${base}-title`, message: `${base}-message`, recipient: `${base}-recipient`, note: `${base}-note` };
});
const recipients = computed(() => (data.value && data.value.recipients) || []);
const canSend = computed(() => recipients.value.length > 0);

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
 * The reason a call failed, as the server put it.
 *
 * @param {*} e
 * @returns {string}
 */
function reason(e) {
  const message = e && (e.message || (e.error && e.error.message));
  return message ? String(message) : '';
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
 * Close without sending.
 */
function cancel() {
  if (!sending.value) {
    emit('close');
  }
}

/**
 * Send - only now does anything go out. Accepted by the server or not.
 */
async function send() {
  if (!canSend.value || sending.value) {
    return;
  }
  sending.value = true;
  error.value = '';
  try {
    const answer = await store.dispatch('sendAccessRequest', {
      courseid: props.course.courseid,
      recipientid: recipientid.value,
      message: text.value,
    });
    emit('sent', answer && answer.recipient ? answer.recipient : '');
  } catch (e) {
    error.value = reason(e) || strings.value.teacher_request_error;
  } finally {
    sending.value = false;
  }
}

/**
 * Copy the message (no recipient to send it to).
 */
async function copy() {
  copied.value = false;
  try {
    await navigator.clipboard.writeText(text.value);
    copied.value = true;
  } catch (e) {
    // Without the clipboard: select the text, the person copies it.
    if (field.value) {
      field.value.focus();
      field.value.select();
    }
  }
}

onMounted(async() => {
  await nextTick();
  if (dialog.value) {
    dialog.value.focus();
  }
  try {
    data.value = await store.dispatch('fetchAccessRequest', props.course.courseid);
    text.value = data.value.message || '';
    recipientid.value = recipients.value.length ? Number(recipients.value[0].id) : 0;
  } catch (e) {
    error.value = reason(e) || strings.value.teacher_request_error;
  } finally {
    loading.value = false;
  }
  await nextTick();
  if (field.value) {
    field.value.focus();
  }
});

defineExpose({ send, cancel });
</script>

<template>
  <Teleport to="body">
    <div
      class="adele-access-request-backdrop"
      data-testid="access-request-backdrop"
      @mousedown.self="cancel"
    >
      <div
        ref="dialog"
        class="adele-access-request"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="ids.title"
        :aria-describedby="ids.note"
        tabindex="-1"
        data-testid="access-request-dialog"
        @keydown="onKeydown"
      >
        <h5
          :id="ids.title"
          class="adele-access-request-title"
        >
          {{ fill('teacher_request_title', course.fullname) }}
        </h5>
        <p
          :id="ids.note"
          class="adele-access-request-note"
        >
          {{ canSend || loading ? strings.teacher_request_note : strings.teacher_request_nobody }}
        </p>
        <p
          v-if="loading"
          role="status"
        >
          {{ strings.teacher_request_loading }}
        </p>
        <template v-else-if="data">
          <div
            v-if="canSend"
            class="mb-2"
          >
            <label
              :for="ids.recipient"
              class="adele-access-request-label"
            >{{ strings.teacher_request_recipient }}</label>
            <select
              v-if="recipients.length > 1"
              :id="ids.recipient"
              v-model.number="recipientid"
              class="form-select custom-select"
              data-testid="access-request-recipient"
            >
              <option
                v-for="person in recipients"
                :key="person.id"
                :value="Number(person.id)"
              >
                {{ person.fullname }}
              </option>
            </select>
            <output
              v-else
              :id="ids.recipient"
              class="d-block"
              data-testid="access-request-recipient"
            >{{ recipients[0].fullname }}</output>
          </div>
          <!-- eslint-disable vue/no-v-html -->
          <div
            v-else-if="data.fallback"
            class="adele-access-request-fallback"
            data-testid="access-request-fallback"
            v-html="data.fallback"
          />
          <!-- eslint-enable vue/no-v-html -->
          <label
            :for="ids.message"
            class="adele-access-request-label"
          >{{ strings.teacher_request_message }}</label>
          <textarea
            :id="ids.message"
            ref="field"
            v-model="text"
            class="form-control"
            rows="6"
            :maxlength="data.maxlength || 4000"
            data-testid="access-request-message"
          />
        </template>
        <p
          v-if="error"
          role="alert"
          class="adele-access-request-error"
          data-testid="access-request-error"
        >
          {{ error }}
        </p>
        <p
          v-if="copied"
          role="status"
        >
          {{ strings.teacher_request_copied }}
        </p>
        <div class="adele-access-request-actions">
          <button
            v-if="canSend"
            type="button"
            class="btn btn-primary"
            :disabled="sending || !text.trim()"
            data-testid="access-request-send"
            @click="send"
          >
            {{ strings.teacher_request_send }}
          </button>
          <button
            v-else-if="data"
            type="button"
            class="btn btn-primary"
            data-testid="access-request-copy"
            @click="copy"
          >
            {{ strings.teacher_request_copy }}
          </button>
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="sending"
            data-testid="access-request-cancel"
            @click="cancel"
          >
            {{ canSend ? strings.teacher_request_cancel : strings.teacher_request_close }}
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
.adele-access-request-label {
  font-weight: 600;
  display: block;
  margin: 0.5rem 0 0.25rem;
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
.adele-access-request-actions .btn:focus-visible,
.adele-access-request textarea:focus-visible,
.adele-access-request select:focus-visible {
  outline: 3px solid #0f6cbf;
  outline-offset: 2px;
}
</style>
