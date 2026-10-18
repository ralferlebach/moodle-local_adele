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

namespace local_adele;

use advanced_testcase;

/**
 * What a refresh of an open learning path does on the server (mod_adele
 * #26): it reads the stored state - nothing is written, recalculated,
 * triggered or queued.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\external\get_lp_user_path_relation
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class path_refresh_readonly_test extends advanced_testcase {
    /**
     * A refresh reads the stored state only: no write, no event, no queued
     * task - so polling triggers no recalculation and no enrolment.
     *
     * @return void
     */
    public function test_a_refresh_reads_only(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Read only', 'description' => '', 'timecreated' => 1, 'timemodified' => 1, 'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id, 'course_id' => $course->id, 'learning_path_id' => $lpid, 'status' => 'active',
            'createdby' => $user->id, 'timecreated' => 1, 'timemodified' => 1,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
        $this->setUser($user);
        $context = \context_course::instance($course->id);
        $before = [
            $DB->get_records('local_adele_path_user'),
            $DB->get_records('local_adele_learning_paths'),
            $DB->count_records('user_enrolments'),
            $DB->count_records('task_adhoc'),
        ];
        $events = $this->redirectEvents();
        for ($i = 0; $i < 3; $i++) {
            \local_adele\external\get_lp_user_path_relation::execute($lpid, (int) $user->id, (int) $context->id);
        }
        $this->assertSame([], array_map(fn($e) => get_class($e), $events->get_events()), 'No event.');
        $events->close();
        $this->assertEquals($before, [
            $DB->get_records('local_adele_path_user'),
            $DB->get_records('local_adele_learning_paths'),
            $DB->count_records('user_enrolments'),
            $DB->count_records('task_adhoc'),
        ], 'Nothing written, nothing queued.');
    }
}
