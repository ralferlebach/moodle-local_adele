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
 * User Path component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

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
        <!--
          The learner's graph is the one that most needs a linear equivalent:
          it carries the order of the courses and the conditions between them,
          and neither survives being read out node by node (#575 B3, way B).
        -->
        <div class="d-flex justify-content-end mt-2">
          <button
            type="button"
            class="btn btn-link"
            :aria-pressed="showOutline ? 'true' : 'false'"
            data-testid="learningpath-outline-toggle"
            @click="toggleOutline"
          >
            {{ showOutline ? store.state.strings.outline_hide : store.state.strings.outline_show }}
          </button>
        </div>

        <LearningPathOutline
          v-if="showOutline"
          :learningpath="user_learningpath"
        />

        <div
          v-show="!showOutline"
          ref="flowContainer"
          class="adele-flow-container"
          @wheel="onWheel($event, zoomLockVaraible, viewport, zoomTo)"
        >
          <VueFlow
            :key="flowKey"
            :nodes="nodes"
            :edges="edges"
            :viewport="viewport"
            :default-viewport="viewport"
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
                :key="nodeStateKey(data)"
                :data="data"
                :learningpath="user_learningpath"
                :zoomstep="zoomstep"
              />
            </template>
            <template
              #node-orcourses="{ data }"
            >
              <CustomStagNodeEdit
                :key="nodeStateKey(data)"
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
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
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
import LearningPathOutline from '../flowchart/LearningPathOutline.vue'
import onNodeClick from '../../composables/flowHelper/onNodeClick';
import onWheel from '../../composables/flowHelper/onWheel';
import ExpandedCourses from '../nodes_items/ExpandedCourses.vue';
import moodleStorage from 'core/localstorage';
import { viewportKey, saveViewport, loadViewport } from '../../composables/flowHelper/viewportStorage';
import { notify } from '@kyvg/vue3-notification';
import { parsePathViewSettings } from '../../composables/flowHelper/pathViewSettings';
import { useUserPathRefresh } from '../../composables/useUserPathRefresh';
import {
  autofocusMode, autofocusRole, compareUserPathStates, focusNodes, resolveAutofocusTarget,
} from '../../composables/flowHelper/autofocus';
import { rememberFocus, restoreFocusWhenReady } from '../../composables/flowHelper/keyboardFocus';
import { uniqueEdges } from '../../composables/flowHelper/normalizeEdges';
import { scopeMarkers } from '../../composables/domIds';

// Load Router
const router = useRouter()
const route = useRoute()

// Load Store
const store = useStore()

const {
  addNodes, removeNodes, findNode,
  zoomTo, viewport, setCenter, fitView, setViewport, onMoveStart, onMoveEnd
} = useVueFlow()

// Bumping this key remounts the whole VueFlow canvas - used by the async in-tab refresh to
// re-render the path cleanly (exactly like a page load), avoiding the mid-life corruption a
// wholesale nodes reassignment causes (stack/expand state, clickability) (#485).
const flowKey = ref(0)

// The graph container; a ResizeObserver re-fits the path whenever its size changes
// (window resize, sidebar/user-list toggle) so all nodes stay reachable (#480).
const flowContainer = ref(null)
let resizeObserver = null
let fitTimeout = null

// The delayed steps of this view (fit, focus, drawing) run only while it is
// mounted, and a step of an earlier refresh does nothing once a later refresh
// has come (re-audit 2026100736, B: late callbacks after unmount, remounts).
let alive = true
const timers = new Set()
const later = (fn, ms) => {
  const timer = setTimeout(() => {
    timers.delete(timer)
    if (alive) {
      fn()
    }
  }, ms)
  timers.add(timer)
  return timer
}
let refreshGeneration = 0
const refitToView = () => {
  clearTimeout(fitTimeout)
  fitTimeout = setTimeout(() => {
    if (nodes.value.length) {
      fitView({ padding: 0.2, duration: 400 })
    }
  }, 150)
}

// The node card components (CustomNodeEdit / CustomStagNodeEdit) snapshot their lock icon,
// the "active"/clickable gate and the resolved course-description text ONCE in onMounted
// from data.completion.feedback - they are not reactive. Keying each card on its feedback
// status makes Vue remount the card (re-running onMounted) whenever its status changes, so
// the lock/info stay correct - a defensive belt-and-braces alongside the flowKey remount.
const nodeStateKey = (data) => {
  const fb = (data && data.completion && data.completion.feedback) ? data.completion.feedback : {}
  const id = (data && (data.node_id || data.id)) || ''
  return `${id}|${fb.status || ''}|${fb.status_completion || ''}|${fb.status_restriction || ''}`
}

// A signature of every node's status verdicts AND rendered feedback text, so the async
// refresh re-renders whenever something the student can see changed - including a stack whose
// node status is unchanged (e.g. 1 of 3 courses done) but whose progress text moved from
// "2 von 3" to "1 von 3". Idle tab returns (nothing changed) produce the same signature and
// are skipped, so there is no needless flicker.
const statusSignature = (lp) => {
  const nodes = (lp && lp.json && lp.json.tree) ? lp.json.tree.nodes : []
  return nodes.map((n) => {
    const fb = (n.data && n.data.completion && n.data.completion.feedback) ? n.data.completion.feedback : {}
    const c = fb.completion || {}
    const r = fb.restriction || {}
    return JSON.stringify([
      n.id, fb.status, fb.status_completion, fb.status_restriction,
      c.before, c.inbetween, c.after, c.information,
      r.before, r.before_valid, r.inbetween, r.information,
    ])
  }).join('|')
}

// Refresh of the open path (#485, mod_adele #26) and auto-focus after a real
// change (mod_adele #27). The site's settings decide how (focus only, polling
// while active, polling in the background, or nothing); useUserPathRefresh
// keeps one request at a time and removes its listeners and timers on
// unmount. A refresh only reads the stored state. If a node's state changed,
// user_learningpath is reassigned (the watch re-renders through
// setFlowchart, as a page load does) and the canvas remounts via flowKey -
// a wholesale in-place nodes update corrupts VueFlow's state (stack expand,
// clickability). Then the view either moves to the change (auto-focus, by
// the role's mode) or keeps the person's place. A teacher's view refreshes
// too, but never while they are working in it (a dialog, a field, a drag or
// zoom), so nothing they are doing is interrupted.
const pathViewSettings = computed(() => store.state.pathviewsettings || parsePathViewSettings(null))
const focusRole = () => autofocusRole({
  view: store.state.view,
  currentUser: store.state.user,
  pathUser: store.state.view === 'student' ? store.state.user : route.params.userId,
})
let moving = false
let lastFocusedSignature = null
const interactionBusy = () => {
  if (moving) {
    return true
  }
  if (document.querySelector('.modal.show')) {
    return true
  }
  const active = document.activeElement
  return !!(active && flowContainer.value && flowContainer.value.contains(active) &&
    ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName))
}
onMoveStart(() => {
  moving = true
})
onMoveEnd(() => {
  moving = false
})
const refreshParams = () => (store.state.view === 'student'
  ? { learningpathId: store.state.learningPathID, userId: store.state.user }
  : route.params)
