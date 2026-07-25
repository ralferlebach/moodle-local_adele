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
 * parent node completed component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="form-check">
    {{ restriction.description }}
    <div v-if="parentNodes">
      {{ store.state.strings.restriction_parents_found }}
      <div
        v-for="(value, key) in parentNodes"
        :key="key"
        class="card-text"
      >
        <div class="fullname-container">

          {{ truncatedText(value.name, 24) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useStore } from 'vuex';
import truncatedText from '../../../composables/nodesHelper/truncatedText';

const store = useStore()

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  restriction: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  }
})
const data = ref({
  node_id: [],
});

const parentNodes = ref([])
const emit = defineEmits(['update:modelValue'])

onMounted(() => {
  props.learningpath.json.tree.nodes.forEach(node => {
    if (
      node.childCourse &&
      node.childCourse.includes(store.state.node.node_id)
    ) {
      let fullname = node.data.fullname
      if (fullname == '') {
        fullname = store.state.strings.nodes_collection
      }
      parentNodes.value.push({
        id: node.id,
        name: fullname
      });
      data.value.node_id.push(node.id)
    }
  })
  emit('update:modelValue', data.value);
});
</script>

<style scoped>

.card-text {
  padding: 5px;
}

.fullname-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #f0f0f0;
  padding: 10px;
  border-radius: 10px;
  overflow: visible;
  white-space: nowrap;
  text-overflow: ellipsis;
}

</style>