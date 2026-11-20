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
 * Group averages (#12, visible groups): the own group first and marked, the
 * others by name; a group without measured learners says "no data".
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <section
    v-if="averages.length"
    class="adele-group-averages mb-3"
    :aria-labelledby="headingId"
    data-testid="adele-group-averages"
  >
    <h4
      :id="headingId"
      class="h6"
    >
      {{ heading || strings.group_averages_heading }}
    </h4>
    <ul class="list-unstyled mb-0">
      <li
        v-for="row in averages"
        :key="row.groupid"
        :class="['adele-group-average', { 'font-weight-bold': row.own }]"
        :data-testid="'adele-group-average-' + row.groupid"
        :data-own="row.own ? 'true' : 'false'"
      >
        <span class="adele-group-average-name">{{ row.groupname }}</span>:
        <span class="adele-group-average-value">{{ valueText(row) }}</span>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { appDomId } from '../../composables/domIds';
import { averageText } from '../../composables/groupProgress';

const props = defineProps({
  averages: { type: Array, default: () => [] },
  heading: { type: String, default: '' },
  idName: { type: String, default: 'group-averages' },
});
const store = useStore();
const strings = computed(() => store.state.strings || {});
const headingId = computed(() => appDomId(store, props.idName));
const valueText = (row) => averageText(row, strings.value);
</script>
