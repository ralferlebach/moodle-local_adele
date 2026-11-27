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

use core_external\external_api;
use enrol_adele\local\instance_manager;
use enrol_adele\local\teacher_grants;
use enrol_adele\task\remove_user_path_adhoc;
use local_adele\external\get_group_overview;
use local_adele\external\get_live_snapshot;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\get_non_learners;
use mod_adele\local\teacher_access_mode as mode;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * The roster through a learner's life, by the real paths (re-audit
 * 2026-11-27, section 3; #13 AK1.5).
 *
 * Nothing is written into enrolment tables here: learners come in by being
 * enrolled in the starting node course of an embedding with sources 2/3 in
 * host mode "hidden" (mod_adele's observer subscribes them and enrol_adele
 * keeps them enrolled "without access" in the host course), leave by being
 * unenrolled there (enrol_adele's observer withdraws and queues the deferred
 * removal), and the removal runs as the task it is. Teachers come in through
 * the teacher grants of the teacher view (enrol_adele kind 3).
 *
 * Decided by the client on 2026-11-27: a learner who left stays on the list
 * for the grace period of the deferred removal - remove_user_path_adhoc::
 * DELAY_SECONDS (300 s) plus the time to the next cron run - which keeps the
 * learning history safe from a resync blip. The tests pin that window down:
 * it is exactly the queued task, and after it nothing of the learner is left
 * in any view.
 *
 * Every case is checked on the same cohort in all the views the roster
 * feeds: participant list, group overview, live markers and counter, and
 * the list of non-learners.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\learning_paths::roster_members_sql
 * @covers     \local_adele\cohort
 * @covers     \enrol_adele\observer
 * @covers     \enrol_adele\task\remove_user_path_adhoc
 */
final class roster_lifecycle_test extends groups_live_testcase {
    /** @var \stdClass The embedding: sources 2/3, host mode "hidden", visible groups. */
    private $activity;

