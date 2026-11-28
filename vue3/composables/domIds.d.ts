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
 * Types of domIds.js (local_adele #18), for the TypeScript composables.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export function nextAppDomPrefix(): string;
export function appDomId(store: { state?: { domPrefix?: string } } | null | undefined, name: string): string;
export function appDomSelector(store: { state?: { domPrefix?: string } } | null | undefined, name: string): string;
export function dialogInstanceId(
  store: { state?: { domPrefix?: string } } | null | undefined,
  name: string
): { base: string; release: () => boolean };
export const DIALOGS: Readonly<{ node: string; course: string; tutorial: string }>;
export function scopedMarker<T>(marker: T): T | { type: string };
export function scopeMarkers<T extends object>(edges: T[]): T[];
