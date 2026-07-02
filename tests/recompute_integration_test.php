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
 * End-to-end recompute integration test.
 *
 * The existing recompute tests are unit-level (they call getnodestatus() etc.
 * directly). This drives a FULL relation_update::updated_single() run over a real
 * path_user snapshot for a tree that mixes:
 *   - a starting node with NO completion structure at all, and
 *   - a child node that DOES have a completion structure and an EMPTY restriction
 *     (restriction.nodes == []),
 * asserting that a sane user_path_relation is produced for every node, that the
 * emptied restriction leaves the child node accessible (not locked - #449), and
 * that the whole run emits no debugging.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\event\user_path_updated;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::updated_single
 * @covers \local_adele\relation_update::getnodestatus
 */
final class recompute_integration_test extends advanced_testcase {

    /**
     * A completion structure with a single course_completed criterion, matching
     * the shape the editor produces (starting_condition -> condition_1 -> feedback).
     *
     * @return array
     */
    private function course_completed_completion(): array {
        return [
            'edges' => [],
            'nodes' => [
                [
                    'id' => 'condition_1',
                    'type' => 'custom',
                    'parentCondition' => ['starting_condition'],
                    'childCondition' => ['condition_1_feedback'],
                    'data' => [
                        'label' => 'course_completed',
                        'name' => 'Course completed',
                        'value' => ['min_courses' => 1],
                    ],
                ],
                [
                    'id' => 'condition_1_feedback',
                    'type' => 'feedback',
                    'parentCondition' => ['condition_1'],
                    'childCondition' => [],
                    'data' => [
                        'visibility' => true,
                        'feedback_before' => 'do {item}',
                        'feedback_after' => 'done {item}',
                        'feedback_inbetween' => 'nearly {item}',
                        'feedback_inbetween_checkmark' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a two-node tree:
     *   dndnode_1: starting node, single course, NO completion, no restriction.
     *   dndnode_2: child node, single course, real completion, EMPTY restriction.
     *
     * @param int $coursea
     * @param int $courseb
     * @return array decoded json (with 'tree')
     */
    private function build_tree(int $coursea, int $courseb): array {
        return [
            'name' => 'Recompute LP',
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'circle',
                        'parentCourse' => ['starting_node'],
                        'childCourse' => ['dndnode_2'],
                        'data' => [
                            'course_node_id' => [(string) $coursea],
                            'fullname' => 'Start node',
                            'label' => 'Start',
                        ],
                        // No completion structure at all - degenerate node.
                    ],
                    [
                        'id' => 'dndnode_2',
                        'type' => 'circle',
                        'parentCourse' => ['dndnode_1'],
                        'childCourse' => [],
                        'data' => [
                            'course_node_id' => [(string) $courseb],
                            'fullname' => 'Child node',
                            'label' => 'Child',
                        ],
                        'completion' => $this->course_completed_completion(),
                        // Access criteria removed by the editor -> present but empty (#449).
                        'restriction' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ];
    }

    /**
     * A full recompute over a no-completion / empty-restriction tree produces a
     * sane user_path_relation for every node and raises no debugging; the node
     * whose restriction was emptied stays accessible (#449).
     *
     * @return void
     */
    public function test_recompute_over_incomplete_tree(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $coursea = $gen->create_course(['enablecompletion' => 1]);
        $courseb = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user();
        $creator = $gen->create_user();
        $gen->enrol_user($user->id, $coursea->id);
        $this->setUser($creator);

        $tree = $this->build_tree((int) $coursea->id, (int) $courseb->id);

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Recompute LP',
            'json' => json_encode($tree),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creator->id,
        ]);

        // Persist a path_user snapshot exactly as subscribe_user_to_learning_path does.
        $userpathid = (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $user->id,
            'course_id' => $coursea->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creator->id,
            'json' => json_encode(['tree' => $tree['tree'], 'modules' => null]),
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $userpathid]);
        $userpath->json = json_decode($userpath->json, true);

        // Drive the recompute through the real event -> observer -> updated_single chain.
        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => [
                'userpath' => $userpath,
                'course_id' => (int) $coursea->id,
            ],
        ]);
        $event->trigger();

        // No debugging must have been raised during the whole recompute.
        $this->assertDebuggingNotCalled();

        // Read back the persisted, recomputed snapshot.
        $stored = $DB->get_record('local_adele_path_user', ['id' => $userpathid]);
        $json = json_decode($stored->json, true);

        $this->assertArrayHasKey('user_path_relation', $json, 'Recompute must write user_path_relation.');

        // Every node must have been walked and carry a feedback envelope plus the
        // recomputed criteria buckets - even the degenerate no-completion node.
        foreach (['dndnode_1', 'dndnode_2'] as $nodeid) {
            $this->assertArrayHasKey($nodeid, $json['user_path_relation'], "Node {$nodeid} must be present.");
            $rel = $json['user_path_relation'][$nodeid];
            $this->assertArrayHasKey('feedback', $rel, "Node {$nodeid} must carry a feedback envelope.");
            $this->assertArrayHasKey('completion', $rel['feedback'], "Node {$nodeid} feedback must have a completion block.");
            $this->assertArrayHasKey('restriction', $rel['feedback'], "Node {$nodeid} feedback must have a restriction block.");
            $this->assertArrayHasKey('completioncriteria', $rel, "Node {$nodeid} must carry completioncriteria.");
            $this->assertArrayHasKey('restrictioncriteria', $rel, "Node {$nodeid} must carry restrictioncriteria.");
        }

        // The child node has a completion structure, so the recompute assigned it a
        // display status. Its restriction was emptied, so it must be accessible - the
        // node must NOT be locked (#449).
        $child = $json['user_path_relation']['dndnode_2']['feedback'];
        $this->assertArrayHasKey('status', $child, 'A node with a completion structure gets a display status.');
        $this->assertSame(
            'accessible',
            $child['status'],
            'A node with an emptied restriction (and unmet completion) must be accessible (#449).'
        );

        // The child completion is not met (course not completed), so it is not valid.
        $this->assertFalse(
            (bool) ($json['user_path_relation']['dndnode_2']['completionnode']['valid'] ?? false),
            'The child node completion must be unmet before the course is completed.'
        );
    }
}
