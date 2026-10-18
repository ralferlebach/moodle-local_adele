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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
// Import needed libraries
import { computed, onMounted } from 'vue';
import absUrl from '../../composables/absUrl';
import { useStore } from 'vuex';
import ExpandNodeInformation from '../nodes_items/ExpandNodeInformation.vue';
import * as nodeColors from '../../config/nodeColors';
import TeacherCourseAccess from '../nodes_items/TeacherCourseAccess.vue';
import {
  expandedCourseDescription,
  expandedCourseFullname,
  expandedCourseLabel,
  expandedCourseNodeId,
  expandedCoursePanelId,
  expandedCourseShortname,
  expandedCourseTitle,
} from '../../composables/nodesHelper/expandedCourse';

// Load Store
const store = useStore();

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  zoomstep: {
    type: Number,
    required: true,
  },
});

// The course is opened by a link - it is navigation - in a new tab, as before.
const courseUrl = computed(() => store.state.wwwroot + '/course/view.php?id=' + encodeURIComponent(props.data.course_id));

const cover_image = computed(() => absUrl(get_cover_image(props.data), store.state.wwwroot));

const get_cover_image = (data) => {
  if (data.imagepaths && data.imagepaths[props.data.course_id]) {
    return data.imagepaths[props.data.course_id]
  }
  return null
}

// Determine completion for the current course id
const isCourseCompleted = computed(() => {
  const courseId = props.data?.course_id;
  const completedMap = props.data?.completion?.completioncriteria?.course_completed?.completed;
  if (!courseId || !completedMap || typeof completedMap !== 'object') {
    return false;
  }
  return !!completedMap[courseId];
});

// Style: light header color and solid border using nodeColors
const headerBackgroundColor = computed(() => ({
  backgroundColor: isCourseCompleted.value
    ? nodeColors.courseNodeFinishedColorLight
    : nodeColors.courseNodeNotFinishedColorLight,
}));

const parentStyle = computed(() => ({
  borderColor: isCourseCompleted.value
    ? nodeColors.courseNodeFinishedColor
    : nodeColors.courseNodeNotFinishedColor,
  borderWidth: '2px',
}));

const courses = computed(() => {

  if (
    !store.state.availablecourses ||
    !props.data.course_id
  ) {
    return [];
  }

  return store.state.availablecourses.filter(course =>
    props.data.course_id == course.course_node_id[0]
    ).map(course => ({
      givenname: props.data.course_node_id_description &&
        props.data.course_node_id_description[course.course_node_id[0]]
        ? props.data.course_node_id_description[course.course_node_id[0]].fullname
        : null,
      description: props.data.course_node_id_description &&
        props.data.course_node_id_description[course.course_node_id[0]]
        ? props.data.course_node_id_description[course.course_node_id[0]].description
        : null,
      fullname: course.fullname,
      summary: course.summary,
      id: [course.course_node_id[0]]
    })
  )}
);

// Resolve the displayed course name independently of the filtered
// availablecourses pool: prefer the name carried in the node data
// (course_node_id_description, now populated server-side for students too),
// then the availablecourses entry, then a generic fallback (issue #457).
const courseTitle = computed(() =>
  expandedCourseTitle(props.data, props.data.course_id, store.state.availablecourses) || 'Subcourse'
);

const courseShortname = computed(() =>
  expandedCourseShortname(props.data, props.data.course_id, store.state.availablecourses)
);

// The panel's readable name: the course name with its short name and id, so
// two courses of the same name are told apart (#17).
const courseLabel = computed(() =>
  expandedCourseLabel(
    expandedCourseTitle(props.data, props.data.course_id, store.state.availablecourses),
    props.data.course_id,
    store.state.strings,
    courseShortname.value
  )
);

// DOM ids of this panel: unique on the page (stack and course in them).
const panelId = computed(() => expandedCoursePanelId(props.data.node_id, props.data.course_id));
const headingId = computed(() => panelId.value + '-heading');
const descriptionId = computed(() => panelId.value + '-desc');

