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
 * Types of normalizeEdges.js for the TypeScript modules (local_adele #603).
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export declare const DEFAULT_EDGE_TYPE: string;
export declare function edgeKey(edge: object | null): string;
export declare function hasEdge(edges: object[] | null, edge: object): boolean;
export declare function normalizeEdges(edges: object[] | null): {
  edges: object[];
  removed: number;
  renamed: number;
  conflicts: { id: string; newid: string }[];
};
export declare function uniqueEdges<T extends object>(edges: T[] | null): T[];
export declare function withUniqueEdges<T>(json: T): T;
