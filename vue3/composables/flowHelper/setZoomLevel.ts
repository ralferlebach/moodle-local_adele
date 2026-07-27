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
 * set Zoom Level module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const zoomSteps: number[] = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5];

interface Viewport {
  zoom: number;
}

const setZoomLevel = async (
  action: 'in' | 'out',
  viewport: Viewport,
  zoomTo: (zoomLevel: number, options: { duration: number }) => void
): Promise<number | undefined> => {
  try {
    let newViewport = parseFloat(viewport.zoom.toFixed(2))

    let currentStepIndex = zoomSteps.findIndex(step => newViewport < step);
    if (currentStepIndex === -1) {
      currentStepIndex = zoomSteps.length;
    }
    if (action === 'in') {
      if (currentStepIndex < zoomSteps.length) {
        newViewport = zoomSteps[currentStepIndex];
      } else {
        newViewport = zoomSteps[currentStepIndex - 1]
      }
    } else if (action === 'out') {
      if (currentStepIndex > 1) {
        newViewport = zoomSteps[currentStepIndex-2];
      } else {
        newViewport = zoomSteps[0]
      }
    }

    zoomTo(newViewport, { duration: 500})
    return newViewport
  } catch (error) {
    console.error("Error during zoom operation:", error)
  }
}

export default setZoomLevel;