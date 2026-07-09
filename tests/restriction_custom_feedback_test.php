<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use advanced_testcase;
use local_adele\event\user_path_updated;

/**
 * A teacher's custom "before" feedback on a restriction feedback node must be shown to
 * the student while the node is locked. The student view renders restriction.before_active,
 * which the recompute builds from the condition's default description_before - so a custom
 * feedback (feedback_before_checkmark off) was ignored and the default was shown instead.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::updated_single
 */
final class restriction_custom_feedback_test extends advanced_testcase {
    /** @var int */
    private $c1;
    /** @var int */
    private $c2;

    /**
     * Two-node branch. Node B has a manual restriction whose feedback node is wired
     * (childCondition -> condition_1_feedback) and set to CUSTOM mode with custom text.
     *
     * @param string $custommsg The custom feedback text.
     * @return array Decoded learning-path tree json structure.
     */
    private function build_tree(string $custommsg): array {
        return [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => ['dndnode_2'],
                        'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Node A'],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                    [
                        'id' => 'dndnode_2', 'type' => 'circle',
                        'parentCourse' => ['dndnode_1'], 'childCourse' => [],
                        'data' => ['course_node_id' => [(string) $this->c2], 'fullname' => 'Node B'],
                        'restriction' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => ['condition_1_feedback'],
                                    'data' => [
                                        'id' => 150,
                                        'label' => 'manual',
                                        'description_before' => 'must be released manually',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => ['condition_1'],
                                    'childCondition' => [],
                                    'data' => [
                                        'label' => 'feedback',
                                        'visibility' => true,
                                        'feedback_before_checkmark' => false,
                                        'feedback_before' => $custommsg,
                                        'information' => 'info',
                                    ],
                                ],
                            ],
                            'edges' => [],
                        ],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'modules' => null,
        ];
    }

    /**
     * Recomputing a locked node with a custom restriction feedback yields the custom
     * text in before_active (what the student sees), not the default description.
     */
    public function test_custom_restriction_feedback_reaches_before_active(): void {
        global $DB;
        $this->resetAfterTest(true);
        $custommsg = 'toll tafel wischen';

        $gen = self::getDataGenerator();
        $this->c1 = (int) $gen->create_course()->id;
        $this->c2 = (int) $gen->create_course()->id;
        $user = $gen->create_user();
        $this->setUser($user);

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Custom feedback LP',
            'json' => json_encode($this->build_tree($custommsg)),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $user->id,
        ]);
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id, 'course_id' => $this->c1, 'learning_path_id' => $lpid,
            'status' => 'active', 'json' => json_encode($this->build_tree($custommsg)),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $user->id,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);

        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => \context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);
        relation_update::updated_single($event);

        $json = json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true);
        $node = null;
        foreach ($json['tree']['nodes'] as $n) {
            if ($n['id'] === 'dndnode_2') {
                $node = $n;
            }
        }
        $this->assertNotNull($node, 'Recomputed node dndnode_2 must exist.');
        $beforeactive = $node['data']['completion']['feedback']['restriction']['before_active'] ?? [];
        $joined = implode(' ', (array) $beforeactive);
        $this->assertStringContainsString(
            $custommsg,
            $joined,
            'The custom restriction feedback must appear in before_active (shown to the student).'
        );
        $this->assertStringNotContainsString(
            'must be released manually',
            $joined,
            'The default description must be replaced by the custom feedback.'
        );
    }
}
