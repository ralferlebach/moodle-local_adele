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
 * Date Info component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div>
    <div
      v-if="date.start"
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar mr-2" />
          {{ store.state.strings.nodes_items_start }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.start) }}
      </div>
    </div>
    <div
      v-if="date.end"
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar mr-2" />
          {{ store.state.strings.nodes_items_end }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.end) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { useStore } from 'vuex';
  const store = useStore();

  interface StartEndDate {
    start: string;
    end: string;
  }

  const props = defineProps<{
    date: StartEndDate;
  }>();

  const formatDate = (dateString: string): string  => {
    const options: Intl.DateTimeFormatOptions =
      {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: false
      };
    const formattedDate = new Date(dateString).toLocaleString('en-US', options);
    return formattedDate;
  }
</script>
