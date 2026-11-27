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
    <div>
      <div v-if="learningpath">
        <TextInputs
          :goal="learningpath"
        />
        <GroupSelector @changed="groupChanged" />
        <p
          v-if="liveFailed"
          class="alert alert-warning"
          role="status"
        >
          {{ store.state.strings.live_error }}
        </p>
        <LearningPath :learningpath="learningpath" />
        <GroupAverages
          v-if="store.state.groupoverview"
          :averages="store.state.groupoverview.averages"
        />
        <UserList />
        <NonLearners />
      </div>
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { onMounted, onUnmounted, ref } from 'vue';
import { useStore } from 'vuex';
import LearningPath from '../flowchart/LearningPath.vue';
import TextInputs from '../charthelper/textInputs.vue';
import UserList from '../user_view/UserList.vue';
import GroupSelector from '../groups/GroupSelector.vue';
import GroupAverages from '../groups/GroupAverages.vue';
import NonLearners from '../groups/NonLearners.vue';
import { useUserPathRefresh } from '../../composables/useUserPathRefresh';
import { POLLING_ACTIVE } from '../../composables/flowHelper/pathViewSettings';

const store = useStore()
const learningpath = ref(null)
const liveFailed = ref(false)
let liveRefresh = null

/**
 * Live markers and node counters (#14, #15): one snapshot of the group
 * shown, then batched polling at the server's interval - no request per
 * node or person, none in parallel, paused while the page is hidden.
 */
async function startLive() {
  // The live data of one group: an answer arriving after another group was
  // chosen is dropped (found 2026-11-26 for the lists, E2E G5).
  const choice = store.state.groupchoice
  const current = () => store.state.groupchoice === choice
  let snapshot = null
  try {
    snapshot = await store.dispatch('fetchLiveSnapshot')
    if (!current()) {
      return
    }
    store.commit('setLiveSnapshot', snapshot)
    liveFailed.value = false
  } catch (e) {
    // No live data (an activity outside mod_adele, or a refusal): the view works without.
    if (current()) {
      store.commit('setLiveSnapshot', null)
    }
    return
  }
  liveRefresh = useUserPathRefresh({
    fetch: () => store.dispatch('fetchLiveSnapshot'),
    onResult: (result) => {
      if (!current()) {
        return
      }
      liveFailed.value = false
      store.commit('setLiveSnapshot', result)
    },
    settings: {
      pollingmode: POLLING_ACTIVE,
      pollinginterval: Math.max(10, Number(snapshot.pollinterval) || 30) * 1000,
      pollingdebounce: 1000,
    },
    onPersistentError: () => { liveFailed.value = true },
  })
  liveRefresh.start()
}

function stopLive() {
  if (liveRefresh) {
    liveRefresh.stop()
    liveRefresh = null
  }
}

/**
 * Another group: list, averages and live data follow it.
 */
async function groupChanged() {
  store.dispatch('fetchUserPathRelations')
  try {
    await store.dispatch('fetchGroupOverview')
  } catch (e) {
    // The list still shows; the averages stay away.
  }
  stopLive()
  await startLive()
}

onMounted( async () => {
  learningpath.value = await store.dispatch('fetchLearningpath')
  try {
    await store.dispatch('fetchGroupOverview')
  } catch (e) {
    // Outside an ADELE activity there are no groups.
    store.commit('setGroupOverview', null)
  }
  store.dispatch('fetchUserPathRelations')
  await startLive()
})

onUnmounted(() => {
  stopLive()
  store.commit('setLiveSnapshot', null)
})
</script>
