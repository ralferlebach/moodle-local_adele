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
 * Course Carousel component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup lang="ts">

  interface CourseProps {
    course_node_id: string[];
  }
  const props = defineProps<{
    courses: CourseProps;
  }>();

</script>

<template>
  <div
    class="row"
  >
    <div
      class="col-12 text-center"
    >
      <span
        v-for="course_node_id in props.courses.course_node_id"
        :key="course_node_id"
        class="dot"
      />
    </div>
  </div>
</template>

<style scoped>
.dot {
  height: 10px; /* Size of dot */
  width: 10px; /* Size of dot */
  background-color: black; /* Dot color */
  border-radius: 50%; /* Make it round */
  display: inline-block; /* Align dots next to each other */
  margin-right: 5px; /* Space between dots */
}
</style>
