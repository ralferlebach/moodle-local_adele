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
 * Regression tests for issue #496.
 *
 * A quiz submission must recompute the learning path of the attempt owner
 * (relateduserid), never the acting user (userid), and the observer must be
 * wired to the real mod_quiz submission event.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Quiz submission observer/user regression tests (#496).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue496_quiz_submitted_relateduserid_test extends advanced_testcase {
    /**
     * Build a learning path whose single node completes on a given quiz.
     *
     * The quiz id is stored as a string so that the current JSON search matches
     * it; the string/int robustness of that search is addressed in #497.
     *
     * @param int $quizid
     * @param int $courseid
     * @param array $extranodequizids Additional nodes referencing quiz ids.
     * @return array
     */
    private function build_tree(int $quizid, int $courseid, array $extranodequizids = []): array {
        $nodes = [];
        $nodes[] = $this->quiz_node('dndnode_1', $quizid, $courseid);
        $i = 2;
        foreach ($extranodequizids as $extraquizid) {
            $nodes[] = $this->quiz_node('dndnode_' . $i, $extraquizid, $courseid);
            $i++;
        }
        return ['name' => 'Quiz LP', 'tree' => ['nodes' => $nodes, 'edges' => []], 'modules' => null];
    }

    /**
     * Build a single modquiz completion node.
     *
     * @param string $id
     * @param int $quizid
     * @param int $courseid
     * @return array
     */
    private function quiz_node(string $id, int $quizid, int $courseid): array {
        return [
            'id' => $id,
            'type' => 'circle',
            'parentCourse' => ['starting_node'],
            'childCourse' => [],
            'data' => [
                'course_node_id' => [(string) $courseid],
                'fullname' => 'Quiz node',
                'label' => $id,
            ],
            'completion' => [
                'nodes' => [
                    [
                        'id' => 'condition_1',
                        'type' => 'custom',
                        'parentCondition' => ['starting_condition'],
                        'childCondition' => [],
                        'data' => [
                            'label' => 'modquiz',
                            'value' => ['quizid' => (string) $quizid, 'grade' => 1],
                        ],
                    ],
                ],
            ],
            'restriction' => ['nodes' => [], 'edges' => []],
        ];
    }

    /**
     * Persist a learning path plus an active user path, returning the user-path id.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $creatorid
     * @param array $tree
     * @return int
     */
    private function persist_path(int $userid, int $courseid, int $creatorid, array $tree): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Quiz LP',
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
     * Create a mod_quiz attempt_submitted event whose acting user differs from
     * the attempt owner.
     *
     * @param \stdClass $quiz
     * @param int $studentid The attempt owner (relateduserid).
     * @param int $actorid The acting user (userid), e.g. a teacher.
     * @return \mod_quiz\event\attempt_submitted
     */
    private function make_submitted_event(\stdClass $quiz, int $studentid, int $actorid): \mod_quiz\event\attempt_submitted {
        $this->setUser($actorid);
        return \mod_quiz\event\attempt_submitted::create([
            'objectid' => 1,
            'relateduserid' => $studentid,
            'courseid' => $quiz->course,
            'context' => \context_module::instance($quiz->cmid),
            'other' => ['quizid' => (int) $quiz->id, 'submitterid' => $actorid],
        ]);
    }

    /**
     * Collect the objectids of all user_path_updated events captured by a sink.
     *
     * @param \core\event\manager|object $sink
     * @return array
     */
    private function fired_userpath_ids($sink): array {
        $ids = [];
        foreach ($sink->get_events() as $e) {
            if ($e->eventname === '\\local_adele\\event\\user_path_updated') {
                $ids[] = (int) $e->objectid;
            }
        }
        return $ids;
    }

    /**
     * A submission recomputes the attempt owner's path, not the acting user's.
     *
     * @covers \local_adele\learning_path_update::quiz_finished
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_submission_recomputes_students_path_not_actor(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        $pathid = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $quiz->id, (int) $course->id)
        );

        $event = $this->make_submitted_event($quiz, (int) $student->id, (int) $teacher->id);
        $this->assertNotEquals($student->id, $event->userid, 'The acting user must be the teacher.');
        $this->assertEquals($student->id, $event->relateduserid, 'The attempt owner must be the student.');

        $sink = $this->redirectEvents();
        learning_path_update::quiz_finished($event);
        $fired = $this->fired_userpath_ids($sink);
        $sink->close();

        $this->assertSame([$pathid], $fired, 'Exactly the student path must be recomputed.');
    }

    /**
     * Another learner's path is not recomputed by a foreign submission.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_other_users_path_not_recomputed(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $studenta = $gen->create_user();
        $studentb = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($studenta->id, $course->id);
        $gen->enrol_user($studentb->id, $course->id);

        $patha = $this->persist_path(
            (int) $studenta->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $quiz->id, (int) $course->id)
        );
        $this->persist_path(
            (int) $studentb->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $quiz->id, (int) $course->id)
        );

        $event = $this->make_submitted_event($quiz, (int) $studenta->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        learning_path_update::quiz_finished($event);
        $fired = $this->fired_userpath_ids($sink);
        $sink->close();

        $this->assertSame([$patha], $fired, 'Only the submitting student path must be recomputed.');
    }

    /**
     * A quiz used in several nodes triggers at most one recompute per path.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_single_recompute_when_quiz_in_multiple_nodes(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $course = $gen->create_course(['enablecompletion' => 1]);
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id);

        // The same quiz appears in two nodes of the same path.
        $pathid = $this->persist_path(
            (int) $student->id,
            (int) $course->id,
            (int) $teacher->id,
            $this->build_tree((int) $quiz->id, (int) $course->id, [(int) $quiz->id])
        );

        $event = $this->make_submitted_event($quiz, (int) $student->id, (int) $teacher->id);

        $sink = $this->redirectEvents();
        learning_path_update::quiz_finished($event);
        $fired = $this->fired_userpath_ids($sink);
        $sink->close();

        $this->assertSame([$pathid], $fired, 'A path must be recomputed once even if the quiz is used twice.');
    }

    /**
     * The observer registration targets attempt_submitted and no longer uses
     * the non-existent attempt_finished or the read-only attempt_reviewed.
     *
     * @coversNothing
     * @return void
     */
    public function test_quiz_observer_registration(): void {
        $observers = [];
        include(__DIR__ . '/../db/events.php');
        $eventnames = array_column($observers, 'eventname');

        $this->assertContains('\\mod_quiz\\event\\attempt_submitted', $eventnames);
        $this->assertNotContains('\\mod_quiz\\event\\attempt_reviewed', $eventnames);
        $this->assertNotContains('\\mod_quiz\\event\\attempt_finished', $eventnames);
    }
}
