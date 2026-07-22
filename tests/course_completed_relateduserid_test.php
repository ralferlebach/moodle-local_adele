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
 * Regression tests for local_adele\completion::completed().
 *
 * Covers the fix where the course_completed observer resolved the affected
 * student via $event->userid (the acting user) instead of
 * $event->relateduserid (the student whose course was completed).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_course;
use context_system;
use core\event\course_completed;
use local_adele\completion;
use local_adele\event\user_path_updated;
use local_adele\relation_update;

/**
 * Regression tests for the course_completed -> relateduserid fix.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\completion::completed
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class course_completed_relateduserid_test extends advanced_testcase {
    /**
     * A minimal, editor-shaped completion structure with a single
     * course_completed criterion (min_courses = 1).
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
     * Build a single-node learning-path tree whose starting node references the
     * given course and completes on course_completed.
     *
     * @param mixed $coursenodeid Value stored in data.course_node_id[0] (string or int).
     * @return array The decoded json (with a 'tree' key).
     */
    private function build_tree($coursenodeid): array {
        return [
            'name' => 'Completion LP',
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'circle',
                        'parentCourse' => ['starting_node'],
                        'childCourse' => [],
                        'data' => [
                            'course_node_id' => [$coursenodeid],
                            'fullname' => 'Start node',
                            'label' => 'Start',
                        ],
                        'completion' => $this->course_completed_completion(),
                        'restriction' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [],
            ],
            'modules' => null,
        ];
    }

    /**
     * Persist a learning path and an active path_user snapshot for a user.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $creatorid
     * @param array|string $tree Decoded tree array, or a raw (possibly invalid) json string.
     * @return int The path_user id.
     */
    private function persist_path(int $userid, int $courseid, int $creatorid, $tree): int {
        global $DB;

        $treejson = is_string($tree) ? $tree : json_encode(['tree' => $tree['tree'], 'modules' => null]);

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Completion LP',
            'json' => is_string($tree) ? $tree : json_encode($tree),
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
            'json' => $treejson,
        ]);
    }

    /**
     * Insert a course_completions row and return the real course_completed event
     * with a distinct acting user (teacher) and the student as relateduserid.
     *
     * @param int $courseid
     * @param int $studentid
     * @param int $actorid The acting user (e.g. grading teacher).
     * @return course_completed
     */
    private function make_course_completed_event(int $courseid, int $studentid, int $actorid): course_completed {
        global $DB;

        $ccid = (int) $DB->insert_record('course_completions', (object) [
            'course' => $courseid,
            'userid' => $studentid,
            'timeenrolled' => time(),
            'timestarted' => time(),
            'timecompleted' => time(),
            'reaggregate' => 0,
        ]);
        \cache::make('core', 'coursecompletion')->delete($studentid . '_' . $courseid);

        // The acting user is set as the current user, so $event->userid becomes
        // the teacher/system - exactly the mismatch this fix addresses.
        $this->setUser($actorid);

        return course_completed::create([
            'objectid' => $ccid,
            'relateduserid' => $studentid,
            'context' => context_course::instance($courseid),
            'courseid' => $courseid,
            'other' => ['relateduserid' => $studentid],
        ]);
    }

    /**
     * Collect the user_path_updated events (objectids) captured by a sink.
     *
     * @param \phpunit_event_sink $sink
     * @return int[] The objectids (path_user ids) of the fired events.
     */
    private function captured_userpath_ids($sink): array {
        $ids = [];
        foreach ($sink->get_events() as $e) {
            if ($e->eventname === '\\local_adele\\event\\user_path_updated') {
                $ids[] = (int) $e->objectid;
            }
        }
        return $ids;
    }

    /**
     * The affected student is resolved via relateduserid (not the acting user),
     * the student's path is updated, and the node ends up 'completed'.
     *
     * @return void
     */
    public function test_completed_uses_relateduserid_not_actor(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        // Store the course id as a STRING in the node to also cover normalisation.
        $tree = $this->build_tree((string) $course->id);
        $pathid = $this->persist_path((int) $student->id, (int) $course->id, (int) $teacher->id, $tree);

        $event = $this->make_course_completed_event((int) $course->id, (int) $student->id, (int) $teacher->id);

        // The acting user must be the teacher; the affected student must be relateduserid.
        $this->assertNotEquals($student->id, $event->userid);
        $this->assertEquals($student->id, $event->relateduserid);

        // Capture the recompute trigger without dispatching it to observers.
        $sink = $this->redirectEvents();
        completion::completed($event);
        $fired = $this->captured_userpath_ids($sink);
        $sink->close();

        $this->assertSame([$pathid], $fired, 'Exactly the student path must be scheduled for recompute.');

        // Now drive the real recompute and assert the node becomes completed.
        $record = $DB->get_record('local_adele_path_user', ['id' => $pathid]);
        $record->json = json_decode($record->json, true);
        $recompute = user_path_updated::create([
            'objectid' => $record->id,
            'context' => context_system::instance(),
            'other' => ['userpath' => $record],
        ]);
        relation_update::updated_single($recompute);

        $stored = json_decode($DB->get_record('local_adele_path_user', ['id' => $pathid])->json, true);
        $this->assertSame(
            'completed',
            $stored['user_path_relation']['dndnode_1']['feedback']['status'],
            'The node must be stored as completed once the course is completed.'
        );
    }

    /**
     * Only the learning path of the relateduserid student is updated; another
     * user holding a path for the same course is left untouched.
     *
     * @return void
     */
    public function test_completed_ignores_other_users_paths(): void {
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $studenta = $gen->create_user();
        $studentb = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($studenta->id, $course->id);
        $gen->enrol_user($studentb->id, $course->id);

        $tree = $this->build_tree((int) $course->id);
        $patha = $this->persist_path((int) $studenta->id, (int) $course->id, (int) $teacher->id, $tree);
        $pathb = $this->persist_path((int) $studentb->id, (int) $course->id, (int) $teacher->id, $tree);

        $event = $this->make_course_completed_event((int) $course->id, (int) $studenta->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        completion::completed($event);
        $fired = $this->captured_userpath_ids($sink);
        $sink->close();

        $this->assertContains($patha, $fired, "Student A's path must be updated.");
        $this->assertNotContains($pathb, $fired, "Student B's path must NOT be updated.");
    }

    /**
     * A course id is matched whether it is stored as a string or as an integer.
     *
     * @return void
     */
    public function test_completed_matches_string_and_int_course_ids(): void {
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        // Two active paths for the same student: one storing the course id as a
        // string, one as an integer.
        $pathstring = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((string) $course->id)
        );
        $pathint = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $course->id)
        );

        $event = $this->make_course_completed_event((int) $course->id, (int) $student->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        completion::completed($event);
        $fired = $this->captured_userpath_ids($sink);
        $sink->close();

        $this->assertContains($pathstring, $fired, 'A string-typed course_node_id must be matched.');
        $this->assertContains($pathint, $fired, 'An int-typed course_node_id must be matched.');
    }

    /**
     * When the same course is referenced by several nodes of one path, only a
     * single recompute is triggered for that path.
     *
     * @return void
     */
    public function test_completed_triggers_single_recompute_per_path(): void {
        global $DB;
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        // Build a tree where the SAME course appears in two nodes.
        $tree = $this->build_tree((int) $course->id);
        $secondnode = $tree['tree']['nodes'][0];
        $secondnode['id'] = 'dndnode_2';
        $tree['tree']['nodes'][] = $secondnode;

        $pathid = $this->persist_path((int) $student->id, (int) $course->id, (int) $teacher->id, $tree);

        $event = $this->make_course_completed_event((int) $course->id, (int) $student->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        completion::completed($event);
        $fired = $this->captured_userpath_ids($sink);
        $sink->close();

        $this->assertSame(
            [$pathid],
            $fired,
            'A course referenced by multiple nodes must still trigger only one recompute per path.'
        );
    }

    /**
     * A structurally broken snapshot must not abort processing of other valid
     * learning paths of the same student.
     *
     * @return void
     */
    public function test_completed_survives_invalid_json(): void {
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        // A broken snapshot (not valid learning-path json) ...
        $brokenpath = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            'this-is-not-json'
        );
        // ... and a valid one referencing the completed course.
        $validpath = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $course->id)
        );

        $event = $this->make_course_completed_event((int) $course->id, (int) $student->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        completion::completed($event);
        $fired = $this->captured_userpath_ids($sink);
        $sink->close();

        $this->assertDebuggingNotCalled();
        $this->assertContains($validpath, $fired, 'The valid path must still be processed.');
        $this->assertNotContains($brokenpath, $fired, 'The broken path must be skipped, not fatal.');
    }
}
