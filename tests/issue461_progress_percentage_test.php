<?php
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
 * The teacher-view learning-path progress percentage must equal completed / total nodes, so it
 * always matches the completed-node count shown next to it. Previously it was the best single
 * root-to-leaf path's ratio, which read as 100% on a branching path once one full branch was
 * done - contradicting the node count (GitHub #461).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Progress-percentage regression test for #461.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::getnodeprogress
 */
final class issue461_progress_percentage_test extends advanced_testcase {
    /**
     * Build the relation object getnodeprogress() expects.
     *
     * @param array $nodes Map of nodeid => bool completed. Module nodes use a '_module' suffix.
     * @param array $edges Map of nodeid => [parentCourse, childCourse].
     * @return object
     */
    private function relation(array $nodes, array $edges): object {
        $relation = ['user_path_relation' => [], 'tree' => ['nodes' => []]];
        foreach ($nodes as $id => $completed) {
            $relation['user_path_relation'][$id] = ['completionnode' => ['valid' => $completed]];
            $relation['tree']['nodes'][] = [
                'id' => $id,
                'parentCourse' => $edges[$id][0] ?? [],
                'childCourse' => $edges[$id][1] ?? [],
            ];
        }
        return json_decode(json_encode($relation));
    }

    /**
     * On a branching path, finishing one full branch (2 of 3 nodes) must read as 66.67%, not
     * 100% - the percentage matches the completed-node count.
     *
     * @return void
     */
    public function test_branching_path_percentage_matches_node_count(): void {
        // A(start)->C, B(start)->C, C(leaf). Learner completed A and C, not B.
        $relation = $this->relation(
            ['A' => true, 'B' => false, 'C' => true],
            [
                'A' => [['starting_node'], ['C']],
                'B' => [['starting_node'], ['C']],
                'C' => [['A', 'B'], []],
            ]
        );
        $result = learning_paths::getnodeprogress($relation);
        $this->assertSame(2, $result['completed_nodes']);
        $this->assertEqualsWithDelta(
            66.67,
            $result['progress'],
            0.01,
            'A branching path with 2 of 3 nodes done must be 66.67%, not the old best-path 100%.'
        );
    }

    /**
     * The percentage must always equal round(100 * completed / total) for representative states.
     *
     * @return void
     */
    public function test_percentage_equals_completed_over_total(): void {
        $edges = [
            'A' => [['starting_node'], ['B']],
            'B' => [['A'], ['C']],
            'C' => [['B'], []],
        ];
        // None done.
        $none = learning_paths::getnodeprogress($this->relation(['A' => false, 'B' => false, 'C' => false], $edges));
        $this->assertSame(0, $none['completed_nodes']);
        $this->assertEqualsWithDelta(0.0, $none['progress'], 0.01);
        // All done.
        $all = learning_paths::getnodeprogress($this->relation(['A' => true, 'B' => true, 'C' => true], $edges));
        $this->assertSame(3, $all['completed_nodes']);
        $this->assertEqualsWithDelta(100.0, $all['progress'], 0.01);
        // One of three.
        $one = learning_paths::getnodeprogress($this->relation(['A' => true, 'B' => false, 'C' => false], $edges));
        $this->assertSame(1, $one['completed_nodes']);
        $this->assertEqualsWithDelta(33.33, $one['progress'], 0.01);
    }

    /**
     * Module overlay nodes (keys containing '_module') are not part of the path and must be
     * excluded from both the count and the percentage.
     *
     * @return void
     */
    public function test_module_nodes_are_excluded(): void {
        $relation = $this->relation(
            ['A' => true, 'B' => false, 'dndnode_1_module' => true],
            [
                'A' => [['starting_node'], ['B']],
                'B' => [['A'], []],
                'dndnode_1_module' => [[], []],
            ]
        );
        $result = learning_paths::getnodeprogress($relation);
        $this->assertSame(1, $result['completed_nodes'], 'The module node must not be counted.');
        $this->assertEqualsWithDelta(50.0, $result['progress'], 0.01, 'Percentage over the 2 real nodes only.');
    }
}
