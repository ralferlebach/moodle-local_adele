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
 * Live markers and the two counters of one node (#14, #15), teachers' overview.
 *
 *   active      learners seen in one of the node's courses within the
 *               activity's window - only while the activity shows live markers;
 *   completed   learners who completed the node.
 * A counter opens a list of names, a page at a time; a name - or a marker -
 * opens that learner's path, centred on this node. Escape closes the list,
 * the focus goes back to the counter. All data comes from the server's
 * snapshot (store.state.livenodes); this component only shows it.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div
    v-if="node"
    class="adele-node-live nodrag nopan"
    :data-testid="'adele-node-live-' + nodeid"
    @mousedown.stop
    @click.stop
  >
    <div class="adele-node-counters d-flex flex-wrap">
      <button
        v-if="enabled"
        ref="activeButton"
        type="button"
        class="btn btn-sm btn-outline-success mr-1 mb-1 adele-counter-active"
        :aria-label="fill(strings.live_counter_active_label, { node: nodename, count: node.active })"
        :aria-expanded="openKind === 'active' ? 'true' : 'false'"
        :aria-controls="openKind === 'active' ? popoverId : undefined"
        :data-testid="'adele-counter-active-' + nodeid"
        @click="toggle('active')"
      >
        {{ fill(strings.live_counter_active, node.active) }}
      </button>
      <button
        ref="completedButton"
        type="button"
        class="btn btn-sm btn-outline-primary mb-1 adele-counter-completed"
        :aria-label="fill(strings.live_counter_completed_label, { node: nodename, count: node.completed })"
        :aria-expanded="openKind === 'completed' ? 'true' : 'false'"
        :aria-controls="openKind === 'completed' ? popoverId : undefined"
        :data-testid="'adele-counter-completed-' + nodeid"
        @click="toggle('completed')"
      >
        {{ fill(strings.live_counter_completed, node.completed) }}
      </button>
    </div>
    <div
      v-if="enabled && node.markers.length"
      class="adele-node-markers d-flex flex-wrap align-items-center"
    >
      <button
        v-for="marker in node.markers"
        :key="marker.userid"
        type="button"
        :class="['adele-marker', 'adele-marker-' + marker.state, { 'adele-marker-ambiguous': marker.ambiguous > 1 }]"
        :aria-label="markerLabel(marker)"
        :title="markerLabel(marker)"
        :data-testid="'adele-marker-' + nodeid + '-' + marker.userid"
        :data-state="marker.state"
        :data-ambiguous="marker.ambiguous > 1 ? marker.ambiguous : null"
        @click="openPerson(marker.userid, 'adele-marker-' + nodeid + '-' + marker.userid)"
      >
        <img
          :src="marker.avatar"
          alt=""
          class="adele-marker-avatar"
        >
        <span class="adele-marker-name">{{ marker.fullname }}</span>
        <span class="sr-only">{{ stateText(marker) }}</span>
        <span
          class="adele-marker-state"
          aria-hidden="true"
        >{{ marker.state === 'active' ? '\u25CF' : '\u25CB' }}</span>
        <span
          v-if="marker.ambiguous > 1"
          class="adele-marker-ambiguous-count"
          aria-hidden="true"
        >?{{ marker.ambiguous }}</span>
      </button>
      <button
        v-if="node.moremarkers > 0"
        type="button"
        class="adele-marker-more btn btn-link btn-sm p-0"
        :aria-label="fill(strings.live_more_label, node.moremarkers)"
        :data-testid="'adele-marker-more-' + nodeid"
        @click="toggle('active')"
      >
        {{ fill(strings.live_more, node.moremarkers) }}
      </button>
    </div>
    <div
      v-if="openKind"
      :id="popoverId"
      class="adele-live-popover card shadow"
      role="dialog"
      :aria-labelledby="popoverId + '-title'"
      :data-testid="'adele-live-popover-' + nodeid"
      @keydown.esc.stop.prevent="close"
    >
      <div class="card-body p-2">
        <h5
          :id="popoverId + '-title'"
          class="h6"
        >
          {{ fill(openKind === 'active' ? strings.live_people_active : strings.live_people_completed, nodename) }}
        </h5>
        <p
          role="status"
          class="small mb-1"
        >
          <span v-if="failed">{{ strings.live_error }}</span>
          <span v-else-if="!loading && !people.length">{{ strings.live_people_empty }}</span>
        </p>
        <ul
          v-if="people.length"
          class="list-unstyled mb-1"
        >
          <li
            v-for="(person, index) in people"
            :key="person.userid"
          >
            <button
              :ref="(el) => { if (index === 0) firstName = el }"
              type="button"
              class="btn btn-link btn-sm p-0 text-left"
              :aria-label="fill(strings.live_people_open, person.fullname)"
              data-testid="adele-live-person"
              @click="openPerson(person.userid, 'adele-counter-' + openKind + '-' + nodeid)"
            >
              {{ person.fullname }}
            </button>
          </li>
        </ul>
        <div
          v-if="pages > 1"
          class="d-flex justify-content-between align-items-center"
        >
          <button
            type="button"
            class="btn btn-sm btn-secondary"
            :disabled="page === 0"
            @click="loadPage(page - 1)"
          >
            {{ strings.live_people_prev }}
          </button>
          <span class="small">{{ fill(strings.live_people_page, { page: page + 1, pages }) }}</span>
          <button
            type="button"
            class="btn btn-sm btn-secondary"
            :disabled="page >= pages - 1"
            @click="loadPage(page + 1)"
          >
            {{ strings.live_people_next }}
          </button>
        </div>
        <button
          type="button"
          class="btn btn-sm btn-secondary mt-1"
          @click="close"
        >
          {{ strings.modals_close }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { useVueFlow } from '@vue-flow/core';
import { appDomId } from '../../composables/domIds';
import { fill } from '../../composables/groupProgress';

const props = defineProps({
  nodeid: { type: String, required: true },
  nodename: { type: String, default: '' },
});

const store = useStore();
const router = useRouter();
// The flow this node is drawn in (inside VueFlow; absent in isolation).
let flow = null;
try {
  flow = useVueFlow();
} catch (e) {
  flow = null;
}
const strings = computed(() => store.state.strings || {});
const node = computed(() => (store.state.livenodes || {})[props.nodeid] || null);
const enabled = computed(() => !!(store.state.livesnapshot && store.state.livesnapshot.enabled));
const popoverId = computed(() => appDomId(store, 'live-people-' + props.nodeid));
const openKind = ref(null);
const people = ref([]);
const total = ref(0);
const perpage = ref(20);
const page = ref(0);
const loading = ref(false);
const failed = ref(false);
const activeButton = ref(null);
const completedButton = ref(null);
let firstName = null;
const pages = computed(() => Math.max(1, Math.ceil(total.value / perpage.value)));

// Inactive with a last activity that is not one node (#14): the marker is on
// every such node and says so - no node is picked.
const stateText = (marker) => {
  if (marker.state === 'active') {
    return strings.value.live_state_active;
  }
  return marker.ambiguous > 1
    ? fill(strings.value.live_state_inactive_ambiguous, marker.ambiguous)
    : strings.value.live_state_inactive;
};
const markerLabel = (marker) => fill(strings.value.live_marker_label, { name: marker.fullname, state: stateText(marker) });

/**
 * Load a page of names.
 *
 * @param {number} number From 0.
 */
async function loadPage(number) {
  loading.value = true;
  failed.value = false;
  try {
    const result = await store.dispatch('fetchNodePeople', { nodeid: props.nodeid, kind: openKind.value, page: number });
    people.value = result.people;
    total.value = result.total;
    perpage.value = result.perpage;
    page.value = result.page;
  } catch (e) {
    failed.value = true;
    people.value = [];
  } finally {
    loading.value = false;
  }
  await nextTick();
  if (firstName && typeof firstName.focus === 'function') {
    firstName.focus();
  }
}

/**
 * Open the list of a counter, or close it.
 *
 * @param {string} kind 'active' or 'completed'.
 */
async function toggle(kind) {
  if (openKind.value === kind) {
    close();
    return;
  }
  openKind.value = kind;
  people.value = [];
  await loadPage(0);
}

/**
 * Close the list; the focus goes back to its counter.
 */
function close() {
  const kind = openKind.value;
  openKind.value = null;
  people.value = [];
  nextTick(() => {
    const button = kind === 'active' ? activeButton.value : completedButton.value;
    if (button && typeof button.focus === 'function') {
      button.focus();
    }
  });
}

/**
 * Open a learner's path, centred on this node.
 *
 * The way back is remembered (#15 AK3.5): the overview's viewport and scroll
 * position, and the control that led here - a marker, or the counter whose
 * list held the name - to be focused again when the teacher comes back.
 *
 * @param {number} userid
 * @param {string|null} origin data-testid of the control to focus on return.
 */
function openPerson(userid, origin = null) {
  store.commit('setReturnPoint', {
    learningpathid: store.state.learningPathID,
    viewport: flow && flow.viewport && flow.viewport.value ? { ...flow.viewport.value } : null,
    scrolly: typeof window !== 'undefined' ? window.scrollY : 0,
    focus: origin,
  });
  router.push({
    name: 'userDetails',
    params: { learningpathId: store.state.learningPathID, userId: userid },
    query: { node: props.nodeid },
  });
}

defineExpose({ toggle, close, openPerson });
</script>

<style scoped>
.adele-node-live {
  position: relative;
  margin-top: 0.25rem;
}
.adele-marker {
  display: inline-flex;
  align-items: center;
  border: 2px solid #1e7e34;
  border-radius: 1rem;
  background: #fff;
  padding: 0 0.4rem 0 0;
  margin: 0 0.25rem 0.25rem 0;
  font-size: 0.75rem;
}
.adele-marker-inactive {
  border: 2px dashed #6c757d;
  color: #495057;
}
.adele-marker-ambiguous {
  border-style: dotted;
}
.adele-marker-ambiguous-count {
  margin-left: 0.15rem;
  font-weight: bold;
}
.adele-marker-avatar {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  margin-right: 0.25rem;
}
.adele-marker-state {
  margin-left: 0.25rem;
}
.adele-live-popover {
  position: absolute;
  z-index: 2000;
  min-width: 14rem;
  top: 100%;
  left: 0;
}
</style>
