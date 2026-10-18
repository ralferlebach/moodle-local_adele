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
use context_course;
use core\event\course_completed;

// The attributes stand above the class comment: between comment and class
// they would hide the class-level @covers tags from the coding-style check.
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
/**
 * A deleted node course does not stop the evaluation of the path (#601).
 *
 * The path of the fixture stackedorcourses.json: node 1 = course A, node 2 =
 * a stack of B, a course that has since been deleted, and C (one of them is
 * enough), behind node 1. Embedded into A with the participant source "this
 * course". With the real observers (no event sink): enrolling a learner,
 * finishing A, finishing C - none of it may throw, and the courses that do
 * exist are evaluated as before. The deleted course counts as not completed
 * and nobody is enrolled into it.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_path_update::get_course
 * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
 * @covers \local_adele\learning_paths::checknodeprogression
 * @runTestsInSeparateProcesses
 */
final class issue601_deleted_node_course_test extends advanced_testcase {
    /** @var \stdClass[] Courses A, B, C by key. */
    private array $courses = [];

    /** @var int The id the deleted course had. */
    private int $goneid = 0;

    /** @var int The learning path. */
    private int $lpid = 0;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        foreach (['A', 'B', 'C'] as $key) {
            $this->courses[$key] = $gen->create_course([
                'shortname' => 'I601-' . $key,
                'fullname' => 'Issue 601 course ' . $key,
                'enablecompletion' => 1,
            ]);
        }
        $gone = $gen->create_course(['shortname' => 'I601-GONE', 'enablecompletion' => 1]);
        $this->goneid = (int) $gone->id;

        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/stackedorcourses.json'), true);
        $tree = json_decode($fixture['json'], true);
        foreach ($tree['tree']['nodes'] as &$node) {
            if (($node['id'] ?? '') === 'dndnode_1') {
                $node['data']['course_node_id'] = [(int) $this->courses['A']->id];
            } else if (($node['id'] ?? '') === 'dndnode_2') {
                $node['data']['course_node_id'] = [
                    (int) $this->courses['B']->id,
                    $this->goneid,
                    (int) $this->courses['C']->id,
                ];
            }
        }
        unset($node);
        $fixture['json'] = json_encode($tree);
        $this->lpid = (int) $gen->get_plugin_generator('local_adele')->create_adele_learningpaths($fixture);

        // The course goes - after it was put into the path, as in real life.
        delete_course($gone, false);
        $this->assertFalse($DB->record_exists('course', ['id' => $this->goneid]));

        $gen->create_module('adele', [
            'course' => $this->courses['A']->id,
            'learningpathid' => $this->lpid,
            'participantslist' => [1],
            'view' => 1,
            'userlist' => 1,
        ]);
    }

    /**
     * The learner's stored path.
     *
     * @param int $userid The learner.
     * @return array|null The decoded json, or null without a subscription.
     */
    private function path(int $userid): ?array {
        global $DB;
        $json = $DB->get_field('local_adele_path_user', 'json', [
            'user_id' => $userid,
            'learning_path_id' => $this->lpid,
            'status' => 'active',
        ]);
        return $json ? json_decode($json, true) : null;
    }

    /**
     * Finish a course for a learner the way Moodle does: a completion row and
     * the core event, handled by the real observers.
     *
     * @param \stdClass $course The course.
     * @param int $userid The learner.
     */
    private function finish(\stdClass $course, int $userid): void {
        global $DB;
        $id = (int) $DB->insert_record('course_completions', (object) [
            'course' => $course->id,
            'userid' => $userid,
            'timeenrolled' => time(),
            'timestarted' => time(),
            'timecompleted' => time(),
            'reaggregate' => 0,
        ]);
        \cache::make('core', 'coursecompletion')->delete($userid . '_' . $course->id);
        course_completed::create([
            'objectid' => $id,
            'relateduserid' => $userid,
            'context' => context_course::instance($course->id),
            'courseid' => $course->id,
            'other' => ['relateduserid' => $userid],
        ])->trigger();
    }

    /**
     * Enrolling, finishing the first node, finishing a course of the stack:
     * no exception, and the courses that exist count as before.
     */
    public function test_the_path_is_evaluated_around_the_deleted_course(): void {
        $learner = $this->getDataGenerator()->create_and_enrol($this->courses['A'], 'student');
        $this->assertNotNull($this->path((int) $learner->id), 'Enrolled into the embedding course: on the path.');

        $this->finish($this->courses['A'], (int) $learner->id);
        // What the observers queued runs as well (enrol_adele, timed checks).
        $this->runAdhocTasks();
        $this->setAdminUser();
        foreach (['B', 'C'] as $key) {
            $this->assertTrue(
                is_enrolled(context_course::instance($this->courses[$key]->id), $learner->id),
                "Node 1 finished: enrolled into $key of the stack."
            );
        }
        $this->assertSame(
            'accessible',
            $this->path((int) $learner->id)['user_path_relation']['dndnode_2']['feedback']['status'] ?? null,
            'The stack is open once node 1 is done.'
        );

        $this->finish($this->courses['C'], (int) $learner->id);
        $this->runAdhocTasks();
        $relation = $this->path((int) $learner->id)['user_path_relation']['dndnode_2'] ?? [];
        $completed = $relation['completioncriteria']['course_completed']['completed'] ?? [];
        $this->assertTrue($completed[$this->courses['C']->id] ?? null, 'C is completed.');
        $this->assertFalse($completed[$this->goneid] ?? null, 'The deleted course counts as not completed.');
        $this->assertSame(
            'completed',
            $relation['feedback']['status'] ?? null,
            'One course of the stack is enough - C counts although another course of the stack is gone.'
        );
    }

    /**
     * The deleted course itself: not completed, no progress, no error.
     */
    public function test_the_deleted_course_counts_as_not_completed(): void {
        $learner = $this->getDataGenerator()->create_and_enrol($this->courses['A'], 'student');
        global $DB;
        $node = null;
        $json = json_decode((string) $DB->get_field('local_adele_learning_paths', 'json', ['id' => $this->lpid]));
        foreach ($json->tree->nodes ?? [] as $n) {
            if ($n->id === 'dndnode_2') {
                $node = $n;
            }
        }
        $this->assertNotNull($node);
        learning_paths::checknodeprogression($node, (int) $learner->id);

        $condition = new course_completion\conditions\course_completed();
        $status = $condition->get_completion_status(json_decode(json_encode($node), true), (int) $learner->id);
        $this->assertFalse($status['completed'][$this->goneid] ?? null, 'The deleted course is not completed.');
        $this->assertFalse($status['completed'][$this->courses['B']->id] ?? null, 'Nor is B, which exists.');
    }
}
