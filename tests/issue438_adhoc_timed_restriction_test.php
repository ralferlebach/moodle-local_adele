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

use context_system;
use local_adele\event\user_path_updated;
use local_adele\event\learnpath_updated;
use local_adele\learning_path_update;
use local_adele\task\update_user_path;

require_once(__DIR__ . '/adele_learningpath_testcase.php'); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Integration tests for the #438 event-driven model: a timed node schedules a
 * per-user adhoc task that, when it fires, unlocks/enrols the learner without them
 * opening the course; and recomputing across time changes stays idempotent (no
 * double enrolment when a window is pushed back and brought forward again).
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class issue438_adhoc_timed_restriction_test extends adele_learningpath_testcase {
    /**
     * The fixture learning path this test suite subscribes users to.
     *
     * @return string
     */
    protected function fixturefile(): string {
        return 'alise_zugangs_lp_einfach.json';
    }

    /**
     * dndnode_2 becomes a single timed condition (placeholder future dates; each
     * test overwrites them).
     *
     * @param array $nodes
     */
    protected function patch_node_ids(array &$nodes): void {
        foreach ($nodes as &$node) {
            if (!isset($node['data']['course_node_id'])) {
                continue;
            }
            if ($node['id'] === 'dndnode_2') {
                $node['data']['course_node_id'] = [$this->courseids[2]];
                foreach ($node['restriction']['nodes'] as &$cn) {
                    if ($cn['id'] === 'condition_1') {
                        $cn['data']['label'] = 'timed';
                        $cn['data']['value'] = [
                            'start' => date('Y-m-d\TH:i', strtotime('+1 day')),
                            'end'   => date('Y-m-d\TH:i', strtotime('+7 days')),
                        ];
                    }
                }
                unset($cn);
                $node['restriction']['nodes'] = array_values(array_filter(
                    $node['restriction']['nodes'],
                    fn($cn) => !in_array($cn['id'], ['condition_2', 'condition_2_feedback'])
                ));
            } else {
                $node['data']['course_node_id'] = [$this->courseids[0], $this->courseids[3]];
            }
        }
        unset($node);
    }

    // Helpers.

    /**
     * Subscribe the two users and run the first evaluation (creates path_user rows).
     */
    private function subscribe_and_evaluate(): void {
        $this->subscribe_users_to_lp();
        foreach ($this->get_update_events() as $event) {
            relation_update::updated_single($event);
        }
    }

    /**
     * Overwrite dndnode_2's timed window in every active row WITHOUT recomputing.
     *
     * @param string $start
     * @param string $end
     */
    private function set_window(string $start, string $end): void {
        global $DB;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $record) {
            $json = json_decode($record->json, true);
            foreach ($json['tree']['nodes'] as &$tn) {
                if ($tn['id'] !== 'dndnode_2') {
                    continue;
                }
                foreach ($tn['restriction']['nodes'] as &$cn) {
                    if (($cn['id'] ?? '') === 'condition_1') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime($start));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime($end));
                    }
                }
                unset($cn);
            }
            unset($tn);
            $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        }
    }

    /**
     * Recompute every active path the normal way (as an LP edit or a view would).
     */
    private function recompute_via_event(): void {
        global $DB;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $record) {
            $decoded = json_decode($record->json, true);
            if (!is_array($decoded)) {
                continue;
            }
            $record->json = $decoded;
            $event = user_path_updated::create([
                'objectid' => $record->id,
                'context'  => context_system::instance(),
                'other'    => ['userpath' => $record],
            ]);
            relation_update::updated_single($event);
        }
    }

    /**
     * Fire every queued update_user_path adhoc task (simulates cron reaching the
     * scheduled boundaries). The harness redirects events into a sink that suppresses
     * observers, so close it first - exactly as production cron would dispatch.
     */
    private function run_adhoc_tasks(): void {
        if ($this->sink) {
            $this->sink->close();
            $this->sink = null;
        }
        foreach (\core\task\manager::get_adhoc_tasks('\\' . update_user_path::class) as $task) {
            ob_start();
            $task->execute();
            ob_end_clean();
        }
    }

    /**
     * Queued update_user_path tasks.
     */
    private function scheduled(): array {
        return \core\task\manager::get_adhoc_tasks('\\' . update_user_path::class);
    }

    /**
     * The set of course ids a user currently has any enrolment in (sorted).
     *
     * @param int $userid
     * @return array
     */
    private function enrolled_courseids(int $userid): array {
        global $DB;
        $sql = "SELECT DISTINCT e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid";
        $ids = array_map('intval', $DB->get_fieldset_sql($sql, ['userid' => $userid]));
        sort($ids);
        return $ids;
    }

    /**
     * A user id taken from the seeded learning-path rows.
     */
    private function a_userid(): int {
        global $DB;
        $records = $DB->get_records('local_adele_path_user', ['status' => 'active'], 'id', 'id, user_id', 0, 1);
        return (int) reset($records)->user_id;
    }

    /**
     * Active path_user rows keyed by id.
     *
     * @return array<int, object>
     */
    private function rows(): array {
        global $DB;
        return $DB->get_records('local_adele_path_user', ['status' => 'active']);
    }

    /**
     * Assert dndnode_2's restriction status across all active rows.
     *
     * @param string $statusrestriction
     * @param string $status
     */
    private function assert_node2_status(string $statusrestriction, string $status): void {
        $found = 0;
        foreach ($this->rows() as $row) {
            $json = json_decode($row->json, true);
            if (!is_array($json) || !isset($json['user_path_relation']['dndnode_2']['feedback'])) {
                continue;
            }
            $fb = $json['user_path_relation']['dndnode_2']['feedback'];
            $this->assertSame($statusrestriction, $fb['status_restriction'], "row {$row->id} status_restriction");
            $this->assertSame($status, $fb['status'], "row {$row->id} status");
            $found++;
        }
        $this->assertGreaterThan(0, $found, 'Expected at least one real learning-path row to assert on.');
    }

    // Tests.

    /**
     * Subscribing with a future window leaves the node locked but SCHEDULES a per-user
     * task for the boundary (reschedule_timed_restrictions_for_all_nodes covers locked
     * nodes, not just enrolled ones).
     */
    public function test_future_window_schedules_a_task(): void {
        $this->subscribe_and_evaluate();
        $this->assert_node2_status('before', 'not_accessible');
        $this->assertNotEmpty(
            $this->scheduled(),
            'A future timed window must queue a boundary task for each subscribed user.'
        );
    }

    /**
     * When the scheduled task fires and the window is open, the node unlocks and the
     * learner is enrolled into the node course - without ever opening the course.
     */
    public function test_scheduled_task_unlocks_and_enrols_when_window_open(): void {
        $this->subscribe_and_evaluate();                 // Future window -> locked, task queued.
        $uid = $this->a_userid();
        $this->assertNotContains(
            (int) $this->courseids[2],
            $this->enrolled_courseids($uid),
            'Precondition: the locked node course is not enrolled yet.'
        );

        $this->set_window('-1 hour', '+7 days');         // Boundary reached: window now open.
        $this->run_adhoc_tasks();                        // The queued task fires.

        $this->assert_node2_status('inbetween', 'accessible');
        $this->assertContains(
            (int) $this->courseids[2],
            $this->enrolled_courseids($uid),
            'The fired task must unlock the node and enrol the user.'
        );
    }

    /**
     * The core idempotency scenario: a user enrolled while the window matched, then the
     * time is pushed back (admin edit) so the node re-locks, then brought forward again.
     * The user must NOT be enrolled a second time in either transition.
     */
    public function test_enrolment_idempotent_across_time_changes(): void {
        $this->subscribe_and_evaluate();
        $this->set_window('-1 hour', '+7 days');
        $this->run_adhoc_tasks();                        // Open -> enrolled.
        $uid = $this->a_userid();
        $baseline = $this->enrolled_courseids($uid);
        $this->assertContains((int) $this->courseids[2], $baseline);

        // Time pushed back into the future -> node re-locks on recompute.
        $this->set_window('+5 days', '+30 days');
        $this->recompute_via_event();
        $this->assert_node2_status('before', 'not_accessible');
        $this->assertEquals(
            $baseline,
            $this->enrolled_courseids($uid),
            'Re-locking must not enrol the user again.'
        );

        // Time brought back so the window is open again -> recompute must be idempotent.
        $this->set_window('-1 hour', '+7 days');
        $this->recompute_via_event();
        $this->assert_node2_status('inbetween', 'accessible');
        $this->assertEquals(
            $baseline,
            $this->enrolled_courseids($uid),
            'Re-opening must not enrol the user a second time (idempotent).'
        );
    }

    /**
     * When the window expires the node closes but enrolment is neither duplicated nor
     * (currently) reversed - the enrolled course set is unchanged.
     *
     * FOR DISCUSSION / FUTURE WORK: whether a closing timed window should ALSO un-enrol
     * the learner is an open product decision (deferred). This pins current behaviour.
     */
    public function test_closing_window_keeps_enrolment(): void {
        $this->subscribe_and_evaluate();
        $this->set_window('-1 hour', '+7 days');
        $this->run_adhoc_tasks();                        // Open -> enrolled.
        $uid = $this->a_userid();
        $before = $this->enrolled_courseids($uid);
        $this->assertContains((int) $this->courseids[2], $before);

        $this->set_window('-30 days', '-1 hour');        // Window expired.
        $this->recompute_via_event();

        $this->assert_node2_status('after', 'not_accessible');
        $this->assertEquals(
            $before,
            $this->enrolled_courseids($uid),
            'Closing the window must not change enrolment (no re-enrol, no spurious enrolment).'
        );
    }

    /**
     * The headline "changing the time restriction" case, driven through the REAL editor
     * save path: learnpath_updated -> updated_learning_path -> passnodevalues (snapshot
     * rebuilt from the new master tree) -> updated_single -> reschedule. Every user's
     * boundary task must move to the new time WITHOUT duplicating.
     */
    public function test_editing_date_in_editor_reschedules_all_user_tasks(): void {
        global $DB;
        $this->subscribe_and_evaluate();                 // Future window -> tasks queued at +1 day.
        $before = $this->scheduled();
        $countbefore = count($before);
        $this->assertGreaterThan(0, $countbefore, 'Precondition: tasks were queued for the future window.');

        // The propagation fires user_path_updated for each user; close the sink so the
        // observer (updated_single) actually runs, exactly as production cron/web would.
        if ($this->sink) {
            $this->sink->close();
            $this->sink = null;
        }

        // Edit dndnode_2's window to +5/+30 days in the MASTER learning path (an editor save).
        $lpid = (int) $DB->get_field('local_adele_path_user', 'learning_path_id', [], IGNORE_MULTIPLE);
        $masterarr = json_decode($DB->get_field('local_adele_learning_paths', 'json', ['id' => $lpid]), true);
        foreach ($masterarr['tree']['nodes'] as &$tn) {
            if (($tn['id'] ?? '') === 'dndnode_2') {
                foreach ($tn['restriction']['nodes'] as &$cn) {
                    if (($cn['id'] ?? '') === 'condition_1') {
                        $cn['data']['value']['start'] = date('Y-m-d\TH:i', strtotime('+5 days'));
                        $cn['data']['value']['end']   = date('Y-m-d\TH:i', strtotime('+30 days'));
                    }
                }
                unset($cn);
            }
        }
        unset($tn);
        $newjson = json_encode($masterarr);
        $DB->set_field('local_adele_learning_paths', 'json', $newjson, ['id' => $lpid]);

        // Fire the genuine editor-save propagation.
        $event = learnpath_updated::create([
            'objectid' => $lpid,
            'context'  => context_system::instance(),
            'other'    => [
                'learningpathname' => 'Repro',
                'learningpathid'   => $lpid,
                'userid'           => 2,
                'json'             => $newjson,
            ],
        ]);
        learning_path_update::updated_learning_path($event);

        // Rescheduled, not duplicated.
        $after = $this->scheduled();
        $this->assertCount(
            $countbefore,
            $after,
            'Editing the restriction date must reschedule the existing tasks, not create duplicates.'
        );

        // The start-slot tasks now fire at the new boundary (~ +5 days).
        $starttasks = array_filter(
            $after,
            fn($t) => str_contains(json_encode($t->get_custom_data()), '_start_')
        );
        $this->assertNotEmpty($starttasks);
        foreach ($starttasks as $t) {
            $this->assertEqualsWithDelta(
                strtotime('+5 days') + 120,
                $t->get_next_run_time(),
                120,
                'The start-boundary task must be rescheduled to the edited date.'
            );
        }
    }

    /**
     * Removing the timed condition in the editor must unlock the node on the next
     * recompute (snapshot is rebuilt from the new master tree, which no longer carries
     * the restriction). Any already-queued task for the deleted slot is harmless: it
     * is never rescheduled, and when it fires it just re-reads the current json.
     */
    public function test_removing_condition_in_editor_unlocks_and_enrols(): void {
        global $DB;
        $this->subscribe_and_evaluate();                 // Future window -> locked.
        $uid = $this->a_userid();
        $this->assert_node2_status('before', 'not_accessible');
        $this->assertNotContains((int) $this->courseids[2], $this->enrolled_courseids($uid));

        if ($this->sink) {
            $this->sink->close();
            $this->sink = null;
        }

        // Remove dndnode_2's restriction entirely in the master.
        $lpid = (int) $DB->get_field('local_adele_path_user', 'learning_path_id', [], IGNORE_MULTIPLE);
        $masterarr = json_decode($DB->get_field('local_adele_learning_paths', 'json', ['id' => $lpid]), true);
        foreach ($masterarr['tree']['nodes'] as &$tn) {
            if (($tn['id'] ?? '') === 'dndnode_2') {
                $tn['restriction']['nodes'] = [];
            }
        }
        unset($tn);
        $newjson = json_encode($masterarr);
        $DB->set_field('local_adele_learning_paths', 'json', $newjson, ['id' => $lpid]);

        $event = learnpath_updated::create([
            'objectid' => $lpid,
            'context'  => context_system::instance(),
            'other'    => [
                'learningpathname' => 'Repro',
                'learningpathid'   => $lpid,
                'userid'           => 2,
                'json'             => $newjson,
            ],
        ]);
        learning_path_update::updated_learning_path($event);

        // No restriction -> node accessible and the user enrolled into its course.
        $this->assertContains(
            (int) $this->courseids[2],
            $this->enrolled_courseids($uid),
            'Removing the restriction must unlock the node and enrol the user.'
        );
    }

    /**
     * A task that fires while the window is STILL closed (e.g. an early/stale fire) must
     * recompute to the real state and leave the node locked - never blindly unlock.
     */
    public function test_task_firing_on_still_closed_window_does_not_enrol(): void {
        $this->subscribe_and_evaluate();                 // Future window -> locked, task queued.
        $uid = $this->a_userid();

        $this->run_adhoc_tasks();                        // Task fires, window still in the future.

        $this->assert_node2_status('before', 'not_accessible');
        $this->assertNotContains(
            (int) $this->courseids[2],
            $this->enrolled_courseids($uid),
            'Firing the task while the window is still closed must not enrol the user.'
        );
    }

    /**
     * After a node was opened+enrolled, the window is pushed back (re-lock). A stale task
     * still in the queue firing against the re-locked window must not change enrolment.
     */
    public function test_stale_task_after_relock_keeps_enrolment(): void {
        $this->subscribe_and_evaluate();
        $this->set_window('-1 hour', '+7 days');
        $this->run_adhoc_tasks();                        // Open -> enrolled.
        $uid = $this->a_userid();
        $baseline = $this->enrolled_courseids($uid);
        $this->assertContains((int) $this->courseids[2], $baseline);

        // Push the window back into the future -> node re-locks.
        $this->set_window('+5 days', '+30 days');
        $this->recompute_via_event();
        $this->assert_node2_status('before', 'not_accessible');

        // Any stale task firing now must be harmless.
        $this->run_adhoc_tasks();
        $this->assertEquals(
            $baseline,
            $this->enrolled_courseids($uid),
            'A stale task firing after re-lock must not change enrolment.'
        );
    }
}