const onRefreshResult = async (fresh, reason) => {
  if (fresh && fresh.json && fresh.json.tree &&
      statusSignature(fresh) !== statusSignature(user_learningpath.value)) {
    const previous = user_learningpath.value
    const busy = interactionBusy()
    // Capture the exact current viewport so the remount below can restore it precisely,
    // independent of the debounced localStorage save.
    const savedviewport = (viewport.value && typeof viewport.value.zoom === 'number')
      ? { x: viewport.value.x, y: viewport.value.y, zoom: viewport.value.zoom }
      : null
    const changes = compareUserPathStates(previous, fresh)
    const signature = statusSignature(fresh)
    const target = (!busy && signature !== lastFocusedSignature)
      ? resolveAutofocusTarget({
        mode: autofocusMode(pathViewSettings.value, focusRole()),
        changes,
        currentPath: fresh,
      })
      : []
    // The keyboard focus stays where it was: the canvas is rebuilt, the
    // focused element gets its successor's focus back (mod_adele #27).
    const keyboard = rememberFocus(flowContainer.value)
    const generation = ++refreshGeneration
    const current = () => alive && generation === refreshGeneration
    user_learningpath.value = fresh
    await nextTick()
    flowKey.value++
    await nextTick()
    later(() => {
      if (!current()) {
        return
      }
      restoreFocusWhenReady(() => flowContainer.value, keyboard, document, current)
      if (target.length) {
        lastFocusedSignature = signature
        // The remounted canvas measures its nodes a moment later; a fit
        // before that has no sizes to fit.
        whenMeasured(target, () => {
          if (current()) {
            focusNodes(target, fitView)
          }
        })
      } else if (savedviewport) {
        setViewport(savedviewport, { duration: 0 })
      } else {
        fitView({ padding: 0.2, duration: 0 })
      }
    }, 300)
  }
  // The progress/ranking list on the same page follows a return to the page
  // (#568) - other participants' progress changes even when the own state
  // did not. A poll reads the own path only (mod_adele #26).
  if (reason === 'focus' && store.state.view === 'student') {
    await store.dispatch('fetchUserPathRelations')
  }
}
/**
 * Run once the given nodes have their size on the canvas (at most 3 s).
 *
 * @param {string[]} ids
 * @param {Function} done
 */
