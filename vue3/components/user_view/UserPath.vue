<template>
  <div>
    <notifications width="100%" />
    <div>
      <button
        v-if="store.state.view!='student'"
        class="btn btn-outline-primary"
        @click="goBack"
      >
        <i class="fas fa-arrow-left" /> {{ store.state.strings.user_view_go_back_overview }}
      </button>
      <h2
        v-if="store.state.view!='student'"
        class="mt-3"
      >
        {{ store.state.strings.user_view_user_path_for }}
      </h2>
      <div
        v-if="user_learningpath"
        class="card"
      >
        <div v-if="store.state.view!='student'">
          <div class="card-body">
            <h5 class="card-title">
              <i
                :class="store.state.version ? 'fas fa-user-circle' : 'fas fa-user'"
              />
              {{ user_learningpath.username }}
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">
                <i class="fas fa-user" /> {{ store.state.strings.user_view_firstname }}: {{ user_learningpath.firstname }}
              </li>
              <li class="list-group-item">
                <i class="fas fa-user" /> {{ store.state.strings.user_view_lastname }}: {{ user_learningpath.lastname }}
              </li>
              <li class="list-group-item">
                <i class="fas fa-envelope" /> {{ store.state.strings.user_view_email }}: {{ user_learningpath.email }}
              </li>
            </ul>
          </div>
        </div>
        <div
          ref="flowContainer"
          class="adele-flow-container"
          @wheel="onWheel($event, zoomLockVaraible, viewport, zoomTo)"
        >
          <VueFlow
            :nodes="nodes"
            :edges="edges"
            :viewport="viewport"
            :default-viewport="viewport"
            :fit-view-on-init="true"
            :max-zoom="1.55"
            :min-zoom="0.05"
            :zoom-on-scroll="false"
            :zoom-on-pinch="false"
            class="learning-path-flow"
            @node-click="onNodeClickCall"
          >
            <Panel position="top-right">
              <div class="adele-view-controls">
                <button
                  type="button"
                  class="btn btn-light btn-sm adele-fit-btn"
                  :title="store.state.strings.fit_view"
                  v-tooltip="store.state.strings.fit_view"
                  @click="refitToView"
                >
                  <i class="fas fa-expand" />
                </button>
                <input
                  type="range"
                  class="adele-zoom-slider"
                  min="0.05"
                  max="1.55"
                  step="0.01"
                  :value="viewport.zoom"
                  :title="Math.round(viewport.zoom * 100) + '%'"
                  @input="(e) => zoomTo(parseFloat(e.target.value), { duration: 0 })"
                >
                <span class="adele-zoom-label">{{ Math.round(viewport.zoom * 100) }}%</span>
              </div>
            </Panel>
            <template #node-custom="{ data }">
              <CustomNodeEdit
                :data="data"
                :learningpath="user_learningpath"
                :zoomstep="zoomstep"
              />
            </template>
            <template
              #node-orcourses="{ data }"
            >
              <CustomStagNodeEdit
                :data="data"
                :learningpath="user_learningpath"
                :zoomstep="zoomstep"
                @expanding-cards="handleExpandCards"
              />
            </template>
            <template #node-module="{ data }">
              <ModuleNode
                :data="data"
                :zoomstep="zoomstep"
              />
            </template>
            <template #node-expandedcourses="{ data }">
              <ExpandNodeEdit
                :data="data"
                :zoomstep="zoomstep"
              />
            </template>
            <template #edge-custom="props">
              <TransitionEdge
                v-bind="props"
                @end-transition="handleZoomLock"
              />
            </template>
          </VueFlow>
        </div>
        <div
          v-if="store.state.view != 'student'"
          class="d-flex justify-content-center control-btns"
        >
          <Controls />
        </div>
      </div>
    </div>
  </div>
</template>

  <script setup>
  // Import needed libraries
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex';
import { VueFlow, useVueFlow, Panel } from '@vue-flow/core'
import TransitionEdge from '../edges/TransitionEdge.vue'
import CustomNodeEdit from '../nodes/CustomNodeEdit.vue'
import CustomStagNodeEdit from '../nodes/CustomStagNodeEdit.vue'
import ExpandNodeEdit from '../nodes/ExpandNodeEdit.vue'
import ModuleNode from '../nodes/ModuleNode.vue'
import Controls from '../user_view/UserControls.vue'
import drawModules from '../../composables/nodesHelper/drawModules'
import onNodeClick from '../../composables/flowHelper/onNodeClick';
import onWheel from '../../composables/flowHelper/onWheel';
import ExpandedCourses from '../nodes_items/ExpandedCourses.vue';

// Load Router
const router = useRouter()
const route = useRoute()

// Load Store
const store = useStore()

const {
  addNodes, removeNodes, findNode,
  zoomTo, viewport, setCenter, fitView
} = useVueFlow()

