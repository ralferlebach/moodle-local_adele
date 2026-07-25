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
 * Time Warning component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="tooltip-container">
    <i class="ml-2 fas fa-exclamation-triangle" />
    <div class="tooltip-text">
      {{ store.state.strings.nodes_warning_time_restriction }}
    </div>
  </div>
</template>

<script setup>
  import { useStore } from 'vuex';
  const store = useStore();
</script>

<style scoped>
  .tooltip-container {
    position: relative;
    display: inline-block;
    cursor: pointer;
  }

  .tooltip-container .tooltip-text {
    visibility: hidden;
    width: 300px; /* Set a max-width for the tooltip */
    background-color: black;
    color: #fff;
    text-align: center;
    border-radius: 5px;
    padding: 10px 20px; /* Increase the padding to give more space on the sides */
    position: absolute;
    z-index: 1;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.6s ease-in-out, visibility 0.6s ease-in-out, transform 0.6s ease-in-out;
    white-space: normal; /* Ensure the text wraps */
    word-break: break-word; 
  }

  .tooltip-container:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
  }
</style>