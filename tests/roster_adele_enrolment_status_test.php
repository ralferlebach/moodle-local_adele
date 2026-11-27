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
use local_adele\external\get_group_overview;
use local_adele\external\get_live_snapshot;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\get_non_learners;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * Who an ADELE enrolment puts on a course's roster (re-audit 2026-11-26, P1;
 * #13 AK1.5, with #12, #14, #15).
 *
 * The roster (learning_paths::roster_members_sql) decides who appears among
 * the learners of a course - in the participant list, the group overview,
 * the live data and, by complement, the list of non-learners. An ADELE
 * enrolment counts there as Moodle counts an enrolment - instance enabled,
 * inside its time window - and, when suspended, only for what ADELE means by
 * that suspension: "enrolled, without access" for an embedding in the host
 * mode "hidden". A suspension in a course whose embeddings are all "visible"
 * means the person no longer has a reason to be there (enrol_adele suspends
 * the host enrolment of a departing learner and removes it after the grace
 * period), and it opens nothing.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\learning_paths::roster_members_sql
 */
final class roster_adele_enrolment_status_test extends groups_live_testcase {
    /** @var int A fixed "now". */
    private const NOW = 1893456000;

    /** @var array Instances by kind and status: "host", "hostoff", "teacher". */
    private $instances = [];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->make_world();
        $this->mock_clock_with_frozen(self::NOW);
        $this->instances = [
            'host' => $this->instance(2, ENROL_INSTANCE_ENABLED),
            'hostoff' => $this->instance(2, ENROL_INSTANCE_DISABLED),
            'teacher' => $this->instance(3, ENROL_INSTANCE_ENABLED),
        ];
    }

    /**
     * An enrol_adele instance of the host course, written directly (no events,
     * no reconciliation: the test is about what the roster makes of the rows).
     *
     * @param int $kind enrol_adele kind (2 host, 3 teacher).
     * @param int $status ENROL_INSTANCE_ENABLED or _DISABLED.
     * @return int
     */
    private function instance(int $kind, int $status): int {
        global $DB;
        return (int) $DB->insert_record('enrol', (object) [
            'enrol' => 'adele', 'courseid' => $this->host->id, 'status' => $status,
            'customint1' => $this->lpid, 'customint2' => $kind, 'sortorder' => 10 + $kind + $status,
            'roleid' => $DB->get_field('role', 'id', ['shortname' => 'student']),
            'timecreated' => self::NOW, 'timemodified' => self::NOW,
        ]);
    }

    /**
     * A learner of the path whose only way into the host course is one ADELE
     * enrolment, seen in node 1's course just now.
     *
     * @param string $lastname
     * @param string $instance Key of $this->instances.
     * @param int $status ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED.
     * @param int $timestart
     * @param int $timeend
     * @return \stdClass
     */
    private function adele_learner(
        string $lastname,
        string $instance,
        int $status,
        int $timestart = 0,
        int $timeend = 0
    ): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['lastname' => $lastname, 'firstname' => 'L']);
        $DB->insert_record('user_enrolments', (object) [
            'enrolid' => $this->instances[$instance], 'userid' => $user->id, 'status' => $status,
            'timestart' => $timestart, 'timeend' => $timeend, 'modifierid' => 2,
            'timecreated' => self::NOW, 'timemodified' => self::NOW,
        ]);
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'student']),
            $user->id,
            \context_course::instance($this->host->id)->id,
            'enrol_adele',
            $this->instances[$instance]
        );
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id, 'course_id' => $this->host->id, 'learning_path_id' => $this->lpid,
            'status' => 'active', 'createdby' => $user->id,
            'json' => json_encode(['tree' => ['nodes' => $this->nodes(), 'edges' => []],
                'user_path_relation' => ['n1' => ['completionnode' => ['valid' => true]]]]),
        ]);
        $this->seen($user, 'c1', self::NOW);
        return $user;
    }

    /**
     * Everything the roster decides, for one activity, as a teacher sees it.
     *
     * @param \stdClass $activity
     * @return array learners (ids), live (active ids on n1), groups (member count), nonlearners (ids)
     */
    private function views(\stdClass $activity): array {
        $ctx = $this->ctx($activity);
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
        $ids = fn(array $rows, string $key) => array_values(array_map('intval', array_column($rows, $key)));
        $learners = $ids($relations, 'id');
        sort($learners);
        $live = array_map('intval', array_column(array_filter(
            $n1['markers'],
            fn($m) => $m['state'] === 'active'
        ), 'userid'));
        sort($live);
        return [
            'learners' => $learners,
            'live' => $live,
            'active' => (int) $n1['active'],
            'overview' => json_encode($overview),
            'nonlearners' => $ids($nonlearners, 'userid'),
        ];
    }

    /**
     * The matrix of the re-audit: active, suspended (hidden and visible host
     * mode), ended, not yet started, disabled instance, a second active
     * source, the teacher kind.
     */
    public function test_which_adele_enrolments_put_a_learner_on_the_roster(): void {
        global $DB;
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1, 'hostenrolmentmode' => 'visible']);
        $active = $this->adele_learner('Active', 'host', ENROL_USER_ACTIVE);
        $suspended = $this->adele_learner('Suspended', 'host', ENROL_USER_SUSPENDED);
        // Core judges the time window by the real time (get_enrolled_sql), so do these cases.
        $ended = $this->adele_learner('Ended', 'host', ENROL_USER_ACTIVE, 0, time() - DAYSECS);
        $future = $this->adele_learner('Future', 'host', ENROL_USER_ACTIVE, time() + DAYSECS);
        $disabled = $this->adele_learner('Disabled', 'hostoff', ENROL_USER_ACTIVE);
        $teacherkind = $this->adele_learner('Teacherkind', 'teacher', ENROL_USER_SUSPENDED);
        $second = $this->adele_learner('Second', 'host', ENROL_USER_SUSPENDED);
        $this->getDataGenerator()->enrol_user($second->id, $this->host->id, 'student', 'manual');
        $DB->set_field('local_adele_path_user', 'status', 'active', ['user_id' => $second->id]);

        $this->setUser($this->teacher());
        $views = $this->views($activity);
        $expected = [(int) $active->id, (int) $second->id];
        sort($expected);
        $this->assertSame($expected, $views['learners'], 'participant list');
        $this->assertSame($expected, $views['live'], 'live markers');
        $this->assertSame(2, $views['active'], 'live counter');
        foreach ([$suspended, $ended, $future, $disabled, $teacherkind] as $out) {
            $this->assertNotContains((int) $out->id, $views['learners']);
            $this->assertNotContains((int) $out->id, $views['live']);
            $this->assertNotContains((int) $out->id, $views['nonlearners'], 'not listed as another person either');
            $this->assertFalse(
                learning_paths::is_roster_member((int) $this->host->id, (int) $out->id),
                $out->lastname . ' is not on the roster'
            );
        }
        $this->assertStringNotContainsString('Suspended', $views['overview']);
        $this->assertTrue(learning_paths::is_roster_member((int) $this->host->id, (int) $active->id));
        $this->assertTrue(
            learning_paths::is_roster_member((int) $this->host->id, (int) $second->id),
            'a second, active source carries the person'
        );
    }

    /**
     * Host mode "hidden": ADELE keeps its learners suspended on purpose
     * ("enrolled, without access") - they are learners of the course and on
     * the list (#588). Outside the time window or with the method disabled,
     * not even they.
     */
    public function test_hidden_host_mode_keeps_its_suspended_learners(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1, 'hostenrolmentmode' => 'hidden']);
        $hidden = $this->adele_learner('Hidden', 'host', ENROL_USER_SUSPENDED);
        $ended = $this->adele_learner('Ended', 'host', ENROL_USER_SUSPENDED, 0, time() - DAYSECS);
        $disabled = $this->adele_learner('Disabled', 'hostoff', ENROL_USER_SUSPENDED);

        $this->setUser($this->teacher());
        $views = $this->views($activity);
        $this->assertSame([(int) $hidden->id], $views['learners']);
        $this->assertSame([(int) $hidden->id], $views['live']);
        $this->assertFalse(learning_paths::is_roster_member((int) $this->host->id, (int) $ended->id));
        $this->assertFalse(learning_paths::is_roster_member((int) $this->host->id, (int) $disabled->id));
    }

    /**
     * The hidden mode of one path does not carry the suspended enrolments of
     * another path's embedding in the same course.
     */
    public function test_hidden_mode_counts_for_its_own_path_only(): void {
        global $DB;
        $this->embed(NOGROUPS, ['hostenrolmentmode' => 'visible']);
        $otherpath = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Other', 'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'visibility' => 1, 'createdby' => 2,
        ]);
        $this->getDataGenerator()->create_module('adele', ['course' => $this->host->id, 'learningpathid' => $otherpath,
            'view' => 1, 'userlist' => 1, 'participantslist' => '0', 'hostenrolmentmode' => 'hidden']);
        $suspended = $this->adele_learner('Suspended', 'host', ENROL_USER_SUSPENDED);
        $this->assertFalse(learning_paths::is_roster_member((int) $this->host->id, (int) $suspended->id));
    }
}
