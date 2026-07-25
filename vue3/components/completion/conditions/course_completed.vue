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
 * course completed component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="form-check">
    <div v-if="course_node.course_node_id && course_node.course_node_id.length == 1">
      {{ completion.description }}
    </div>
    <div v-else-if="course_node.course_node_id">
      <label
        class="form-label"
        :for="`completion-${completion.node_id}-min`"
      >
        {{ store.state.strings.course_completion_minimum_amount }}
      </label>
      <select
        :id="`completion-${completion.node_id}-min`"
        v-model="data.min_courses"
        :name="`completion-${completion.node_id}-min`"
        class="form-select"
        @change="emitSelectedParentCourse"
      >
        <option
          disabled
          value=""
          selected
        >
          {{ store.state.strings.course_completion_choose_number }}
        </option>
        <option
          v-for="number in nodeCourses"
          :key="number"
          :value="number"
        >
          {{ number }}
        </option>
      </select>
      / {{ course_node.course_node_id.length }}
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const course_node = ref({});
const nodeCourses = ref(0)
const emit = defineEmits(['update:modelValue'])

const data = ref({
  min_courses: 0,
});

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  completion: {
    type: Object,
    default: null,
  },
});

onMounted(() => {
  course_node.value = store.state.node;
  if (props.completion.value && props.completion.value.min_courses) {
    data.value.min_courses = props.completion.value.min_courses
  }
  nodeCourses.value = course_node.value.course_node_id.length
})

// watch values from selected node
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true } );

</script>

<style scoped>
.form-check {
  margin-bottom: 10px;
}

.form-group {
  margin-bottom: 15px;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-select {
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}
</style>