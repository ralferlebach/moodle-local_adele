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
 * specific course component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="form-check">
    {{ restriction.description }}
    <div class="form-group">
      <label
        class="form-label"
        for="courseSelect"
      >
        {{ store.state.strings.restriction_select_course }}
      </label>
      <select
        id="courseSelect"
        v-model="selectedCourse"
        class="form-select"
      >
        <option
          :value="null"
          disabled
        >
          {{ store.state.strings.restriction_select_course }}
        </option>
        <option
          v-for="course in courses"
          :key="course.id"
          :value="course.id"
        >
          {{ course.name }}
        </option>
      </select>
      <div
        v-if="selectedCourse === null || selectedCourse === undefined"
        class="adele-condition-warning"
      >
        {{ store.state.strings.restriction_no_node_warning }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

// Load Store
const store = useStore();
const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  restriction: {
    type: Object,
    required: true,
  }
})
const data = ref([])
const courses = ref([])
const selectedCourse = ref(null)
const emit = defineEmits(['update:modelValue'])

onMounted(async () => {
  store.state.learningpath.json.tree.nodes.forEach(node => {
    // Exclude the node itself - it must not require its own completion (#451).
    // node_id (not id) is how the current node is identified everywhere else,
    // so the previous store.state.node.id never matched and self was selectable.
    if (store.state.node.node_id != node.id) {
      courses.value.push({
        id: node.id,
        name: node.data.fullname
      });
    }
  })
  // Set selectedCourse to the value of props.restriction.value
  if (props.restriction.value !== undefined) {
    selectedCourse.value = props.restriction.value.courseid;
  }
});

// Watch for changes in selectedCourse
watch(() => selectedCourse.value, async () => {
  data.value = {
    courseid: selectedCourse.value,
  }
}, { deep: true });

// Watch for changes in data and emit the update
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true });

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

.form-select,
.form-control {
  width: 100%; /* Make the inputs fill their container */
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}

.form-select {
  max-width: 100%; /* Set a maximum width for the select */
}

.adele-condition-warning {
  margin-top: 6px;
  color: #b94a48;
  font-size: 13px;
  font-weight: bold;
}

/* Add any additional styling as needed */
</style>