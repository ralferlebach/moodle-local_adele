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
 * Regression tests for issue #502.
 *
 * The course_completed condition must treat a course as "in progress"
 * (inbetween) from the first course access (erster Kursaufruf) onwards, not
 * merely because the learner is enrolled. The timed processing-duration
 * restriction is a separate mechanism and is not exercised here.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use local_adele\course_completion\conditions\course_completed;

/**
 * Regression tests for the first-access "in progress" criterion (#502).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue502_progress_inbetween_test extends advanced_testcase {
    /**
     * Build a single course_completed node referencing one course.
     *
     * @param int $courseid
     * @return array
     */
    private function build_node(int $courseid): array {
        return [
            'id' => 'dndnode_1',
            'data' => [
                'course_node_id' => [$courseid],
                'fullname' => 'Node',
            ],
            'completion' => [
                'nodes' => [
                    [
                        'id' => 'condition_1',
                        'data' => [
                            'label' => 'course_completed',
                            'value' => ['min_courses' => 1],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Simulate the first course access by recording a user_lastaccess row, as
     * Moodle does the first time a user opens a course.
     *
     * @param int $courseid
     * @param int $userid
     */
    private function mark_course_accessed(int $courseid, int $userid): void {
        global $DB;
        $DB->insert_record('user_lastaccess', (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'timeaccess' => time(),
        ]);
    }

    /**
     * A learner who is only enrolled but never opened the course is not started.
     *
     * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
     * @return void
     */
    public function test_enrolled_but_never_accessed_is_not_inbetween(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $gen->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        $status = (new course_completed())->get_completion_status(
            $this->build_node((int) $course->id),
            (int) $student->id
        );

        $this->assertFalse(
            $status['inbetween']['condition_1'],
            'Enrolled-but-never-accessed must not be treated as in progress (#502).'
        );
    }

    /**
     * The first course access marks the node as in progress, even at 0 % progress.
     *
     * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
     * @return void
     */
    public function test_first_course_access_is_inbetween(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        // An activity with completion keeps progress computable, but nothing is done (0 %).
        $gen->create_module('page', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);
        $this->mark_course_accessed((int) $course->id, (int) $student->id);

        $status = (new course_completed())->get_completion_status(
            $this->build_node((int) $course->id),
            (int) $student->id
        );

        $this->assertTrue(
            $status['inbetween']['condition_1'],
            'The first course access must mark the node as in progress (#502).'
        );
    }

    /**
     * The access-based criterion is independent of completion activities: a
     * course without any completion activities still counts as started once opened.
     *
     * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
     * @return void
     */
    public function test_access_without_completion_activities_is_inbetween(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);
        $this->mark_course_accessed((int) $course->id, (int) $student->id);

        $status = (new course_completed())->get_completion_status(
            $this->build_node((int) $course->id),
            (int) $student->id
        );

        $this->assertTrue(
            $status['inbetween']['condition_1'],
            'A course opened at least once counts as in progress regardless of criteria (#502).'
        );
    }
}
