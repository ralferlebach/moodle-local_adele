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
     * No groups: a learner gets the own row only - also with "all results" (AK0-L2, AK0-M4).
     */
    public function test_no_groups_learner_gets_own_data_only(): void {
        $activity = $this->embed(NOGROUPS, ['userlist' => 1]);
        $this->people();
        $this->setUser($this->p['bert']);
        $this->assertSame($this->ids('bert'), $this->roster($activity));
        $overview = $this->overview($activity);
        $this->assertSame(NOGROUPS, $overview['groupmode']);
        $this->assertSame([], $overview['rankings']);
        $this->assertSame([], $overview['averages']);
        $this->assertSame([], $overview['groups']);
        // Through the course context neither (no other host context bypasses it).
        global $USER;
        $coursecontextid = (int) \context_course::instance($this->host->id)->id;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $coursecontextid);
        $this->assertSame([(int) $this->p['bert']->id], array_map('intval', array_column($rows, 'id')));
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
     * A learner of a group without a path counts in the members, not in the average (no silent denominator).
     */
    public function test_missing_progress_is_named(): void {
        global $DB;
        $activity = $this->embed(VISIBLEGROUPS);
        $this->people();
        $DB->delete_records('local_adele_path_user', ['user_id' => $this->p['carl']->id]);
        $this->setUser($this->p['bert']);
        $own = $this->overview($activity)['averages'][0];
        $this->assertSame((int) $this->ga->id, $own['groupid']);
        $this->assertSame(3, $own['measured']);
        $this->assertSame(4, $own['members']);
        $this->assertEqualsWithDelta((100 + 33.33 + 33.33) / 3, $own['value'], 0.05);
    }

    /**
     * "Only own results" closes the rankings in a group mode too.
     */
    public function test_only_own_results_closes_rankings(): void {
        $activity = $this->embed(VISIBLEGROUPS, ['userlist' => 2]);
        $this->people();
        $this->setUser($this->p['bert']);
        $overview = $this->overview($activity);
        $this->assertSame([], $overview['rankings']);
        $this->assertSame([], $overview['averages']);
        $this->assertTrue($overview['closed']);
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
