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

/**
 * on Wheel module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import setZoomLevel from './setZoomLevel'

interface WheelEventWithTarget extends WheelEvent {
  target: HTMLElement;
}

const onWheel = async (
  event: WheelEventWithTarget,
  zoomLockVaraible: boolean,
  viewport: HTMLElement,
  zoomTo: number
) : Promise<void> => {
  const isScrollTarget = event.target.closest('.vue-flow__pane')
  const isTrackpad = Math.abs(event.deltaY) < 2;
  const isZoomingGesture = event.ctrlKey || event.metaKey;
  const zoomingdirection = event.deltaY < 0 ? 'in' : 'out'
  if (
    isScrollTarget
  ) {
    if (isTrackpad && !isZoomingGesture) {
      return;
    }
    event.preventDefault();
    event.stopPropagation();
    if(!zoomLockVaraible) {
      zoomLockVaraible = true
      await setZoomLevel(zoomingdirection, viewport, zoomTo)
      setTimeout(() => {
        zoomLockVaraible = false
      }, 500)
    }
  }
}

export default onWheel;