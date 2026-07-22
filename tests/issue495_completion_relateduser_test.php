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
 * When a course completion is triggered by somebody other than the student (a teacher
 * grading, an admin, or cron), the \core\event\course_completed carries the actor in
 * $event->userid and the affected student in $event->relateduserid. The observer must
 * look up learning paths for the STUDENT (relateduserid), not the actor (userid) -
 * otherwise the student's node never gets recomputed (GitHub #495).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Regression test for #495: completion routed by relateduserid, not userid.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\completion::completed
 */
final class issue495_completion_relateduser_test extends advanced_testcase {
    /**
     * Seed an active path_user snapshot for $userid whose tree carries $nodes, each a
     * [id => course_node_id array] mapping, and return the learning-path id.
     *
     * @param int $userid
     * @param int $courseid enrolment course for the path_user row
     * @param int $createdby
     * @param array $nodes Map of node id => course_node_id array.
     * @return int path_user row id (what user_path_updated carries as objectid)
     */
    private function seed_path(int $userid, int $courseid, int $createdby, array $nodes): int {
        global $DB;
        $treenodes = [];
        foreach ($nodes as $id => $coursenodeid) {
            $treenodes[] = [
                'id' => $id,
                'type' => 'circle',
                'parentCourse' => ['starting_node'],
                'childCourse' => [],
                'data' => ['course_node_id' => $coursenodeid, 'fullname' => $id, 'label' => $id],
            ];
        }
        $tree = ['tree' => ['nodes' => $treenodes, 'edges' => []], 'modules' => null];

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP 495',
            'json' => json_encode($tree),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $createdby,
        ]);
        return (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $userid,
            'course_id' => $courseid,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $createdby,
            'json' => json_encode($tree),
        ]);
    }

    /**
     * Build a real course_completed event for $student, triggered while $actor is the
     * logged-in user. $event->userid is auto-filled from $USER (the actor);
     * $event->relateduserid is the student.
     *
     * @param \stdClass $student
     * @param \stdClass $actor
     * @param int $courseid
     * @return \core\event\course_completed
     */
    private function completion_event(\stdClass $student, \stdClass $actor, int $courseid): \core\event\course_completed {
        $this->setUser($actor);
        // A full course_completions record so the event's record snapshot matches the table.
        $completion = (object) [
            'id' => 1,
            'userid' => $student->id,
            'course' => $courseid,
            'timeenrolled' => 0,
            'timestarted' => 0,
            'timecompleted' => time(),
            'reaggregate' => 0,
        ];
        return \core\event\course_completed::create_from_completion($completion);
    }

    /**
     * Collect the learning-path ids named by every user_path_updated event fired while
     * running completion::completed on $event.
     *
     * @param \core\event\course_completed $event
     * @return int[] learning-path ids, in order fired (with duplicates).
     */
    private function fired_lp_ids(\core\event\course_completed $event): array {
        $sink = $this->redirectEvents();
        completion::completed($event);
        $ids = [];
        foreach ($sink->get_events() as $fired) {
            if ($fired->eventname === '\\local_adele\\event\\user_path_updated') {
                $ids[] = (int) $fired->objectid;
            }
        }
        $sink->close();
        return $ids;
    }

    /**
     * A teacher-triggered completion must recompute the STUDENT's path. On the old code
     * (which looked up $event->userid) the teacher owns no path, so nothing fired.
     *
     * @return void
     */
    public function test_teacher_triggered_completion_recomputes_student_path(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $pathid = $this->seed_path($student->id, (int) $course->id, $teacher->id, [
            'dndnode_1' => [(string) $course->id],
        ]);

        $event = $this->completion_event($student, $teacher, (int) $course->id);
        // Sanity: the event does carry the actor in userid and the student in relateduserid.
        $this->assertSame((int) $teacher->id, (int) $event->userid);
        $this->assertSame((int) $student->id, (int) $event->relateduserid);

        $this->assertSame(
            [$pathid],
            $this->fired_lp_ids($event),
            'A teacher-triggered completion must recompute the student path (routed by relateduserid).'
        );
    }

    /**
     * End-to-end wiring: the event registered in db/events.php must, when the REAL
     * course_completed event is triggered by a teacher, run the observer chain
     * (course_completed -> completion::completed -> user_path_updated -> updated_single)
     * and recompute the STUDENT's stored path. This covers the subscription itself,
     * which the direct-call tests above bypass.
     *
     * @return void
     */
    public function test_registered_observer_recomputes_student_path_on_real_event(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, (int) $course->id);
        $pathid = $this->seed_path($student->id, (int) $course->id, $teacher->id, [
            'dndnode_1' => [(string) $course->id],
        ]);

        // Precondition: the freshly seeded snapshot has not been recomputed yet.
        $before = json_decode($DB->get_field('local_adele_path_user', 'json', ['id' => $pathid]), true);
        $this->assertArrayNotHasKey('user_path_relation', $before);

        // Fire the REAL event as the teacher, letting the registered observer run (no redirectEvents).
        $this->completion_event($student, $teacher, (int) $course->id)->trigger();

        // The registered observer must have recomputed the student's path end to end.
        $after = json_decode($DB->get_field('local_adele_path_user', 'json', ['id' => $pathid]), true);
        $this->assertArrayHasKey(
            'user_path_relation',
            $after,
            'The registered course_completed observer must recompute the student path (#495 wiring + relateduserid).'
        );
    }

    /**
     * A completion the student triggers themselves must still recompute their path (the
     * case that worked before): relateduserid == userid == the student.
     *
     * @return void
     */
    public function test_self_triggered_completion_still_recomputes(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $pathid = $this->seed_path($student->id, (int) $course->id, $student->id, [
            'dndnode_1' => [(string) $course->id],
        ]);

        $event = $this->completion_event($student, $student, (int) $course->id);
        $this->assertSame([$pathid], $this->fired_lp_ids($event));
    }

    /**
     * When one completed course maps to several nodes of the same learning path, the
     * path must be recomputed at most once per event, not once per matching node.
     *
     * @return void
     */
    public function test_course_in_multiple_nodes_recomputes_path_once(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $pathid = $this->seed_path($student->id, (int) $course->id, $teacher->id, [
            'dndnode_1' => [(string) $course->id],
            'dndnode_2' => [(string) $course->id],
        ]);

        $event = $this->completion_event($student, $teacher, (int) $course->id);
        $this->assertSame(
            [$pathid],
            $this->fired_lp_ids($event),
            'A course mapped to two nodes of one path must trigger exactly one recompute.'
        );
    }
}
