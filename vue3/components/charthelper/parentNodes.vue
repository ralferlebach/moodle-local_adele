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
 * parent Nodes component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
  import { useStore } from 'vuex';
  const store = useStore()
  const props = defineProps({
      parentNodes: {
        type: Array,
        default: null,
      },
  });
</script>

<template>
  <div class="card-body">
    <h5 class="card-title">
      <i class="fas fa-arrow-circle-up" /> {{ store.state.strings.charthelper_parent_nodes }}
    </h5>
    <ul class="list-group list-group-flush">
      <template v-if="props.parentNodes.length > 0">
        <div 
          v-for="parent in props.parentNodes"
          :key="parent.data.fullname" 
        >
          <li class="list-group-item">
            {{ parent.data.fullname }}
          </li>
        </div>
      </template>
      <template v-else>
        <li class="list-group-item">
          {{ store.state.strings.charthelper_no_parent_nodes }}
        </li>
      </template>
    </ul>
  </div>
</template>
