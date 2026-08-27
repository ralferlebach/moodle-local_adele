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
 * The leaderboard ranks by best-route progress first (#461 revisit).
 *
 * With route-based percentages the ranking flips: percent is the primary key
 * (equal relative progress ties), the raw completed-node count breaks ties
 * (equal relative progress, more absolute work ranks higher). Rank ties
 * ("1224" style) only occur when BOTH values are equal.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Percent-first leaderboard ranking (#461 revisit).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::get_learning_user_relations
 */
final class issue461_route_ranking_test extends advanced_testcase {
    /**
     * Snapshot json for the 2-vs-10 branch graph with the given completions.
     *
     * @param array $completed Node ids marked completed.
     * @return string
     */
    private function snapshot(array $completed): string {
        $relation = ['user_path_relation' => [], 'tree' => ['nodes' => []]];
        $edges = ['A1' => [['starting_node'], ['A2']], 'A2' => [['A1'], []]];
        for ($i = 1; $i <= 10; $i++) {
            $prev = $i === 1 ? 'starting_node' : 'B' . ($i - 1);
            $next = $i === 10 ? [] : ['B' . ($i + 1)];
            $edges['B' . $i] = [[$prev], $next];
        }
        foreach ($edges as $id => $edge) {
            $relation['user_path_relation'][$id] = [
                'completionnode' => ['valid' => in_array($id, $completed, true)],
            ];
            $relation['tree']['nodes'][] = [
                'id' => $id,
                'parentCourse' => $edge[0],
                'childCourse' => $edge[1],
            ];
        }
        return json_encode($relation);
    }

    /**
     * Ranking: percent first, raw completed count as tie-break, shared rank
     * only on full equality.
     *
     * @return void
     */
    public function test_leaderboard_ranks_by_percent_then_raw_work(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP 461 ranking',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);

        // C: short branch complete (100%). B: 5 of the long branch (50%, raw 5).
        // A: 1 of the short branch (50%, raw 1). E: same as A (full tie).
        // D: nothing (0%).
        $completions = [
            'c' => ['A1', 'A2'],
            'b' => ['B1', 'B2', 'B3', 'B4', 'B5'],
            'a' => ['A1'],
            'e' => ['A1'],
            'd' => [],
        ];
        foreach ($completions as $key => $completed) {
            $user = $gen->create_user(['firstname' => strtoupper($key), 'lastname' => 'Ranked']);
            $DB->insert_record('local_adele_path_user', [
                'user_id' => $user->id,
                'course_id' => 1,
                'learning_path_id' => $lpid,
                'status' => 'active',
                'timecreated' => time(),
                'timemodified' => time(),
                'createdby' => 2,
                'json' => $this->snapshot($completed),
            ]);
        }

        $roster = learning_paths::get_learning_user_relations(['learningpathid' => $lpid]);
        $order = array_map(static fn($row) => $row['firstname'], $roster);
        $ranks = array_map(static fn($row) => $row['rank'], $roster);

        // C(100%) first; B(50%, raw 5) beats A/E(50%, raw 1); D(0%) last.
        $this->assertSame('C', $order[0]);
        $this->assertSame('B', $order[1]);
        $this->assertEqualsCanonicalizing(['A', 'E'], [$order[2], $order[3]], 'The full tie shares positions 3/4.');
        $this->assertSame('D', $order[4]);

        // Ranks are 1224-style: A and E share rank 3, D drops to rank 5.
        $this->assertSame([1, 2, 3, 3, 5], array_map('intval', $ranks));
    }
}
