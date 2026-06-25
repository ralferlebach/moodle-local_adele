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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_adele;

use advanced_testcase;
use local_adele\helper\adhoc_task_helper;
use local_adele\relation_update;
use local_adele\task\update_user_path;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Unit tests for the #438 event-driven scheduler (adhoc_task_helper): a per-user
 * adhoc task is queued for each FUTURE timed boundary, past boundaries are skipped,
 * editing a date reschedules (never duplicates) the task, and timed_duration windows
 * are scheduled at their computed close time.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\helper\adhoc_task_helper
 */
final class issue438_adhoc_scheduling_test extends advanced_testcase {

    /** RUNTIME_BUFFER mirrored from the helper (boundary -> nextruntime offset). */
    private const BUFFER = 120;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /** A userpath stub carrying the fields the scheduler reads. */
    private function userpath(int $id = 11, int $timecreated = 0): \stdClass {
        return (object) [
            'id' => $id,
            'learning_path_id' => 1,
            'user_id' => 2,
            'course_id' => 3,
            'timecreated' => $timecreated,
        ];
    }

    /** A node carrying a single timed restriction with the given start/end. */
    private function timed_node(string $startexpr, string $endexpr): array {
        return ['restriction' => ['nodes' => [[
            'id' => 'r1',
            'data' => ['label' => 'timed', 'value' => [
                'start' => $startexpr === '' ? '' : date('Y-m-d\TH:i', strtotime($startexpr)),
                'end'   => $endexpr === '' ? '' : date('Y-m-d\TH:i', strtotime($endexpr)),
            ]],
        ]]]];
    }