const whenMeasured = (ids, done) => {
  const started = Date.now()
  const check = () => {
    const measured = ids.every((id) => {
      const node = findNode(id)
      return node && node.dimensions && node.dimensions.width > 0
    })
    if (measured || Date.now() - started > 3000) {
      done()
    } else {
      later(check, 100)
    }
  }
  check()
}
let pathRefresh = null
const startRefresh = () => {
  if (pathRefresh) {
    return
  }
  pathRefresh = useUserPathRefresh({
    fetch: () => store.dispatch('fetchUserPathRelation', refreshParams()),
    onResult: onRefreshResult,
    settings: pathViewSettings.value,
    isBusy: interactionBusy,
    onPersistentError: () => notify({
      type: 'warn',
      title: store.state.strings.path_refresh_failed,
    }),
    log: (message, error) => {
      if (window.M && window.M.cfg && window.M.cfg.developerdebug) {
        // eslint-disable-next-line no-console
        console.debug(message, error)
      }
    },
  })
  pathRefresh.start()
}

// Persist the student's viewport (pan + zoom) per learning-path + user, so a genuine
// page (re)load restores where they were instead of jumping to the start (#485). Save
// is driven by a debounced watch on the reactive viewport (pan/zoom/fit all move it).
const currentViewportKey = () => viewportKey(store.state.learningPathID, store.state.user)
let saveViewportTimeout = null
watch(viewport, () => {
  if (store.state.view !== 'student') {
    return
  }
  clearTimeout(saveViewportTimeout)
  saveViewportTimeout = setTimeout(() => saveViewport(moodleStorage, currentViewportKey(), viewport.value), 500)
}, { deep: true })
// On (re)load, restore the saved viewport if there is one; otherwise fit the whole path.
const restoreOrFitView = () => {
  const saved = (store.state.view === 'student') ? loadViewport(moodleStorage, currentViewportKey()) : null
  if (saved) {
    setViewport(saved, { duration: 0 })
  } else {
    fitView({ padding: 0.2, duration: 1000 })
  }
}
// The #480 auto-refit on container resize must NOT clobber a student's restored viewport,
// so it is skipped for the student view (they keep their place; the manual Fit button and
// viewport restore cover their needs).
const onContainerResize = () => {
  if (store.state.view === 'student') {
    return
  }
  refitToView()
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

// Graph or list. The graph stays the default; the list is the equivalent
// alternative, reachable by one button (#575 B3).
const showOutline = ref(false)

/**
 * Switch the presentation and say which one is now shown (#575 B4).
 */
const toggleOutline = () => {
  showOutline.value = !showOutline.value
  store.commit('announce', showOutline.value
    ? store.state.strings.outline_title
    : store.state.strings.outline_graph_shown)
}

onMounted( async () => {
  if (!store.state.availablecourses) {
    store.dispatch('fetchAvailablecourses')
  }
  // The parent (StudentView) has loaded the path already: one load, not two.
  // The prop was read under another name (user_learningpath_parent) before,
  // so every student view loaded the path twice (mod_adele #32).
  if(!props.userlearningpathparent){
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
    user_learningpath.value = props.userlearningpathparent
  }
  if(user_learningpath.value){
    setFlowchart()
    later(() => {
      nextTick().then(() => {
        // Opened from a node counter or marker of the teachers' overview
        // (#15): centre the node it came from, not the whole graph.
        // Otherwise restore the student's last viewport if we have one, or
        // fit the whole path so every node is reachable (#480/#485).
        const focusnode = route && route.query ? String(route.query.node || '') : ''
        if (!(focusnode && findNode(focusnode) && focusNodes([focusnode], fitView))) {
          restoreOrFitView()
        }
      })
    }, 300)
  }
  // Re-fit on any container size change (window / sidebar / user-list toggle).
  if (flowContainer.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(onContainerResize)
    resizeObserver.observe(flowContainer.value)
  }
  // Keep the path current by the site's settings - after the one first load (#485, mod_adele #26).
  startRefresh()
  if (store.state.user == store.state.lpuserpathrelation.user_id) {
    await store.dispatch('updateUserPathRelation', {
      lpuserpathid: store.state.lpuserpathrelation.id,
    });
  }
})

onUnmounted(() => {
  alive = false
  timers.forEach((timer) => clearTimeout(timer))
  timers.clear()
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
  clearTimeout(fitTimeout)
  clearTimeout(saveViewportTimeout)
  if (pathRefresh) {
    pathRefresh.stop()
    pathRefresh = null
  }
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
  // One edge per connection (local_adele #603).
  // Markers named per graph, not per page (#18).
  edges.value = scopeMarkers(uniqueEdges(flowchart.tree.edges));

  edges.value.forEach((edge) => {
    edge.deletable = false
    edge.type = 'custom'
  })

  later(() => {
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