// The graph container; a ResizeObserver re-fits the path whenever its size changes
// (window resize, sidebar/user-list toggle) so all nodes stay reachable (#480).
const flowContainer = ref(null)
let resizeObserver = null
let fitTimeout = null
const refitToView = () => {
  clearTimeout(fitTimeout)
  fitTimeout = setTimeout(() => {
    if (nodes.value.length) {
      fitView({ padding: 0.2, duration: 400 })
    }
  }, 150)
}

const goBack = () => {
  router.go(-1)
}

const props = defineProps({
  userlearningpathparent: {
    type: Object,
    default: null,
  }
});

// Declare reactive variable for nodes
const nodes = ref([]);
const edges = ref([]);
const zoomstep = ref(0)
const zoomLockVaraible = ref(false)
const user_learningpath = ref({})

onMounted( async () => {
  if (!store.state.availablecourses) {
    store.dispatch('fetchAvailablecourses')
  }
  if(!props.user_learningpath_parent){
    let params = []
    if (store.state.view == 'student') {
      params = {
        learningpathId: store.state.learningPathID,
        userId: store.state.user,
      }
    }else {
      params = route.params
      // Remember which list row was opened so the user list can scroll back to it
      // when the teacher returns via "Close" (#481).
      store.commit('setFocusUser', route.params.userId)
    }
    user_learningpath.value  = await store.dispatch('fetchUserPathRelation', params)
  } else {
    user_learningpath.value = props.user_learningpath_parent
  }
  if(user_learningpath.value){
    setFlowchart()
    setTimeout(() => {
      nextTick().then(() => {
        // Fit the whole path into the viewport so every node is reachable, however
        // far the path spreads - replaces the old fixed-zoom setCenter that only
        // showed the middle of large paths (#480).
        fitView({ padding: 0.2, duration: 1000 })
      })
    }, 300)
  }
  // Re-fit on any container size change (window / sidebar / user-list toggle).
  if (flowContainer.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(refitToView)
    resizeObserver.observe(flowContainer.value)
  }
  if (store.state.user == store.state.lpuserpathrelation.user_id) {
    await store.dispatch('updateUserPathRelation', {
      lpuserpathid: store.state.lpuserpathrelation.id,
    });
  }
})

onUnmounted(() => {
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
  clearTimeout(fitTimeout)
})

const handleZoomLock = (node) => {
  nextTick(() => {
    let event = {
      node: null,
    }
    event.node = findNode(node)
    if (event.node) {
      zoomstep.value = onNodeClick(event, setCenter, store)
    }
  })
}

const handleExpandCards = async () => {
    await zoomTo(0.35, { duration: 500})
}

watch(() => user_learningpath.value, () => {
  setFlowchart()
}, { deep: true } )

// Set flowchart
function setFlowchart() {
  const flowchart = user_learningpath.value.json
  nodes.value = flowchart.tree.nodes;
  edges.value = flowchart.tree.edges;

  edges.value.forEach((edge) => {
    edge.deletable = false
    edge.type = 'custom'
  })

  setTimeout(() => {
    drawModules(user_learningpath.value, addNodes, removeNodes, findNode)
  }, 100);
}

// Zoom in node
function onNodeClickCall(event) {
  zoomstep.value = onNodeClick(event, setCenter, store);
  // Clicking one of a stack's expanded child cards (or its "i"/feedback icons)
  // must NOT auto-collapse the stack. Vue-flow fires node-click for the child
  // node too, and collapsing here would unmount the children and instantly
  // close the info window the user just opened (issue #454). Only auto-collapse
  // expanded stacks when the user clicks a different, non-child node.
  if (event && event.node && typeof event.node.id === 'string' &&
      event.node.id.includes('_expanded_courses_')) {
    return;
  }
  // Collapse any expanded stacks when navigating to another node.
  nextTick(() => {
    // Query all elements with the 'fa-minus-circle' class (a stack's collapse button)
    const buttons = document.querySelectorAll('.fa-minus-circle');

    // For each found button, simulate a click event
    buttons.forEach(button => {
        button.click();
    });
  });
}

</script>

<style>
.vue-flow__edge-layer {
  z-index: 0; /* Ensure edges are below */
}

.vue-flow__node-layer {
  z-index: 10; /* Ensure nodes are above */
}

.control-btns {
  height: 100px;
}
</style>

<style scoped>
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.adele-flow-container {
  width: 100%;
  /* Grow with the viewport so large paths get as much room as possible, and so the
     space freed by hiding the user list is used (#480). */
  height: 70vh;
  min-height: 450px;
}

.learning-path-flow {
  border-radius: 1rem;
}

.adele-view-controls {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 6px 8px;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.adele-zoom-slider {
  width: 100px;
  cursor: pointer;
}

.adele-zoom-label {
  font-size: 11px;
  color: #333;
}
</style>