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
 * Completion Out Put Item component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="col 12 mt-2">
    <div 
      v-for="output_item in output_items" 
      :key="output_item"
    >
      <component 
        :is="getOutputLabel(output_item)" 
        v-model="data.manualcompletionvalue" 
        :data="data"
      />
    </div>
  </div>
</template>

<script setup>
import manual_output from './conditions_output/manual_output.vue'

const props = defineProps({
  data: {
    type: Object,
    default: null,
  },
});
const output_items = ['manual'];

const dynamicComponent = (output_item) => {
  switch (output_item) {
    case 'manual':
      return manual_output;
    default:
      return null;
  }
};

const getOutputLabel = (output_item) => {
  if (props.data) {
    return dynamicComponent(output_item)
  }else{
    return null;
  } 
};

</script>
