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
 * VueFlow's default (bezier) and smoothstep edges, with a page-unique DOM id (#18).
 *
 * VueFlow writes the edge's id as the id of its path element. Two ADELE
 * applications that show the same learning path - two activities on one
 * course page - then repeat ids such as "dndnode_2dndnode_1". This edge draws
 * the same bezier line and prefixes only the DOM id; the edge's own id in the
 * graph (data-id, the saved path) stays as it is.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { BaseEdge, getBezierPath, getSmoothStepPath } from '@vue-flow/core'
import { computed } from 'vue'
import { useStore } from 'vuex'
import { appDomId } from '../../composables/domIds'

const props = defineProps({
  id: { type: String, required: true },
  sourceX: { type: Number, required: true },
  sourceY: { type: Number, required: true },
  targetX: { type: Number, required: true },
  targetY: { type: Number, required: true },
  sourcePosition: { type: String, required: true },
  targetPosition: { type: String, required: true },
  markerEnd: { type: String, required: false, default: undefined },
  markerStart: { type: String, required: false, default: undefined },
  style: { type: Object, required: false, default: undefined },
  interactionWidth: { type: Number, required: false, default: 20 },
  // 'bezier' (VueFlow's default edge) or 'smoothstep' (the mobile view).
  shape: { type: String, required: false, default: 'bezier' },
})

const store = useStore()
const domId = computed(() => appDomId(store, props.id))
const path = computed(() => (props.shape === 'smoothstep' ? getSmoothStepPath(props) : getBezierPath(props)))
</script>

<script>
export default {
  inheritAttrs: false,
}
</script>

<template>
  <BaseEdge
    :id="domId"
    :path="path[0]"
    :marker-end="markerEnd"
    :marker-start="markerStart"
    :style="style"
    :interaction-width="interactionWidth"
  />
</template>
