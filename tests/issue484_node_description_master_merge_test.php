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
 * Node display texts must follow the MASTER learning path for subscribed students.
 *
 * A student's path_user snapshot copies the tree at subscription time. When an
 * editor later adds/changes a node description (or per-course texts inside a
 * stack), existing students still render the frozen snapshot and see no (or a
 * stale) description (GitHub #484). Display-only node fields must therefore be
 * overlaid from the master path at fetch time - exactly like the settings
 * toggles already are (#474).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Fetch-time master overlay of node display texts (#484).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::get_learning_user_relation
 */
final class issue484_node_description_master_merge_test extends advanced_testcase {
    /**
     * Seed a master LP (nodes WITH display texts) and a student snapshot whose
     * tree lacks them (subscribed before the editor added the texts).
     *
     * @return array [loadparams, masternodedata]
     */
    private function seed(): array {
        global $DB;
        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $user = $gen->create_user();

        $masternodedata = [
            'course_node_id' => [(string) $course->id],
            'fullname' => 'Edited node name',
            'description' => 'Node description added AFTER subscription',
            'estimate_duration' => '2 Wochen',
            'course_node_id_description' => [
                (string) $course->id => [
                    'fullname' => 'Course given name',
                    'description' => 'Course own description',
                ],
            ],
        ];
        $node = static function (array $data): array {
            return [
                'id' => 'dndnode_1',
                'type' => 'custom',
                'parentCourse' => ['starting_node'],
                'childCourse' => [],
                // Real editor nodes always carry a completion sub-graph;
                // addnodemanualcondition() iterates it without a guard.
                'completion' => ['nodes' => [], 'edges' => []],
                'data' => $data,
            ];
        };

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP 484',
            'json' => json_encode(['tree' => ['nodes' => [$node($masternodedata)], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);
        // The snapshot was taken BEFORE the editor added the texts.
        $snapshotdata = [
            'course_node_id' => [(string) $course->id],
            'fullname' => 'Old node name',
        ];
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => json_encode([
                'tree' => ['nodes' => [$node($snapshotdata)], 'edges' => []],
                'user_path_relation' => [],
            ]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
        ]);

        return [[
            'learningpathid' => $lpid,
            'userpathid' => $user->id,
            'courseid' => $course->id,
        ], $masternodedata];
    }

    /**
     * The fetched relation must carry the master's display texts even though the
     * snapshot predates them.
     *
     * @return void
     */
    public function test_display_texts_follow_the_master_path(): void {
        $this->resetAfterTest(true);
        [$loadparams, $master] = $this->seed();

        $relation = learning_paths::get_learning_user_relation($loadparams);
        $json = json_decode($relation['json'], true);
        $data = $json['tree']['nodes'][0]['data'];

        $this->assertSame(
            $master['description'],
            $data['description'] ?? null,
            'A node description added after subscription must reach existing students (#484).'
        );
        $this->assertSame(
            $master['estimate_duration'],
            $data['estimate_duration'] ?? null,
            'The estimated duration must follow the master path.'
        );
        $this->assertSame(
            $master['course_node_id_description'],
            $data['course_node_id_description'] ?? null,
            'Per-course texts (stack courses) must follow the master path.'
        );
        $this->assertSame(
            $master['fullname'],
            $data['fullname'] ?? null,
            'The node name must follow the master path.'
        );
    }
}
