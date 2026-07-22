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
 * Regression tests for issue #498.
 *
 * Post-submission quiz changes (manual grading, regrading, deletion, reopening)
 * must trigger a full recompute of the attempt owner's learning paths, resolved
 * via relateduserid rather than the acting user.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Quiz change-event synchronisation regression tests (#498).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue498_quiz_change_events_test extends advanced_testcase {
    /**
     * Persist a learning path plus an active user path with a modquiz node.
     *
     * @param int $userid
     * @param int $quizid
     * @param int $courseid
     * @param int $creatorid
     * @return int The user-path id.
     */
    private function persist_path(int $userid, int $quizid, int $courseid, int $creatorid): int {
        global $DB;
        $tree = ['tree' => ['nodes' => [[
            'id' => 'dndnode_1',
            'type' => 'circle',
            'parentCourse' => ['starting_node'],
            'childCourse' => [],
            'data' => ['course_node_id' => [(string) $courseid], 'fullname' => 'Quiz node', 'label' => 'q'],
            'completion' => [
                'nodes' => [
                    ['id' => 'condition_1', 'data' => ['label' => 'modquiz', 'value' => ['quizid' => $quizid, 'grade' => 1]]],
                ],
            ],
            'restriction' => ['nodes' => [], 'edges' => []],
        ]]], 'modules' => null];
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP',
            'json' => json_encode($tree),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
        ]);
        return (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $userid,
            'course_id' => $courseid,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
            'json' => json_encode(['tree' => $tree['tree'], 'modules' => null]),
        ]);
    }

    /**
     * Collect the objectids of captured user_path_updated events.
     *
     * @param object $sink
     * @return array
     */
    private function fired($sink): array {
        $ids = [];
        foreach ($sink->get_events() as $e) {
            if ($e->eventname === '\\local_adele\\event\\user_path_updated') {
                $ids[] = (int) $e->objectid;
            }
        }
        return $ids;
    }

    /**
     * The generic quiz-change observer recomputes the attempt owner's path
     * (relateduserid), not the acting user's.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_quiz_change_recomputes_relateduserid_not_actor(): void {
        $this->resetAfterTest();
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        $pathid = $this->persist_path((int) $student->id, (int) $quiz->id, (int) $course->id, (int) $teacher->id);

        // A quiz attempt change carries the affected learner in relateduserid; the
        // acting user (teacher) is in userid. attempt_submitted is used here as a
        // constructible \core\event\base carrying both fields.
        $this->setUser($teacher);
        $event = \mod_quiz\event\attempt_submitted::create([
            'objectid' => 1,
            'relateduserid' => $student->id,
            'courseid' => $quiz->course,
            'context' => \context_module::instance($quiz->cmid),
            'other' => ['quizid' => (int) $quiz->id, 'submitterid' => (int) $teacher->id],
        ]);
        $this->assertNotEquals($student->id, $event->userid, 'The acting user must be the teacher.');

        $sink = $this->redirectEvents();
        \local_adele_observer::quiz_attempt_changed($event);
        $fired = $this->fired($sink);
        $sink->close();

        $this->assertSame([$pathid], $fired, 'A quiz change must recompute the attempt owner path (relateduserid).');
    }

    /**
     * The manual-grading, regrading, deletion and reopening events are all wired
     * to the generic quiz-change observer.
     *
     * @coversNothing
     * @return void
     */
    public function test_change_events_registration(): void {
        $observers = [];
        include(__DIR__ . '/../db/events.php');
        $map = [];
        foreach ($observers as $observer) {
            $map[$observer['eventname']] = $observer['callback'];
        }
        $expected = [
            '\\mod_quiz\\event\\attempt_manual_grading_completed',
            '\\mod_quiz\\event\\attempt_regraded',
            '\\mod_quiz\\event\\attempt_deleted',
            '\\mod_quiz\\event\\attempt_reopened',
        ];
        foreach ($expected as $eventname) {
            $this->assertArrayHasKey($eventname, $map, "Event {$eventname} must be registered.");
            $this->assertSame('local_adele_observer::quiz_attempt_changed', $map[$eventname]);
        }
    }
}
