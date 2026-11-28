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
 * The other enrolled people of the course (#13), on a teacher's request.
 *
 * Loaded only when the teacher opens the section; separate from the
 * participant list, the rankings, the counters and the averages. Nothing is
 * created for these people and no progress is shown for them - they have none.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div
    class="adele-non-learners mt-2"
    data-testid="adele-non-learners"
  >
    <button
      type="button"
      class="btn btn-secondary btn-sm"
      :aria-expanded="open ? 'true' : 'false'"
      :aria-controls="open ? sectionId : undefined"
      data-testid="adele-non-learners-toggle"
      @click="toggle"
    >
      {{ open ? strings.nonlearners_hide : strings.nonlearners_show }}
    </button>
    <section
      v-if="open"
      :id="sectionId"
      class="mt-2"
      :aria-labelledby="headingId"
      data-testid="adele-non-learners-section"
    >
      <h4
        :id="headingId"
        class="h6"
      >
        {{ strings.nonlearners_heading }}
      </h4>
      <p
        role="status"
        class="mb-1"
      >
        <span v-if="loading">{{ strings.nonlearners_loading }}</span>
        <span
          v-else-if="failed"
          class="text-danger"
        >{{ strings.nonlearners_error }}</span>
        <span v-else-if="!people.length">{{ strings.nonlearners_none }}</span>
      </p>
      <ul
        v-if="people.length"
        class="list-unstyled"
      >
        <li
          v-for="person in people"
          :key="person.userid"
          data-testid="adele-non-learner"
          :data-reason="person.reason"
        >
          {{ person.fullname }} -
          <span class="text-muted">{{ reasonText(person) }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useStore } from 'vuex';
import { appDomId } from '../../composables/domIds';
import { fill } from '../../composables/groupProgress';

const store = useStore();
const strings = computed(() => store.state.strings || {});
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const people = ref([]);
const sectionId = computed(() => appDomId(store, 'non-learners'));
const headingId = computed(() => appDomId(store, 'non-learners-heading'));

const reasonText = (person) => (person.reason === 'nonlearner'
  ? fill(strings.value.nonlearners_reason_nonlearner, person.roles)
  : strings.value.nonlearners_reason_nopath);

// Number of the latest request: only its answer counts. Group changes in a
// row send several requests, and their answers can arrive in any order - a
// late answer for an earlier group used to overwrite the list of the group
// chosen last (found in E2E G5, 2026-11-26).
let latest = 0;

/**
 * Load the list for the group shown.
 */
async function load() {
  const request = ++latest;
  loading.value = true;
  failed.value = false;
  try {
    const answer = await store.dispatch('fetchNonLearners');
    if (request === latest) {
      people.value = answer;
    }
  } catch (e) {
    if (request === latest) {
      failed.value = true;
      people.value = [];
    }
  } finally {
    if (request === latest) {
      loading.value = false;
    }
  }
}

/**
 * Open (and load) or close the section. Closed, the list is dropped.
 */
async function toggle() {
  open.value = !open.value;
  if (open.value) {
    await load();
  } else {
    // A request still under way answers into nothing.
    latest++;
    loading.value = false;
    people.value = [];
  }
}

// Another group: an open list follows it.
watch(() => store.state.groupid, () => {
  if (open.value) {
    load();
  }
});
</script>
