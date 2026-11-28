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
 * A learner's rankings in the own groups (#12): per own group the members
 * by progress, plain names (nothing to click, no ids); in visible groups the
 * own group's average above the list and the other groups' averages after.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div
    v-if="error || (overview && overview.groupmode > 0)"
    class="adele-group-ranking mt-3"
    data-testid="adele-group-ranking"
  >
    <p
      v-if="error"
      class="alert alert-warning"
      role="status"
    >
      {{ strings.group_error }}
    </p>
    <p
      v-else-if="overview && !overview.rankings.length && !overview.closed"
      class="text-muted"
    >
      {{ strings.group_nogroup }}
    </p>
    <section
      v-for="ranking in (overview ? overview.rankings : [])"
      :key="ranking.groupid"
      class="mb-3"
      :aria-labelledby="headingId(ranking.groupid)"
      :data-testid="'adele-group-ranking-' + ranking.groupid"
    >
      <h4
        :id="headingId(ranking.groupid)"
        class="h6"
      >
        {{ fill(strings.group_ranking_heading, ranking.groupname) }}
      </h4>
      <p
        v-if="ownAverage(ranking.groupid)"
        class="adele-own-average font-weight-bold"
        :data-testid="'adele-own-average-' + ranking.groupid"
      >
        {{ fill(strings.group_average_own, ranking.groupname) }}:
        {{ valueText(ownAverage(ranking.groupid)) }}
      </p>
      <ol class="list-unstyled adele-ranking-list">
        <li
          v-for="(member, index) in ranking.members"
          :key="index"
          class="adele-ranking-row d-flex align-items-center mb-1"
          :class="{ 'adele-ranking-self': member.self }"
          data-testid="adele-ranking-row"
        >
          <span class="adele-ranking-rank mr-2">{{ fill(strings.group_rank, member.rank) }}</span>
          <span class="adele-ranking-name mr-2">
            {{ member.fullname + (member.self ? ' ' + strings.group_you : '') }}
          </span>
          <span
            class="progress flex-grow-1"
            role="progressbar"
            :aria-valuenow="member.progress"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="member.fullname"
            :aria-valuetext="fill(strings.group_progress_value, member.progress)"
          >
            <span
              class="progress-bar"
              :style="{ width: member.progress + '%' }"
            />
          </span>
          <span class="ml-2 adele-ranking-percent">{{ member.progress }} %</span>
        </li>
      </ol>
    </section>
    <GroupAverages
      :averages="otherAverages"
      :heading="strings.group_other_groups"
      id-name="group-other-averages"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useStore } from 'vuex';
import { appDomId } from '../../composables/domIds';
import { averageText, fill } from '../../composables/groupProgress';
import GroupAverages from './GroupAverages.vue';

const store = useStore();
const strings = computed(() => store.state.strings || {});
const overview = computed(() => store.state.groupoverview);
const error = ref(false);
const ownAverage = (groupid) => (overview.value.averages || []).find((row) => row.own && row.groupid === groupid);
const otherAverages = computed(() => ((overview.value && overview.value.averages) || []).filter((row) => !row.own));
const headingId = (groupid) => appDomId(store, 'group-ranking-' + groupid);
const valueText = (row) => averageText(row, strings.value);

onMounted(async () => {
  try {
    await store.dispatch('fetchGroupOverview');
  } catch (e) {
    error.value = true;
  }
});
</script>
