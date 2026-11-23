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

use local_adele\external\get_group_overview;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\get_non_learners;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * Group rankings and averages (#12), non-learners (#13), through the web
 * services with the activity's group mode (mod_adele #16).
 *
 * Group A: Anna (n1, n2, n3 = 100 %), Bert (n1 = 33.33 %), Carl (none = 0 %).
 * Group B: Dora (n1, n2 = 66.67 %), Emil - in A and B - (n1 = 33.33 %).
 * Fred: in no group (n1, n2, n3).
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\group_progress
 * @covers     \local_adele\cohort
 * @covers     \local_adele\external\get_group_overview
 * @covers     \local_adele\external\get_non_learners
 * @covers     \local_adele\external\get_lp_user_path_relations
 * @covers     \mod_adele\local\context_contract
 */
final class group_progress_test extends groups_live_testcase {
    /** @var \stdClass[] People by first name. */
    private $p = [];
    /** @var \stdClass Group A. */
    private $ga;
    /** @var \stdClass Group B. */
    private $gb;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->make_world();
        $this->ga = $this->group('A');
        $this->gb = $this->group('B');
    }

    /**
     * The learners of the scenario (after the activity exists).
     *
     * @return void
     */
    private function people(): void {
        $this->p['anna'] = $this->learner('Anna', ['n1', 'n2', 'n3'], [$this->ga]);
        $this->p['bert'] = $this->learner('Bert', ['n1'], [$this->ga]);
        $this->p['carl'] = $this->learner('Carl', [], [$this->ga]);
        $this->p['dora'] = $this->learner('Dora', ['n1', 'n2'], [$this->gb]);
        $this->p['emil'] = $this->learner('Emil', ['n1'], [$this->ga, $this->gb]);
        $this->p['fred'] = $this->learner('Fred', ['n1', 'n2', 'n3']);
    }

    /**
     * The overview as the current user.
     *
     * @param \stdClass $activity
     * @param int $groupid
     * @return array
     */
    private function overview(\stdClass $activity, int $groupid = -1): array {
        return \core_external\external_api::clean_returnvalue(
            get_group_overview::execute_returns(),
            get_group_overview::execute($this->ctx($activity), $groupid)
        );
    }

    /**
     * The roster ids as the current user.
     *
     * @param \stdClass $activity
     * @param int $groupid
     * @return int[]
     */
    private function roster(\stdClass $activity, int $groupid = -1): array {
        global $USER;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $this->ctx($activity), $groupid);
        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);
        return $ids;
    }

    /**
     * Ids of people by first name.
     *
     * @param string ...$names
     * @return int[]
     */
    private function ids(string ...$names): array {
        $ids = array_map(fn($n) => (int) $this->p[$n]->id, $names);
        sort($ids);
        return $ids;
    }

    /**
     * No groups, "results of all other participants" (decision 2026-11-22):
     * through the activity a learner gets the ranking of the activity's
     * learners - the other rows without id or username; through the course
     * context the own row only. No rankings or averages of groups.
     */
    public function test_no_groups_all_results_lists_the_activitys_learners(): void {
        global $USER;
        $activity = $this->embed(NOGROUPS, ['userlist' => 1]);
        $this->people();
        $this->setUser($this->p['bert']);
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $this->ctx($activity));
        $this->assertSame(
            ['Anna', 'Bert', 'Carl', 'Dora', 'Emil', 'Fred'],
            $this->sorted(array_column($rows, 'lastname')),
            'the learners of the activity, staff not among them'
        );
        foreach ($rows as $index => $row) {
            if ($row['lastname'] === 'Bert') {
                $this->assertSame((int) $this->p['bert']->id, (int) $row['id'], 'the own row keeps its id');
                continue;
            }
            $this->assertLessThan(0, (int) $row['id'], "{$row['lastname']}: no user id");
            $this->assertSame('', $row['username'], "{$row['lastname']}: no username");
        }
        $this->assertSame(1, (int) $rows[0]['rank'], 'ranked among the learners');
        $this->assertCount(6, array_unique(array_column($rows, 'id')), 'every row keeps a key of its own');
        $overview = $this->overview($activity);
        $this->assertSame(NOGROUPS, $overview['groupmode']);
        $this->assertFalse($overview['closed']);
        $this->assertSame([], $overview['rankings']);
        $this->assertSame([], $overview['averages']);
        $this->assertSame([], $overview['groups']);
        // Through the course context the own row only.
        $coursecontextid = (int) \context_course::instance($this->host->id)->id;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $coursecontextid);
        $this->assertSame([(int) $this->p['bert']->id], array_map('intval', array_column($rows, 'id')));
    }

    /**
     * No groups, "only own results" (#569): the own row, the overview says closed.
     */
    public function test_no_groups_only_own_results(): void {
        $activity = $this->embed(NOGROUPS, ['userlist' => 2]);
        $this->people();
        $this->setUser($this->p['bert']);
        $this->assertSame($this->ids('bert'), $this->roster($activity));
        $this->assertTrue($this->overview($activity)['closed']);
    }

    /**
     * Sorted copy.
     *
     * @param array $values
     * @return array
     */
    private function sorted(array $values): array {
        sort($values);
        return array_values($values);
    }

    /**
     * No groups: the teacher keeps the learners of the course.
     */
    public function test_no_groups_teacher_gets_the_course(): void {
        $activity = $this->embed(NOGROUPS);
        $this->people();
        $this->setUser($this->teacher());
        $this->assertSame($this->ids('anna', 'bert', 'carl', 'dora', 'emil', 'fred'), $this->roster($activity));
    }

    /**
     * Separate groups: per own group a descending ranking with names and progress, no ids (AK0-L3).
     */
    public function test_separate_groups_learner_ranking(): void {
        $activity = $this->embed(SEPARATEGROUPS);
        $this->people();
        $this->setUser($this->p['bert']);
        $overview = $this->overview($activity);
        $this->assertSame(SEPARATEGROUPS, $overview['groupmode']);
        $this->assertCount(1, $overview['rankings']);
        $ranking = $overview['rankings'][0];
        $this->assertSame((int) $this->ga->id, $ranking['groupid']);
        // Anna 100, then Bert and Emil 33.33 (equal: same rank, by name), Carl 0.
        $this->assertSame(['L Anna', 'L Bert', 'L Emil', 'L Carl'], array_column($ranking['members'], 'fullname'));
        $this->assertSame([1, 2, 2, 4], array_column($ranking['members'], 'rank'));
        $this->assertSame([false, true, false, false], array_column($ranking['members'], 'self'));
        $this->assertEqualsWithDelta([100.0, 33.33, 33.33, 0.0], array_column($ranking['members'], 'progress'), 0.01);
        $this->assertArrayNotHasKey('userid', $ranking['members'][0], 'no ids of other people');
        $this->assertSame([], $overview['averages'], 'separate groups: no averages');
        // Dora (group B) appears nowhere; neither does Fred (no group).
        $this->assertStringNotContainsString('Dora', json_encode($overview));
        $this->assertStringNotContainsString('Fred', json_encode($overview));
    }

    /**
     * A learner in two groups gets a ranking for each (AK0-L5); a learner in no group gets none.
     */
    public function test_learner_in_two_groups_and_in_none(): void {
        $activity = $this->embed(SEPARATEGROUPS);
        $this->people();
        $this->setUser($this->p['emil']);
        $overview = $this->overview($activity);
        $this->assertSame([(int) $this->ga->id, (int) $this->gb->id], array_column($overview['rankings'], 'groupid'));
        $this->assertSame(['L Dora', 'L Emil'], array_column($overview['rankings'][1]['members'], 'fullname'));

        $this->setUser($this->p['fred']);
        $this->assertSame([], $this->overview($activity)['rankings']);
    }

    /**
     * Visible groups: own average above the own list, other groups only as averages (AK0-L4, AK0-L5).
     */
    public function test_visible_groups_learner_averages(): void {
        $activity = $this->embed(VISIBLEGROUPS);
        $this->people();
        $empty = $this->group('C');
        $this->setUser($this->p['dora']);
        $overview = $this->overview($activity);
        $this->assertSame([(int) $this->gb->id], array_column($overview['rankings'], 'groupid'));
        $averages = $overview['averages'];
        $this->assertSame(
            [(int) $this->gb->id, (int) $this->ga->id, (int) $empty->id],
            array_column($averages, 'groupid'),
            'own group first, then the others by name'
        );
        $this->assertTrue($averages[0]['own']);
        // B: Dora 66.67, Emil 33.33 -> 50.0. A: 100, 33.33, 0, 33.33 -> 41.7.
        $this->assertEqualsWithDelta(50.0, $averages[0]['value'], 0.05);
        $this->assertEqualsWithDelta(41.7, $averages[1]['value'], 0.05);
        $this->assertSame([2, 4], [$averages[0]['members'], $averages[1]['members']]);
        // An empty group: no data, not 0 % and not NaN.
        $this->assertNull($averages[2]['value']);
        $this->assertSame(0, $averages[2]['members']);
        // Nothing of group A's people.
        foreach (['Anna', 'Bert', 'Carl'] as $name) {
            $this->assertStringNotContainsString($name, json_encode($overview));
        }
    }

    /**
     * A learner of a group without a path stays in the denominator with 0 %
     * and is named (#12: mean over all distinct learners, no silent removal).
     * A learner whose snapshot completes nothing counts with 0 % as well; a
     * group whose learners all lack a path has 0 %, a group without learners
     * no value.
     */
    public function test_missing_progress_stays_in_the_denominator(): void {
        global $DB;
        $activity = $this->embed(VISIBLEGROUPS);
        $this->people();
        $DB->delete_records('local_adele_path_user', ['user_id' => $this->p['carl']->id]);
        $onlymissing = $this->group('D');
        $nopath = $this->getDataGenerator()->create_and_enrol($this->host, 'student');
        $this->getDataGenerator()->create_group_member(['groupid' => $onlymissing->id, 'userid' => $nopath->id]);
        // Enrolling subscribes the person to the path: take that back.
        $DB->delete_records('local_adele_path_user', ['user_id' => $nopath->id]);
        // Staff in a group is no learner of it.
        $this->teacher('teacher', [$onlymissing]);
        $this->setUser($this->p['bert']);
        $averages = $this->overview($activity)['averages'];
        $own = $averages[0];
        $this->assertSame((int) $this->ga->id, $own['groupid']);
        $this->assertSame(3, $own['measured']);
        $this->assertSame(4, $own['members']);
        // Anna 100, Bert 33.33, Emil 33.33, Carl (no path) 0 - over four.
        $this->assertEqualsWithDelta((100 + 33.33 + 33.33 + 0) / 4, $own['value'], 0.05);
        $d = array_values(array_filter($averages, fn($row) => $row['groupid'] === (int) $onlymissing->id))[0];
        $this->assertSame([0, 1], [$d['measured'], $d['members']]);
        $this->assertEqualsWithDelta(0.0, $d['value'], 0.001);
    }

    /**
     * "Participant results" applies without groups only (decision
     * 2026-11-22): with groups the stored value changes nothing - learners
     * get the rankings (and with visible groups the averages) of the #12
     * matrix, and the participant list holds their own row only, whatever
     * the value. Teachers are never closed out.
     */
    public function test_participant_results_apply_without_groups_only(): void {
        $this->people();
        $this->setUser($this->p['bert']);
        foreach ([1, 2] as $userlist) {
            $visible = $this->embed(VISIBLEGROUPS, ['userlist' => $userlist]);
            $overview = $this->overview($visible);
            $this->assertFalse($overview['closed'], "visible groups, userlist {$userlist}");
            $this->assertSame([(int) $this->ga->id], array_column($overview['rankings'], 'groupid'));
            $this->assertNotEmpty($overview['averages'], "visible groups, userlist {$userlist}: averages");
            $this->assertSame($this->ids('bert'), $this->roster($visible), "visible groups, userlist {$userlist}: own row");
            $separate = $this->embed(SEPARATEGROUPS, ['userlist' => $userlist]);
            $this->assertSame([(int) $this->ga->id], array_column($this->overview($separate)['rankings'], 'groupid'));
            $this->assertSame($this->ids('bert'), $this->roster($separate), "separate groups, userlist {$userlist}: own row");
        }
        // Without groups the setting decides.
        $this->assertTrue($this->overview($this->embed(NOGROUPS, ['userlist' => 2]))['closed']);
        $this->assertFalse($this->overview($this->embed(NOGROUPS, ['userlist' => 1]))['closed']);
        // Teachers are not closed out.
        $this->setUser($this->teacher());
        $this->assertFalse($this->overview($this->embed(NOGROUPS, ['userlist' => 2]))['closed']);
    }

    /**
     * Teachers: individual learners of the group chosen; foreign groups refused (AK0-L6, AK0-M2).
     */
    public function test_teacher_group_choice(): void {
        $activity = $this->embed(SEPARATEGROUPS);
        $this->people();
        // An editing teacher has accessallgroups: all participants, or any group.
        $this->setUser($this->teacher());
        $this->assertSame($this->ids('anna', 'bert', 'carl', 'dora', 'emil', 'fred'), $this->roster($activity, 0));
        $this->assertSame($this->ids('dora', 'emil'), $this->roster($activity, (int) $this->gb->id));
        $overview = $this->overview($activity);
        $this->assertSame(0, $overview['groupid']);
        $this->assertSame(['A', 'B'], array_column($overview['groups'], 'name'));

        // A non-editing teacher in group A only: group A by default, B refused, "all" refused.
        $this->setUser($this->teacher('teacher', [$this->ga]));
        $this->assertSame((int) $this->ga->id, $this->overview($activity)['groupid']);
        $this->assertSame($this->ids('anna', 'bert', 'carl', 'emil'), $this->roster($activity));
        foreach ([(int) $this->gb->id, 0, 999999] as $foreign) {
            try {
                $this->roster($activity, $foreign);
                $this->fail("group {$foreign} must be refused");
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('group', $e->getMessage());
            }
        }
    }

    /**
     * Visible groups: the teacher gets the averages of the groups too.
     */
    public function test_visible_groups_teacher_averages(): void {
        $activity = $this->embed(VISIBLEGROUPS);
        $this->people();
        $this->setUser($this->teacher('teacher', [$this->ga]));
        $overview = $this->overview($activity);
        $this->assertSame(['A', 'B'], array_column($overview['averages'], 'groupname'));
        $this->assertSame([], $overview['rankings']);
    }

    /**
     * A grouping limits the groups; a group outside it is refused (AK0-M5).
     */
    public function test_grouping(): void {
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $this->host->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $this->gb->id]);
        $activity = $this->embed(SEPARATEGROUPS, ['groupingid' => $grouping->id]);
        $this->people();
        $this->setUser($this->teacher());
        $this->assertSame(['B'], array_column($this->overview($activity)['groups'], 'name'));
        $this->assertSame($this->ids('dora', 'emil'), $this->roster($activity, 0), 'all participants of the grouping');
        $this->expectException(\invalid_parameter_exception::class);
        $this->roster($activity, (int) $this->ga->id);
    }

    /**
     * The overview of an activity in another course, or of nothing, is refused.
     */
    public function test_foreign_context_is_refused(): void {
        $activity = $this->embed(SEPARATEGROUPS);
        $this->people();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);
        $this->expectException(\require_login_exception::class);
        $this->overview($activity);
    }

    /**
     * Rankings and averages cost a fixed number of queries, whatever the size of the groups (DoD0-L5).
     */
    public function test_query_budget(): void {
        global $DB;
        $activity = $this->embed(VISIBLEGROUPS);
        $this->people();
        $count = function () use ($activity, $DB) {
            $this->setUser($this->p['bert']);
            $before = $DB->perf_get_reads();
            $this->overview($activity);
            return $DB->perf_get_reads() - $before;
        };
        $small = $count();
        for ($i = 0; $i < 30; $i++) {
            $this->learner("Extra{$i}", ['n1'], [$i % 2 ? $this->ga : $this->gb]);
        }
        $large = $count();
        $this->assertLessThanOrEqual($small + 2, $large, "no query per learner ({$small} -> {$large})");
    }

    /**
     * Non-learners with "all participants" in an activity with a grouping:
     * only the people of the grouping's groups (#13 AK1.6, re-audit
     * 2026-11-21) - the same people the participant list covers.
     */
    public function test_non_learners_all_participants_respect_the_grouping(): void {
        global $DB;
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $this->host->id]);
        $this->getDataGenerator()->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $this->ga->id]);
        $activity = $this->embed(SEPARATEGROUPS, ['groupingid' => $grouping->id]);
        $this->people();
        $inside = $this->learner('Inside', [], [$this->ga]);
        $outside = $this->learner('Outside', [], [$this->gb]);
        $nogroup = $this->learner('Nogroup', []);
        $DB->delete_records_list('local_adele_path_user', 'user_id', [$inside->id, $outside->id, $nogroup->id]);
        $assistantin = $this->teacher('teacher', [$this->ga]);
        $assistantout = $this->teacher('teacher', [$this->gb]);

        $this->setUser($this->teacher());
        $rows = \core_external\external_api::clean_returnvalue(
            get_non_learners::execute_returns(),
            get_non_learners::execute($this->ctx($activity), 0)
        );
        $ids = array_map('intval', array_column($rows, 'userid'));
        $this->assertContains((int) $inside->id, $ids);
        $this->assertContains((int) $assistantin->id, $ids);
        foreach ([$outside, $nogroup, $assistantout] as $person) {
            $this->assertNotContains((int) $person->id, $ids, 'outside the grouping: not listed');
        }
        // The participant list of the same choice covers the same people.
        $roster = $this->roster($activity, 0);
        $this->assertSame($this->ids('anna', 'bert', 'carl', 'emil'), $roster);
    }

    /**
     * Non-learners by role and enrolment (#13 AK1.5, DoD1.1; re-audit
     * 2026-11-22): a non-editing teacher and an editing teacher without a
     * path are "nonlearner"; a learner without a path is "nopath", also one
     * who teaches as well (a participant role decides); people whose
     * enrolment is suspended or has ended are not listed; learners with a
     * path are not listed - and none of this changes the participant list.
     */
    public function test_non_learners_roles_and_enrolment_status(): void {
        global $DB;
        $activity = $this->embed(NOGROUPS);
        $this->people();
        $tutor = $this->teacher('teacher');
        $editor = $this->teacher('editingteacher');
        $dual = $this->learner('Dual');
        role_assign(
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
            $dual->id,
            \context_course::instance($this->host->id)->id
        );
        $suspended = $this->learner('Suspended');
        $ended = $this->learner('Ended');
        $nopath = $this->learner('Nopath');
        $DB->delete_records_list(
            'local_adele_path_user',
            'user_id',
            [$tutor->id, $editor->id, $dual->id, $suspended->id, $ended->id, $nopath->id]
        );
        $manual = $DB->get_record('enrol', ['courseid' => $this->host->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $DB->set_field('user_enrolments', 'status', ENROL_USER_SUSPENDED, ['enrolid' => $manual->id, 'userid' => $suspended->id]);
        $DB->set_field('user_enrolments', 'timeend', time() - DAYSECS, ['enrolid' => $manual->id, 'userid' => $ended->id]);
        $this->setUser($this->teacher());
        $before = $this->roster($activity, 0);
        $rows = \core_external\external_api::clean_returnvalue(
            get_non_learners::execute_returns(),
            get_non_learners::execute($this->ctx($activity), 0)
        );
        $reasons = array_column($rows, 'reason', 'userid');
        $this->assertSame('nonlearner', $reasons[$tutor->id] ?? null, 'non-editing teacher');
        $this->assertSame('nonlearner', $reasons[$editor->id] ?? null, 'editing teacher without a path');
        $this->assertSame('nopath', $reasons[$nopath->id] ?? null, 'learner without a path');
        $this->assertSame('nopath', $reasons[$dual->id] ?? null, 'learner who also teaches, without a path');
        $this->assertArrayNotHasKey($suspended->id, $reasons, 'suspended enrolment');
        $this->assertArrayNotHasKey($ended->id, $reasons, 'ended enrolment');
        foreach (['anna', 'bert', 'carl', 'dora', 'emil', 'fred'] as $learner) {
            $this->assertArrayNotHasKey($this->p[$learner]->id, $reasons, 'a learner with a path');
        }
        // The roles are named; nothing beyond names, roles and the reason.
        $this->assertStringContainsString(',', $rows[array_search($dual->id, array_column($rows, 'userid'))]['roles']);
        foreach ($rows as $row) {
            $this->assertSame(['userid', 'fullname', 'reason', 'roles'], array_keys($row));
        }
        // The participant list is the same before and after.
        $this->assertSame($before, $this->roster($activity, 0));
    }

    /**
     * Non-learners (#13): teachers only, separate from the learners, nothing created.
     */
    public function test_non_learners(): void {
        global $DB;
        $activity = $this->embed(SEPARATEGROUPS);
        $this->people();
        $assistant = $this->teacher('teacher', [$this->ga]);
        $nopath = $this->learner('Nopa', [], [$this->ga]);
        $DB->delete_records('local_adele_path_user', ['user_id' => $nopath->id]);
        $before = $DB->count_records('local_adele_path_user');

        $this->setUser($this->teacher());
        $rows = \core_external\external_api::clean_returnvalue(
            get_non_learners::execute_returns(),
            get_non_learners::execute($this->ctx($activity), (int) $this->ga->id)
        );
        $byid = array_column($rows, null, 'userid');
        $this->assertSame('nonlearner', $byid[$assistant->id]['reason']);
        $this->assertSame('nopath', $byid[$nopath->id]['reason']);
        foreach (['anna', 'bert', 'carl', 'emil'] as $learner) {
            $this->assertArrayNotHasKey($this->p[$learner]->id, $byid, 'learners are not listed here');
        }
        $this->assertArrayNotHasKey($this->p['dora']->id, $byid);
        $this->assertSame($before, $DB->count_records('local_adele_path_user'), 'no subscription created');
        $this->assertStringNotContainsString('@', json_encode($rows), 'no e-mail addresses');

        // Learners may not ask.
        $this->setUser($this->p['bert']);
        $this->expectException(\required_capability_exception::class);
        get_non_learners::execute($this->ctx($activity), -1);
    }
}
