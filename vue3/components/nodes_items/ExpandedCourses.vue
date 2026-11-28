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
 * Expanded Courses component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex';
import {
  expandedCourseLabel,
  expandedCourseNodeId,
  expandedCourseShortname,
  expandedCourseTitle,
} from '../../composables/nodesHelper/expandedCourse';


// load useVueFlow properties / functions
const { findNode, removeNodes, addNodes } = useVueFlow()
const store = useStore()

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits([
  'done-folding',
  'collapse',
]);

// Escape in a panel of this stack folds the stack; the stack's expand button
// takes the focus, so the keyboard does not lose its place (#17, TP-08). An
// element inside the panel that handles Escape itself (the information card,
// the course access list) stops the event first.
const onKeydown = (event) => {
  if (event.key !== 'Escape' || event.defaultPrevented) {
    return
  }
  const active = document.activeElement
  const wrapper = active && active.closest ? active.closest('.vue-flow__node') : null
  if (!wrapper) {
    return
  }
  const own = (props.data.course_node_id || []).map((id) => expandedCourseNodeId(props.data.node_id, id))
  if (own.includes(wrapper.dataset.id)) {
    event.preventDefault()
    emit('collapse')
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  const expandedNode = findNode(props.data.node_id)
  if (!expandedNode) return

  props.data.course_node_id.forEach((node_id, index) => {
    // Copy the position object to avoid modifying the original node's position
    const position = {
      x: expandedNode.position.x + (index + 1) * 500,
      y: expandedNode.position.y,
    }
    const nodeData = JSON.parse(JSON.stringify(expandedNode.data))
    nodeData.course_id = node_id // Add course_id to the copied data
    nodeData.showCard = false

    // Identity by the real course id, readable by the course name (#17).
    const title = expandedCourseTitle(expandedNode.data, node_id, store.state.availablecourses)
    const shortname = expandedCourseShortname(expandedNode.data, node_id, store.state.availablecourses)
    const newnode = {
      id: expandedCourseNodeId(props.data.node_id, node_id),
      type: 'expandedcourses',
      ariaLabel: expandedCourseLabel(title, node_id, store.state.strings, shortname),
      class: `adele-expanded-course adele-expanded-course-${node_id}`,
      data: nodeData,
      draggable: false,
      deletable: false,
      position,
    }
    setTimeout(() => {
      newnode.zIndex = 9999;
      addNodes([newnode])
      if (index === props.data.course_node_id.length - 1) {
        emit('done-folding');
      }
    }, index * 300)
  })
})

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  let remove_nodes = []
  let reversed_course_node_id = [...props.data.course_node_id].reverse();
  reversed_course_node_id.forEach((node_id, index) => {
    remove_nodes.push(expandedCourseNodeId(props.data.node_id, node_id))
    let remove_node = findNode(expandedCourseNodeId(props.data.node_id, node_id))
    if (remove_node) {
      remove_node.deletable = true
      setTimeout(() => {
        remove_node.data.showCard = false
      }, index * 300)
      setTimeout(() => {
        removeNodes([remove_node])
      }, (index+1) * 300)
      if (index === props.data.course_node_id.length - 1) {
          emit('done-folding');
        }
    }
  })
})
</script>

<template>
  <div>

  </div>
</template>