// Shown below the name and read as the panel's description: short name, the
// course's own full name, course id, status in words (#17, TP-08).
const courseDescription = computed(() =>
  expandedCourseDescription({
    title: courseTitle.value,
    shortname: courseShortname.value,
    fullname: expandedCourseFullname(props.data, props.data.course_id, store.state.availablecourses),
    courseid: props.data.course_id,
    completed: isCourseCompleted.value,
  }, store.state.strings)
);

const goToCourseLabel = computed(() =>
  (store.state.strings.go_to_course || 'Go to course') + ': ' + courseTitle.value
  + ' (' + (store.state.strings.opens_in_new_tab || 'opens in a new tab') + ')'
);

// The VueFlow node of this panel (the course access finds its wrapper by it).
const accessNodeId = computed(() => expandedCourseNodeId(props.data.node_id, props.data.course_id));

onMounted(() => {
  setTimeout(() => {
    props.data.showCard = true
  }, 200)
})

</script>

<template>
  <transition name="unfold">
    <div v-if="data.showCard">
      <div
        :id="panelId"
        class="card test"
        role="group"
        :aria-labelledby="headingId"
        :aria-describedby="descriptionId"
        :style="[{ minHeight: '200px', width: '400px' }, parentStyle]"
        :data-course-id="data.course_id"
        :data-node-id="data.node_id"
        :data-course-shortname="courseShortname || undefined"
        :data-status="isCourseCompleted ? 'completed' : 'open'"
        :data-testid="`expanded-course-${data.course_id}`"
        :title="courseLabel"
      >
        <div class="card-header text-center" :style="headerBackgroundColor">
          <ExpandNodeInformation
            :courses
            :data="data"
            :startanimation="true"
          />
          <div class="row justify-content-center">
            <div class="col-10">
              <h5
                :id="headingId"
                style="color: #000;"
              >
                {{ courseTitle }}
              </h5>
              <p
                :id="descriptionId"
                class="small mb-0 adele-expanded-course-desc"
                style="color: #000;"
              >
                {{ courseDescription }}
              </p>
            </div>
          </div>
        </div>
        <div
          class="card-body active-node"
        >
          <div
            class="card-img dashboard-card-img mb-2"
            :style="{
              height: '10rem',
              backgroundImage: cover_image ? 'url(' + cover_image + ')' : 'none',
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              backgroundColor: cover_image ? '' : '#cccccc'
            }"
          >
            <div class="overlay">
              <a
                class="icon-link"
                :href="courseUrl"
                target="_blank"
                rel="noopener"
                :title="store.state.strings.go_to_course"
                :aria-label="goToCourseLabel"
                @click.stop
              >
                <i
                  class="fas fa-play"
                  aria-hidden="true"
                />
              </a>
            </div>
          </div>
        </div>
        <TeacherCourseAccess
          :node-id="accessNodeId"
          :course-ids="[data.course_id]"
        />
      </div>
    </div>
  </transition>
</template>
<style scoped>
/* Starting state for entering */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  visibility: visible;
}

.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: left;
}

.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: left;
}

.overlay {
  position: relative;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(0, 0, 0, 0.4); /* Semi-transparent gray */
  display: flex;
  justify-content: center;
  align-items: center;
  width: 70%; /* Adjust width as needed */
  height: 50%; /* Adjust height as needed */
  border-radius: 15px; /* Rounded edges */
}
.icon-link {
  border: none;
  background: none;
  color: white;
  font-size: 30px;
  cursor: pointer;
  padding: 10px;
  margin: 0 15px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}
.icon-link:hover {
  color: lightgray; /* Hover effect */
}
/* A visible focus on the dark overlay (TP-08). */
.icon-link:focus-visible {
  outline: 3px solid #fff;
  outline-offset: 2px;
  box-shadow: 0 0 0 6px #0f6cbf;
  border-radius: 4px;
}
</style>