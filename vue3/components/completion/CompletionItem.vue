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
 * Completion Item component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <component
    :is="dynamicComponent"
    v-model="completion_value"
    :completion="completion"
  />
</template>

<script setup>
import { computed } from 'vue';
import course_completed from './conditions/course_completed.vue'
import manual from './conditions/manual_check.vue'
import catquiz from './conditions/catQuiz.vue'
import modquiz from './conditions/modQuiz.vue'

const props = defineProps({
  completion: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits([
  'changevalues'
]);

const completion_value = computed({
  get() {
    return props.completion?.value;
  },
  set(newValue) {
    emit('changevalues', newValue);
  },
});


const dynamicComponent = computed(() => {
  switch (getInputLabel()) {
    case 'course_completed':
      return course_completed;
    case 'manual':
      return manual;
    case 'catquiz':
      return catquiz;
    case 'modquiz':
      return modquiz;
    default:
      return null;
  }
});

const getInputLabel = () => {
  // Map completion labels to input components
  const labelToComponent = {
    course_completed: 'course_completed',
    manual: 'manual',
    catquiz: 'catquiz',
    modquiz: 'modquiz',
  };
  return labelToComponent[props.completion.label] || 'manual';
};

</script>
