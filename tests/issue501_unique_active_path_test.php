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
 * Regression tests for issue #501.
 *
 * At most one active user-path relation may exist per (user_id, course_id,
 * learning_path_id). The database enforces this via a unique index, and the
 * subscribe flow reuses an existing snapshot instead of creating a duplicate.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Unique active user-path relation regression tests (#501).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue501_unique_active_path_test extends advanced_testcase {
    /**
     * Insert an active user-path row for the given triple.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $lpid
     * @return int
     */
    private function insert_path(int $userid, int $courseid, int $lpid): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $userid,
            'course_id' => $courseid,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
            'json' => '{"tree":{"nodes":[]}}',
        ]);
    }

    /**
     * The database rejects a second row with the same (user, course, lp) triple.
     *
     * @coversNothing
     * @return void
     */
    public function test_unique_index_prevents_duplicate_active_paths(): void {
        $this->resetAfterTest();
        $this->insert_path(101, 202, 303);
        $this->expectException(\dml_exception::class);
        $this->insert_path(101, 202, 303);
    }

    /**
     * A repeat subscribe reuses the existing active snapshot instead of creating
     * a duplicate row.
     *
     * @covers \local_adele\enrollment::subscribe_user_to_learning_path
     * @return void
     */
    public function test_subscribe_reuses_existing_active_path(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = self::getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []], 'modules' => null]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // A snapshot already exists for this triple.
        $this->insert_path((int) $user->id, (int) $course->id, $lpid);

        $learningpath = (object) [
            'id' => $lpid,
            'json' => ['tree' => ['nodes' => [], 'edges' => []], 'modules' => null],
        ];
        $params = (object) ['relateduserid' => (int) $user->id, 'userid' => (int) $user->id];

        $sink = $this->redirectEvents();
        enrollment::subscribe_user_to_learning_path($learningpath, $params, (int) $course->id);
        $sink->close();

        $count = $DB->count_records('local_adele_path_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'learning_path_id' => $lpid,
            'status' => 'active',
        ]);
        $this->assertSame(1, $count, 'subscribe must reuse the existing active snapshot, not duplicate it.');
    }
}
