<template>
  <rect
    class="vue-flow__minimap-node"
    :class="{ selected, dragging }"
    :x="position.x"
    :y="position.y"
    :rx="borderRadius"
    :ry="borderRadius"
    :width="dimensions.width"
    :height="dimensions.height"
    :fill="color"
    :stroke="strokeColor"
    :stroke-width="strokeWidth"
    :shape-rendering="shapeRendering"
  />
</template>

<script setup>
/**
 * A node of the minimap, drawn as VueFlow draws it - without an id.
 *
 * VueFlow's own minimap node gives the rectangle the node's id ("dndnode_1"),
 * which every ADELE application on a page has: two paths on one course page
 * shown with a minimap (wider than 768 px, e.g. in the Classic theme) put the
 * same id on the page twice (local_adele #18 AK7, found 2026-11-23). The id
 * is declared as a prop so it is not passed on to the rectangle.
 */
defineProps({
  id: { type: String, default: '' },
  type: { type: String, default: '' },
  selected: { type: Boolean, default: false },
  dragging: { type: Boolean, default: false },
  position: { type: Object, required: true },
  dimensions: { type: Object, required: true },
  borderRadius: { type: [Number, String], default: 5 },
  color: { type: String, default: '' },
  shapeRendering: { type: String, default: '' },
  strokeColor: { type: String, default: '' },
  strokeWidth: { type: [Number, String], default: 2 },
})
</script>
