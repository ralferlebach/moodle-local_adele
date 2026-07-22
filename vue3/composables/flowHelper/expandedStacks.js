// This file is part of Moodle - http://moodle.org/
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

import { reactive } from 'vue';

/**
 * Which stack ("Lernpaket") nodes are currently expanded, keyed by node id. Kept at module
 * scope - outside any component lifecycle - so an expanded stack stays expanded across a
 * VueFlow remount: the async in-tab refresh that rebuilds the whole canvas, and the per-card
 * status remount, both otherwise reset the local expand state to collapsed (#485).
 */
export const expandedStacks = reactive({});
