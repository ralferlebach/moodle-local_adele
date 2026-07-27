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
 * darken Color module.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

interface RgbColor {
  r: number;
  g: number;
  b: number;
}

const  darkenColor = (color: string, darken: number): string => {
    let intensity = 0.2;
    let rgb = hexToRgb(color);
    rgb.r = Math.floor(rgb.r * intensity + 128 * darken);
    rgb.g = Math.floor(rgb.g * intensity + 128 * darken);
    rgb.b = Math.floor(rgb.b * intensity + 128 * darken);
    return rgbToHex(rgb.r, rgb.g, rgb.b);
}

const hexToRgb = (hex: string): RgbColor => {
    let bigint = parseInt(hex.slice(1), 16);
    let r = (bigint >> 16) & 255;
    let g = (bigint >> 8) & 255;
    let b = (bigint & 255);
    return { r, g, b };
}

const rgbToHex = (
  r: number,
  g: number,
  b: number
): string => {
    return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
}

export default darkenColor;