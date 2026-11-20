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
 * The group a teacher looks at (#12, mod_adele #16).
 *
 * Offers what the server allows (the activity's contract): the groups the
 * teacher may choose, "all participants" only where allowed. A choice is
 * checked again on the server with every request.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div
    v-if="visible"
    class="adele-group-selector form-inline mb-2"
    data-testid="adele-group-selector"
  >
    <label
      :for="selectId"
      class="mr-2"
    >
      {{ strings.group_select_label }}
    </label>
    <select
      :id="selectId"
      class="custom-select"
      :value="store.state.groupid"
      @change="choose($event.target.value)"
    >
      <option
        v-if="overview.allowall"
        :value="0"
      >
        {{ strings.group_all_participants }}
      </option>
      <option
        v-for="group in overview.groups"
        :key="group.id"
        :value="group.id"
      >
        {{ group.name }}
      </option>
    </select>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { appDomId } from '../../composables/domIds';

const store = useStore();
const emit = defineEmits(['changed']);
const strings = computed(() => store.state.strings || {});
const overview = computed(() => store.state.groupoverview || { groups: [], groupmode: 0, allowall: false });
const visible = computed(() => overview.value.groupmode > 0 && (overview.value.groups.length > 0 || overview.value.allowall));
const selectId = computed(() => appDomId(store, 'group-selector'));

/**
 * Show another group: the participant list, the averages and the live data follow.
 *
 * @param {string} value
 */
async function choose(value) {
  store.commit('setGroupId', Number(value));
  emit('changed', Number(value));
}
</script>
