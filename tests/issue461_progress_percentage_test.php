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
 * Learning-path progress measures the learner's BEST ROUTE through the graph.
 *
 * GitHub #461 revisit: the first fix made progress = completed / total nodes,
 * which is wrong on OR-branched paths - with a 2-node and a 10-node branch,
 * finishing 1 node of the short branch showed 1/12 (8%) although the learner
 * is halfway to completing the path. Decision: progress = max over all
 * root->terminal routes of (completed-on-route / route-length); the roster
 * additionally reports the best route's x/y and the raw totals for display.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Best-route progress semantics for #461 (revisited).
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
     * The 2-vs-10 branch graph from the ticket: branch A = A1->A2, branch
     * B = B1->...->B10, both starting nodes. 12 nodes, two routes.
     *
     * @param array $completed Node ids marked completed.
     * @return object
     */
    private function branched(array $completed): object {
        $nodes = [];
        $edges = [];
        $edges['A1'] = [['starting_node'], ['A2']];
        $edges['A2'] = [['A1'], []];
        for ($i = 1; $i <= 10; $i++) {
            $prev = $i === 1 ? 'starting_node' : 'B' . ($i - 1);
            $next = $i === 10 ? [] : ['B' . ($i + 1)];
            $edges['B' . $i] = [[$prev], $next];
        }
        foreach (array_keys($edges) as $id) {
            $nodes[$id] = in_array($id, $completed, true);
        }
        return $this->relation($nodes, $edges);
    }

    /**
     * The ticket's flagship case: 1 of the 2-node branch done = HALFWAY to
     * completion (50%), not 1/12th - with the best route reported as 1/2 and
     * the raw totals available for the tooltip.
     *
     * @return void
     */
    public function test_short_branch_half_done_reads_fifty_percent(): void {
        $result = learning_paths::getnodeprogress($this->branched(['A1']));
        $this->assertEqualsWithDelta(
            50.0,
            $result['progress'],
            0.01,
            '1 of 2 nodes on the short branch is 50% toward completion, not 1/12.'
        );
        $this->assertSame(1, $result['route_completed']);
        $this->assertSame(2, $result['route_total']);
        $this->assertSame(1, $result['completed_nodes'], 'Raw count keeps its meaning.');
        $this->assertSame(12, $result['total_nodes'], 'Raw total for the tooltip.');
    }

    /**
     * Completing the short branch completes the path: 100%, route 2/2, while
     * the raw totals still say 2 of 12.
     *
     * @return void
     */
    public function test_completed_short_branch_reads_hundred_percent(): void {
        $result = learning_paths::getnodeprogress($this->branched(['A1', 'A2']));
        $this->assertEqualsWithDelta(100.0, $result['progress'], 0.01);
        $this->assertSame(2, $result['route_completed']);
        $this->assertSame(2, $result['route_total']);
        $this->assertSame(2, $result['completed_nodes']);
        $this->assertSame(12, $result['total_nodes']);
    }

    /**
     * Two routes at the same ratio (1/2 and 5/10 are both 50%): the DISPLAYED
     * route is the one closest to finishing (fewest remaining nodes) - "1/2",
     * not "5/10".
     *
     * @return void
     */
    public function test_equal_ratio_prefers_fewest_remaining(): void {
        $result = learning_paths::getnodeprogress(
            $this->branched(['A1', 'B1', 'B2', 'B3', 'B4', 'B5'])
        );
        $this->assertEqualsWithDelta(50.0, $result['progress'], 0.01);
        $this->assertSame(1, $result['route_completed'], 'The 1-remaining route wins the display.');
        $this->assertSame(2, $result['route_total']);
        $this->assertSame(6, $result['completed_nodes']);
    }

    /**
     * On a linear path the best route IS the whole path - the numbers are
     * identical to the previous completed/total semantics.
     *
     * @return void
     */
    public function test_linear_path_numbers_unchanged(): void {
        $edges = [
            'A' => [['starting_node'], ['B']],
            'B' => [['A'], ['C']],
            'C' => [['B'], []],
        ];
        $none = learning_paths::getnodeprogress($this->relation(['A' => false, 'B' => false, 'C' => false], $edges));
        $this->assertEqualsWithDelta(0.0, $none['progress'], 0.01);
        $this->assertSame(0, $none['route_completed']);
        $this->assertSame(3, $none['route_total']);

        $one = learning_paths::getnodeprogress($this->relation(['A' => true, 'B' => false, 'C' => false], $edges));
        $this->assertEqualsWithDelta(33.33, $one['progress'], 0.01);
        $this->assertSame(1, $one['route_completed']);
        $this->assertSame(3, $one['route_total']);

        $all = learning_paths::getnodeprogress($this->relation(['A' => true, 'B' => true, 'C' => true], $edges));
        $this->assertEqualsWithDelta(100.0, $all['progress'], 0.01);
        $this->assertSame(3, $all['completed_nodes']);
        $this->assertSame(3, $all['total_nodes']);
    }

    /**
     * Module overlay nodes ('_module' ids) are not part of the path: excluded
     * from the raw counts AND never counted on a route - even if such an id
     * ever leaked into the graph walk.
     *
     * @return void
     */
    public function test_module_nodes_are_excluded_everywhere(): void {
        $relation = $this->relation(
            ['A' => true, 'x_module' => true, 'B' => false],
            [
                'A' => [['starting_node'], ['x_module']],
                'x_module' => [['A'], ['B']],
                'B' => [['x_module'], []],
            ]
        );
        $result = learning_paths::getnodeprogress($relation);
        $this->assertSame(1, $result['completed_nodes'], 'Module node not in the raw count.');
        $this->assertSame(2, $result['total_nodes'], 'Module node not in the raw total.');
        $this->assertSame(2, $result['route_total'], 'Module node not counted on the route.');
        $this->assertSame(1, $result['route_completed']);
        $this->assertEqualsWithDelta(50.0, $result['progress'], 0.01);
    }

    /**
     * A dead-end cycle (A->B->C->B, no terminal) yields no routes at all:
     * fall back to the raw completed/total semantics and mirror the raw
     * numbers into the route fields so the display stays consistent.
     *
     * @return void
     */
    public function test_dead_end_cycle_falls_back_to_raw_semantics(): void {
        $relation = $this->relation(
            ['A' => true, 'B' => false, 'C' => false],
            [
                'A' => [['starting_node'], ['B']],
                'B' => [['A', 'C'], ['C']],
                'C' => [['B'], ['B']],
            ]
        );
        $result = learning_paths::getnodeprogress($relation);
        $this->assertEqualsWithDelta(33.33, $result['progress'], 0.01);
        $this->assertSame(1, $result['route_completed'], 'Raw mirrored into the route fields.');
        $this->assertSame(3, $result['route_total']);
    }

    /**
     * A snapshot without a tree keeps working on the raw semantics.
     *
     * @return void
     */
    public function test_missing_tree_falls_back_to_raw_semantics(): void {
        $relation = json_decode(json_encode([
            'user_path_relation' => [
                'A' => ['completionnode' => ['valid' => true]],
                'B' => ['completionnode' => ['valid' => false]],
            ],
        ]));
        $result = learning_paths::getnodeprogress($relation);
        $this->assertEqualsWithDelta(50.0, $result['progress'], 0.01);
        $this->assertSame(1, $result['route_completed']);
        $this->assertSame(2, $result['route_total']);
        $this->assertSame(1, $result['completed_nodes']);
        $this->assertSame(2, $result['total_nodes']);
    }
}