    /** @var \stdClass The one group everybody of the cohort is in (for the group overview). */
    private $everybody;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $this->setAdminUser();
        $this->make_world();
        $this->everybody = $this->group('Everybody');
        $this->activity = $this->embed(VISIBLEGROUPS, [
            'participantslist' => [2, 3], 'hostenrolmentmode' => 'hidden', 'livemarkers' => 1,
        ]);
        $this->runAdhocTasks();
    }

    /**
     * A learner who comes in the real way: enrolled in the starting node
     * course, seen there just now.
     *
     * @param string $lastname
     * @return \stdClass
     */
    private function newcomer(string $lastname): \stdClass {
        $user = $this->getDataGenerator()->create_user(['lastname' => $lastname, 'firstname' => 'L']);
        $this->getDataGenerator()->enrol_user($user->id, $this->courses['c1']->id, 'student');
        $this->runAdhocTasks();
        $this->getDataGenerator()->create_group_member(['groupid' => $this->everybody->id, 'userid' => $user->id]);
        $this->seen($user, 'c1', time());
        return $user;
    }

    /**
     * The person's ADELE host enrolment (kind 2), or false.
     *
     * @param \stdClass $user
     * @return \stdClass|false
     */
    private function hostue(\stdClass $user) {
        global $DB;
        return $DB->get_record_sql(
            "SELECT ue.* FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :c AND e.enrol = 'adele' AND e.customint2 = :kind AND ue.userid = :u",
            ['c' => $this->host->id, 'kind' => instance_manager::KIND_HOST, 'u' => $user->id]
        );
    }

    /**
     * Unenrol a person from the starting node course the way Moodle does.
     *
     * @param \stdClass $user
     * @return void
     */
    private function leave(\stdClass $user): void {
        global $DB;
        $instance = $DB->get_record('enrol', ['courseid' => $this->courses['c1']->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $user->id);
    }

    /**
     * The queued deferred removals: userid => next run time.
     *
     * @return array
     */
    private function queued_removals(): array {
        global $DB;
        $out = [];
        foreach ($DB->get_records('task_adhoc', ['classname' => '\\' . remove_user_path_adhoc::class]) as $row) {
            $data = json_decode($row->customdata, true);
            if ((int) $data['learningpathid'] === $this->lpid) {
                $out[(int) $data['userid']] = (int) $row->nextruntime;
            }
        }
        return $out;
    }

    /**
     * Run the deferred removals as cron would once their time has come.
     *
     * @return void
     */
    private function after_grace(): void {
        $task = \core\task\manager::get_next_adhoc_task(time() + remove_user_path_adhoc::DELAY_SECONDS + 1);
        while ($task !== null) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
            $task = \core\task\manager::get_next_adhoc_task(time() + remove_user_path_adhoc::DELAY_SECONDS + 1);
        }
    }

    /**
     * Everything the roster decides, as a teacher of the host course sees it.
     *
     * @return array learners, live (active ids on n1), active (counter), measured and members (group
     *     overview), nonlearners (id => reason)
     */
    private function views(): array {
        global $USER;
        $me = $USER;
        $this->setUser($this->viewer);
        $ctx = $this->ctx($this->activity);
        $relations = get_lp_user_path_relations::execute(0, $this->lpid, $ctx, 0);
        $snapshot = external_api::clean_returnvalue(
            get_live_snapshot::execute_returns(),
            get_live_snapshot::execute($ctx, 0)
        );
        $n1 = array_column($snapshot['nodes'], null, 'nodeid')['n1'];
        $overview = external_api::clean_returnvalue(
            get_group_overview::execute_returns(),
            get_group_overview::execute($ctx, 0)
        );
        $nonlearners = external_api::clean_returnvalue(
            get_non_learners::execute_returns(),
            get_non_learners::execute($ctx, 0)
        );
        $this->setUser($me);
        $learners = array_values(array_map('intval', array_column($relations, 'id')));
        sort($learners);
        $live = array_map('intval', array_column(array_filter($n1['markers'], fn($m) => $m['state'] === 'active'), 'userid'));
        sort($live);
        return [
            'learners' => $learners,
            'live' => $live,
            'active' => (int) $n1['active'],
            // The group overview of a teacher: the averages of the one group.
            'measured' => (int) $overview['averages'][0]['measured'],
            'members' => (int) $overview['averages'][0]['members'],
            'nonlearners' => array_column($nonlearners, 'reason', 'userid'),
        ];
    }

    /** @var \stdClass The teacher looking at the activity. */
    private $viewer;

    /**
     * Assert one person's place in every view.
     *
     * @param array $views From views().
     * @param \stdClass $user
     * @param bool $learner Listed as a learner (participant list, live markers).
     * @param string|null $nonlearner The reason in the non-learner list, null when not there.
     * @param string $when For the messages.
     * @return void
     */
    private function assert_place(array $views, \stdClass $user, bool $learner, ?string $nonlearner, string $when): void {
        $id = (int) $user->id;
        $who = $user->lastname . ' ' . $when;
        $this->assertSame($learner, in_array($id, $views['learners'], true), "{$who}: participant list");
        $this->assertSame($learner, in_array($id, $views['live'], true), "{$who}: live markers");
        $this->assertSame($nonlearner, $views['nonlearners'][$id] ?? null, "{$who}: non-learner list");
    }

    /**
     * Assert the counters: live counter on node 1, and the group overview's
     * learners measured (with the path) and members (participants).
     *
     * @param array $views From views().
     * @param int $active
     * @param int $measured
     * @param int $members
     * @param string $when
     * @return void
     */
    private function assert_counts(array $views, int $active, int $measured, int $members, string $when): void {
        $this->assertSame(
            ['active' => $active, 'measured' => $measured, 'members' => $members],
            ['active' => $views['active'], 'measured' => $views['measured'], 'members' => $views['members']],
            "counters {$when}"
        );
    }

    /**
     * A departure in host mode "hidden": on the list for exactly the grace
     * period, then gone from every view - together with a learner who stays,
     * one an admin suspends from outside, one removed from outside, and one
     * with a parallel active enrolment who leaves the path but not the course.
     */
    public function test_departure_external_changes_and_a_parallel_enrolment(): void {
        global $DB;
        $stays = $this->newcomer('Stays');
        $leaves = $this->newcomer('Leaves');
        $suspended = $this->newcomer('Suspended');
        $removed = $this->newcomer('Removed');
        $parallel = $this->newcomer('Parallel');
        $this->getDataGenerator()->enrol_user($parallel->id, $this->host->id, 'student', 'manual');
        $this->viewer = $this->teacher();

        // Coming in: subscribed, enrolled "without access", on every list.
        foreach ([$stays, $leaves, $suspended, $removed, $parallel] as $user) {
            $ue = $this->hostue($user);
            $this->assertNotFalse($ue, $user->lastname . ': ADELE keeps a host enrolment');
            $this->assertSame(ENROL_USER_SUSPENDED, (int) $ue->status, $user->lastname . ': without access');
        }
        $views = $this->views();
        foreach ([$stays, $leaves, $suspended, $removed, $parallel] as $user) {
            $this->assert_place($views, $user, true, null, 'on arrival');
        }
        $this->assert_counts($views, 5, 5, 5, 'on arrival');

        // From outside: an admin suspends one hidden enrolment (it already is
        // suspended - nothing to tell it apart from ADELE's own), and removes
        // another one.
        $plugin = enrol_get_plugin('adele');
        $hostinstance = $DB->get_record('enrol', ['id' => $this->hostue($suspended)->enrolid], '*', MUST_EXIST);
        $plugin->update_user_enrol($hostinstance, $suspended->id, ENROL_USER_SUSPENDED);
        $plugin->unenrol_user($hostinstance, $removed->id);

        // Leaving the starting node course: access goes at once, the removal
        // is queued for exactly the grace period.
        $before = time();
        $this->leave($leaves);
        $this->leave($parallel);
        $queued = $this->queued_removals();
        $this->assertArrayHasKey((int) $leaves->id, $queued, 'the departure queues the deferred removal');
        $this->assertGreaterThanOrEqual($before + remove_user_path_adhoc::DELAY_SECONDS, $queued[(int) $leaves->id]);
        $this->assertLessThanOrEqual(time() + remove_user_path_adhoc::DELAY_SECONDS, $queued[(int) $leaves->id]);
        $this->assertSame(ENROL_USER_SUSPENDED, (int) $this->hostue($leaves)->status);

        $views = $this->views();
        $this->assert_place($views, $stays, true, null, 'while others leave');
        $this->assert_place($views, $suspended, true, null, 'suspended from outside');
        $this->assert_place($views, $removed, false, null, 'removed from outside');
        // The accepted window (2026-11-27): during the grace period the one
        // who left is still listed.
        $this->assert_place($views, $leaves, true, null, 'in the grace period');
        $this->assert_place($views, $parallel, true, null, 'in the grace period');
        $this->assert_counts($views, 4, 4, 4, 'in the grace period');

        // The grace period over: nothing of the one who left is left anywhere.
        $this->after_grace();
        $this->assertFalse($this->hostue($leaves), 'the host enrolment is removed');
        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['user_id' => $leaves->id, 'learning_path_id' => $this->lpid]),
            'the snapshot is removed'
        );
        $views = $this->views();
        $this->assert_place($views, $leaves, false, null, 'after the grace period');
        $this->assertFalse(learning_paths::is_roster_member((int) $this->host->id, (int) $leaves->id));
        // The parallel enrolment carries the person in the course - but no
        // longer on the path: a member of the course without the path.
        $this->assertTrue(learning_paths::is_roster_member((int) $this->host->id, (int) $parallel->id));
        $this->assert_place($views, $parallel, false, 'nopath', 'after the grace period');
        // Nobody else moved.
        $this->assert_place($views, $stays, true, null, 'after the grace period');
        $this->assert_place($views, $suspended, true, null, 'after the grace period');
        // The group counts the course member without the path among its
        // members, not among those measured.
        $this->assert_counts($views, 2, 2, 3, 'after the grace period');
    }

    /**
     * A person whose only enrolment in the host course is an active ADELE
     * teacher grant (kind 3, teacher view, local_adele #600) is no learner in
     * any view - not even with a snapshot of the path from an earlier time -
     * and is listed among the other people of the course.
     */
    public function test_an_active_teacher_grant_is_no_learner(): void {
        global $DB;
        $stays = $this->newcomer('Stays');
        $tutor = $this->getDataGenerator()->create_user(['lastname' => 'Tutor', 'firstname' => 'T']);
        $this->assertTrue(teacher_grants::add(
            (int) $this->activity->cmid,
            $this->lpid,
            (int) $this->host->id,
            (int) $tutor->id,
            mode::ENROL_TEACHER,
            mode::target_role(mode::ENROL_TEACHER)
        ), 'enrolled with the teacher role through the grant');
        $kinds = $DB->get_fieldset_sql(
            "SELECT e.customint2 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :c AND ue.userid = :u AND ue.status = :active",
            ['c' => $this->host->id, 'u' => $tutor->id, 'active' => ENROL_USER_ACTIVE]
        );
        $this->assertSame([instance_manager::KIND_TEACHER], array_map('intval', $kinds), 'only the teacher grant');
        $this->getDataGenerator()->create_group_member(['groupid' => $this->everybody->id, 'userid' => $tutor->id]);
        $this->seen($tutor, 'c1', time());
        $this->viewer = $this->teacher();

        $views = $this->views();
        $this->assertTrue(learning_paths::is_roster_member((int) $this->host->id, (int) $tutor->id), 'enrolled in the course');
        $this->assert_place($views, $tutor, false, 'nonlearner', 'with the teacher grant');
        $this->assert_place($views, $stays, true, null, 'next to the tutor');
        $this->assert_counts($views, 1, 1, 1, 'with the teacher grant');

        // A snapshot of the path from an earlier time changes nothing: the role decides.
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $tutor->id, 'course_id' => $this->host->id, 'learning_path_id' => $this->lpid,
            'status' => 'active', 'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => $this->nodes(), 'edges' => []],
                'user_path_relation' => ['n1' => ['completionnode' => ['valid' => false]]]]),
        ]);
        $views = $this->views();
        $this->assert_place($views, $tutor, false, 'nonlearner', 'with the grant and a snapshot');
        $this->assert_counts($views, 1, 1, 1, 'with the grant and a snapshot');
    }
}
