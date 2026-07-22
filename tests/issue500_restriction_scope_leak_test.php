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
 * Regression tests for issue #500.
 *
 * The restriction paths of one node must not leak into the following nodes
 * during a full learning-path recompute.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\event\user_path_updated;

/**
 * Restriction scope-leak regression tests (#500).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue500_restriction_scope_leak_test extends advanced_testcase {
    /**
     * A minimal but valid parent_courses restriction (condition + feedback node).
     *
     * Mirrors the structure proven by the parent_courses use-case tests. The
     * referenced parent id is deliberately dangling; parent_courses treats a
     * dangling reference as satisfied (#445), so no debugging is emitted.
     *
     * @return array
     */
    private function parent_courses_restriction(): array {
        return [
            'nodes' => [
                [
                    'id' => 'condition_1',
                    'type' => 'custom',
                    'parentCondition' => ['starting_condition'],
                    'childCondition' => ['condition_1_feedback'],
                    'data' => [
                        'label' => 'parent_courses',
                        'name' => 'parent',
                        'node_id' => 'condition_1',
                        'description_before' => 'before {node_name}',
                        'visibility' => true,
                        'value' => ['min_courses' => 1, 'courses_id' => ['ghost_node']],
                    ],
                ],
                [
                    'id' => 'condition_1_feedback',
                    'type' => 'feedback',
                    'parentCondition' => ['condition_1'],
                    'childCondition' => [],
                    'data' => [
                        'childCondition' => 'condition_1',
                        'visibility' => true,
                        'feedback_before' => 'before {node_name}',
                        'feedback_before_checkmark' => true,
                    ],
                ],
            ],
            'edges' => [],
        ];
    }

    /**
     * Build a linear tree from a list of [id, courseid, hasrestriction] tuples.
     *
     * @param array $nodedefs
     * @return array
     */
    private function build_tree(array $nodedefs): array {
        $nodes = [];
        $prev = 'starting_node';
        foreach ($nodedefs as $def) {
            [$id, $courseid, $hasrestriction] = $def;
            $nodes[] = [
                'id' => $id,
                'type' => 'circle',
                'parentCourse' => [$prev],
                'childCourse' => [],
                'data' => [
                    'course_node_id' => [(string) $courseid],
                    'fullname' => 'Node ' . $id,
                    'label' => $id,
                ],
                'restriction' => $hasrestriction
                    ? $this->parent_courses_restriction()
                    : ['nodes' => [], 'edges' => []],
            ];
            $prev = $id;
        }
        $count = count($nodes);
        for ($i = 0; $i < $count - 1; $i++) {
            $nodes[$i]['childCourse'] = [$nodes[$i + 1]['id']];
        }
        return ['name' => 'Leak LP', 'tree' => ['nodes' => $nodes, 'edges' => []], 'modules' => null];
    }

    /**
     * Persist a learning path plus an active user path and run updated_single.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $creatorid
     * @param array $tree
     * @return array The decoded user-path json after the recompute.
     */
    private function run_recompute(int $userid, int $courseid, int $creatorid, array $tree): array {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Leak LP',
            'json' => json_encode($tree),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
        ]);
        $userpathid = (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $userid,
            'course_id' => $courseid,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
            'json' => json_encode(['tree' => $tree['tree'], 'modules' => null]),
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $userpathid]);
        $userpath->json = json_decode($userpath->json, true);
        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);
        relation_update::updated_single($event);
        return json_decode($DB->get_record('local_adele_path_user', ['id' => $userpathid])->json, true);
    }

    /**
     * A restriction on the first node must not leak into a later unrestricted node.
     *
     * @covers \local_adele\relation_update::updated_single
     * @return void
     */
    public function test_restriction_paths_do_not_leak_to_following_node(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $creator = $gen->create_user();
        $user = $gen->create_user();
        $ca = $gen->create_course(['enablecompletion' => 1]);
        $cb = $gen->create_course(['enablecompletion' => 1]);
        $gen->enrol_user($user->id, $ca->id);
        $this->setUser($creator);

        $tree = $this->build_tree([
            ['restrnode', (int) $ca->id, true],
            ['plainnode', (int) $cb->id, false],
        ]);

        $json = $this->run_recompute((int) $user->id, (int) $ca->id, (int) $creator->id, $tree);

        $this->assertNotEmpty(
            $json['user_path_relation']['restrnode']['restrictionnode'],
            'The restricted node must carry its own restriction paths.'
        );
        $this->assertSame(
            [],
            $json['user_path_relation']['plainnode']['restrictionnode'],
            'A node without a restriction must not inherit the previous node restriction paths (#500).'
        );
    }

    /**
     * The leaky variable must not accumulate across three or more nodes.
     *
     * @covers \local_adele\relation_update::updated_single
     * @return void
     */
    public function test_no_accumulation_across_three_nodes(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $creator = $gen->create_user();
        $user = $gen->create_user();
        $ca = $gen->create_course(['enablecompletion' => 1]);
        $cb = $gen->create_course(['enablecompletion' => 1]);
        $cc = $gen->create_course(['enablecompletion' => 1]);
        $gen->enrol_user($user->id, $ca->id);
        $this->setUser($creator);

        $tree = $this->build_tree([
            ['restrnode', (int) $ca->id, true],
            ['plain1', (int) $cb->id, false],
            ['plain2', (int) $cc->id, false],
        ]);

        $json = $this->run_recompute((int) $user->id, (int) $ca->id, (int) $creator->id, $tree);

        $this->assertSame(
            [],
            $json['user_path_relation']['plain1']['restrictionnode'],
            'The second node must not inherit restriction paths.'
        );
        $this->assertSame(
            [],
            $json['user_path_relation']['plain2']['restrictionnode'],
            'The third node must not accumulate restriction paths of earlier nodes.'
        );
    }
}