    /** All currently queued update_user_path tasks, sorted by next run time. */
    private function scheduled(): array {
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . update_user_path::class);
        usort($tasks, fn($a, $b) => $a->get_next_run_time() <=> $b->get_next_run_time());
        return $tasks;
    }

    /** A future timed start AND end each queue a task at boundary + buffer. */
    public function test_future_start_and_end_are_scheduled(): void {
        $start = strtotime('+1 day');
        $end = strtotime('+7 days');
        adhoc_task_helper::set_scheduled_adhoc_tasks(
            $this->timed_node('+1 day', '+7 days'), $this->userpath());

        $tasks = $this->scheduled();
        $this->assertCount(2, $tasks);
        // strtotime on the 'Y-m-d\TH:i' string truncates seconds, so allow a minute slack.
        $this->assertEqualsWithDelta($start + self::BUFFER, $tasks[0]->get_next_run_time(), 60);
        $this->assertEqualsWithDelta($end + self::BUFFER, $tasks[1]->get_next_run_time(), 60);
    }

    /** A boundary already in the past is granted synchronously, never scheduled. */
    public function test_past_boundary_is_not_scheduled(): void {
        adhoc_task_helper::set_scheduled_adhoc_tasks(
            $this->timed_node('-1 hour', '-30 minutes'), $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** An empty slot (only start, no end) schedules just the one boundary. */
    public function test_empty_slot_is_ignored(): void {
        adhoc_task_helper::set_scheduled_adhoc_tasks(
            $this->timed_node('+1 day', ''), $this->userpath());
        $this->assertCount(1, $this->scheduled());
    }

    /**
     * Editing the date must UPDATE the existing task (same slot dedup key), not leave
     * a stale duplicate - this is the core correctness property of the design.
     */
    public function test_editing_date_reschedules_without_duplicating(): void {
        $up = $this->userpath();
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+1 day', ''), $up);
        $this->assertCount(1, $this->scheduled());

        // Admin moves the start later; same slot -> one task, new run time.
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+3 days', ''), $up);
        $tasks = $this->scheduled();
        $this->assertCount(1, $tasks, 'Rescheduling must not create a duplicate task.');
        $this->assertEqualsWithDelta(strtotime('+3 days') + self::BUFFER, $tasks[0]->get_next_run_time(), 60);
    }

    /** Two different userpaths get their own task (dedup key includes userpath id). */
    public function test_distinct_userpaths_get_distinct_tasks(): void {
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+1 day', ''), $this->userpath(11));
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+1 day', ''), $this->userpath(22));
        $this->assertCount(2, $this->scheduled());
    }

    /** timed_duration: the window close (start ref + duration) is scheduled. */
    public function test_timed_duration_close_is_scheduled(): void {
        $enrolled = time() - 10;
        $node = [
            'data' => ['first_enrolled' => $enrolled],
            'restriction' => ['nodes' => [[
                'id' => 'rd',
                'data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '1',        // enrolment-based start.
                    'durationValue' => '0',         // days.
                    'selectedDuration' => 1,        // one day.
                ]],
            ]]],
        ];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());

        $tasks = $this->scheduled();
        $this->assertCount(1, $tasks);
        $expectedclose = $enrolled + 86400; // DURATION_SECONDS[days] * 1.
        $this->assertEqualsWithDelta($expectedclose + self::BUFFER, $tasks[0]->get_next_run_time(), 5);
    }

    /** timed_duration whose window already closed is not scheduled (past boundary). */
    public function test_timed_duration_already_closed_is_not_scheduled(): void {
        $node = [
            'data' => ['first_enrolled' => time() - (86400 * 3)], // enrolled 3 days ago.
            'restriction' => ['nodes' => [[
                'id' => 'rd',
                'data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '1',
                    'durationValue' => '0',     // days.
                    'selectedDuration' => 1,    // 1-day window -> closed 2 days ago.
                ]],
            ]]],
        ];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** A node without restrictions schedules nothing and does not error. */
    public function test_node_without_restrictions_is_noop(): void {
        adhoc_task_helper::set_scheduled_adhoc_tasks(['data' => ['course_node_id' => [3]]], $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    // -------------------------------------------------- additional edge cases.

    /** Two distinct restriction nodes on one node each get their own task. */
    public function test_multiple_restriction_nodes_each_get_a_task(): void {
        $node = ['restriction' => ['nodes' => [
            ['id' => 'rA', 'data' => ['label' => 'timed', 'value' => [
                'start' => date('Y-m-d\TH:i', strtotime('+1 day')), 'end' => '']]],
            ['id' => 'rB', 'data' => ['label' => 'timed', 'value' => [
                'start' => date('Y-m-d\TH:i', strtotime('+2 days')), 'end' => '']]],
        ]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(2, $this->scheduled());
    }

    /** A timed AND a timed_duration restriction on the same node both schedule. */
    public function test_timed_and_timed_duration_on_same_node(): void {
        $node = [
            'data' => ['first_enrolled' => time() - 10],
            'restriction' => ['nodes' => [
                ['id' => 'rt', 'data' => ['label' => 'timed', 'value' => [
                    'start' => date('Y-m-d\TH:i', strtotime('+1 day')), 'end' => '']]],
                ['id' => 'rd', 'data' => ['label' => 'timed_duration', 'value' => [
                    'selectedOption' => '1', 'durationValue' => '0', 'selectedDuration' => 1]]],
            ]],
        ];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(2, $this->scheduled());
    }

    /** A malformed date string is skipped, not fataled. */
    public function test_malformed_date_is_skipped(): void {
        $node = ['restriction' => ['nodes' => [[
            'id' => 'r1', 'data' => ['label' => 'timed', 'value' => ['start' => 'not-a-date', 'end' => '']],
        ]]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** timed_duration based on path creation time (selectedOption 0). */
    public function test_timed_duration_timecreated_based(): void {
        $created = time() - 100;
        $node = ['restriction' => ['nodes' => [[
            'id' => 'rd', 'data' => ['label' => 'timed_duration', 'value' => [
                'selectedOption' => '0', 'durationValue' => '1', 'selectedDuration' => 1]], // weeks.
        ]]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath(11, $created));
        $tasks = $this->scheduled();
        $this->assertCount(1, $tasks);
        $this->assertEqualsWithDelta($created + 604800 + self::BUFFER, $tasks[0]->get_next_run_time(), 5);
    }

    /** timed_duration in months uses the averaged month constant. */
    public function test_timed_duration_months(): void {
        $enrolled = time() - 10;
        $node = ['data' => ['first_enrolled' => $enrolled], 'restriction' => ['nodes' => [[
            'id' => 'rd', 'data' => ['label' => 'timed_duration', 'value' => [
                'selectedOption' => '1', 'durationValue' => '2', 'selectedDuration' => 1]], // months.
        ]]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $tasks = $this->scheduled();
        $this->assertCount(1, $tasks);
        $this->assertEqualsWithDelta($enrolled + 2629746 + self::BUFFER, $tasks[0]->get_next_run_time(), 5);
    }

    /** Enrolment-based timed_duration cannot be scheduled before enrolment (no first_enrolled). */
    public function test_timed_duration_without_first_enrolled_is_not_scheduled(): void {
        $node = ['data' => [], 'restriction' => ['nodes' => [[
            'id' => 'rd', 'data' => ['label' => 'timed_duration', 'value' => [
                'selectedOption' => '1', 'durationValue' => '0', 'selectedDuration' => 1]],
        ]]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** An incomplete timed_duration (no durationValue) schedules nothing. */
    public function test_incomplete_timed_duration_is_not_scheduled(): void {
        $node = ['data' => ['first_enrolled' => time()], 'restriction' => ['nodes' => [[
            'id' => 'rd', 'data' => ['label' => 'timed_duration', 'value' => ['selectedOption' => '1']],
        ]]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** Re-running the identical schedule must not churn the task (same row, same time). */
    public function test_identical_reschedule_is_noop(): void {
        $up = $this->userpath();
        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+1 day', ''), $up);
        $first = $this->scheduled();
        $this->assertCount(1, $first);
        $runtime = $first[0]->get_next_run_time();

        adhoc_task_helper::set_scheduled_adhoc_tasks($this->timed_node('+1 day', ''), $up);
        $second = $this->scheduled();
        $this->assertCount(1, $second, 'Identical reschedule must not duplicate.');
        $this->assertEquals($runtime, $second[0]->get_next_run_time(), 'Identical reschedule must not change the run time.');
    }

    /** A restriction node missing label/value is skipped without error. */
    public function test_restriction_without_label_is_skipped(): void {
        $node = ['restriction' => ['nodes' => [
            ['id' => 'feedbacknode'],                                  // no data/label (e.g. a feedback node).
            ['id' => 'r1', 'data' => []],                              // no label.
        ]]];
        adhoc_task_helper::set_scheduled_adhoc_tasks($node, $this->userpath());
        $this->assertCount(0, $this->scheduled());
    }

    /** reschedule_timed_restrictions_for_all_nodes covers every node and skips dropzones. */
    public function test_reschedule_all_nodes_skips_dropzone(): void {
        $userpath = (object) [
            'id' => 11, 'learning_path_id' => 1, 'user_id' => 2, 'course_id' => 3, 'timecreated' => 0,
            'json' => ['tree' => ['nodes' => [
                ['id' => 'n1', 'type' => 'default', 'restriction' => ['nodes' => [[
                    'id' => 'r1', 'data' => ['label' => 'timed', 'value' => [
                        'start' => date('Y-m-d\TH:i', strtotime('+1 day')), 'end' => '']]]]]],
                ['id' => 'dz', 'type' => 'dropzone', 'restriction' => ['nodes' => [[
                    'id' => 'rz', 'data' => ['label' => 'timed', 'value' => [
                        'start' => date('Y-m-d\TH:i', strtotime('+1 day')), 'end' => '']]]]]],
                ['id' => 'n2', 'type' => 'default', 'restriction' => ['nodes' => [[
                    'id' => 'r2', 'data' => ['label' => 'timed', 'value' => [
                        'start' => date('Y-m-d\TH:i', strtotime('+2 days')), 'end' => '']]]]]],
            ]]],
        ];
        relation_update::reschedule_timed_restrictions_for_all_nodes($userpath);
        // n1 + n2 scheduled, dropzone NOT.
        $this->assertCount(2, $this->scheduled());
    }

    /**
     * The task firing against a path that has since been deleted/deactivated must be a
     * safe no-op (the PK lookup returns nothing and execute() bails) - not a fatal.
     */
    public function test_execute_with_missing_path_is_safe(): void {
        $task = new update_user_path();
        $task->set_custom_data((object) [
            'userpath_id' => 999999, 'learning_path_id' => 1, 'user_id' => 2, 'course_id' => 3, 'time' => 'x',
        ]);
        $task->execute(); // Must not throw.
        $this->assertDebuggingNotCalled();
    }
}
