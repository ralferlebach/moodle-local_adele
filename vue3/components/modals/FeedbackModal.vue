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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div>
    <div
      :id="ids.dialog"
      class="modal fade"
      tabindex="-1"
      role="dialog"
      :aria-labelledby="ids.title"
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-lg"
        role="document"
      >
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5
              :id="ids.title"
              class="modal-title"
            >
              {{ store.state.strings.modals_edit_feedback }}
            </h5>
            <button
              type="button"
              class="close text-white"
              data-dismiss="modal"
              :aria-label="store.state.strings.modals_close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <div
                :id="ids.content"
                ref="contentEl"
                contenteditable="true"
                role="textbox"
                aria-multiline="true"
                :aria-labelledby="ids.title"
                class="form-control mt-2 p-2 border"
                @keyup="adjustContenteditableHeight"
                v-html="initialFeedback"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-dismiss="modal"
            >
              {{ store.state.strings.modals_close }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              @click="saveChanges"
            >
              {{ store.state.strings.modals_save_changes }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, onBeforeUnmount } from 'vue';
import { DIALOGS, dialogInstanceId } from '../../composables/domIds';
import { useStore } from 'vuex';

// Load Store
const store = useStore();
// Page-unique ids (#18). The dialog used to name a label id that did not
// exist (feedbackModalLabel) while its title carried the node dialog's id.
// One id per instance of this dialog in the application (#18 AK2): the
// first is the one the openers name, a further one gets a number.
const dialogId = dialogInstanceId(store, DIALOGS.feedback);
onBeforeUnmount(dialogId.release);
const ids = computed(() => ({
  dialog: dialogId.base,
  title: `${dialogId.base}-title`,
  content: `${dialogId.base}-content`,
}));

const initialFeedback = ref(null);
// The editable element. (Its template ref used to share the name of a computed
// value, so saving read innerHTML of a string; handleInput did not exist.)
const contentEl = ref(null);
const learningpathfeedback= ref({})

const props = defineProps({
  learningpath: {
    type: Object,
    default: () => ({}),
  }
});

// updating changes and closing modal
const saveChanges = () => {
  // Loop over nodes and macht node
  const cleanedHtml = cleanFeedback(contentEl.value ? contentEl.value.innerHTML : '')
  if (learningpathfeedback.value.json) {
    learningpathfeedback.value.json.tree.nodes.forEach((node) => {
      if (node.id == store.state.node.node_id) {
        // Find the feedback node.
        node.completion.nodes.forEach((completionnode) => {
          if (completionnode.type == 'feedback' &&
            completionnode.data.childCondition == store.state.feedback.childCondition) {
              completionnode.data.feedback = cleanedHtml;
            }
          })
        }
      });
    store.dispatch('saveLearningpath', learningpathfeedback.value)
    store.state.feedback = null
  }
};

const cleanFeedback = (feedback) => {
  feedback = feedback.replace(/(id|style)="[^"]*"\s*/g, '');
  return feedback.replace(/(<span\s*\/?>|<\/span>)/gi, '');
};

const adjustContenteditableHeight = () => {
  if (!contentEl.value) {
    return;
  }
  contentEl.value.style.height = 'auto';
  contentEl.value.style.height = `${contentEl.value.scrollHeight}px`;
};

onMounted(() => {
  learningpathfeedback.value = props.learningpath
});

</script>