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

use local_adele\external\get_lp_user_path_relation;
use local_adele\external\get_lp_user_path_relations;
use local_adele\external\save_lp_user_path_relation;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * A teacher's view of learners through every context (#12 AK0-L1/L6, #16
 * AK0-M2/M4, re-audit 2026-11-21 P0): the course context, another module, an
 * activity of another path and one of two host activities with different
 * groupings give no more than the path's activities give the teacher.
 *
 * Host course with two activities of the same path, separate groups:
 *   A1 grouping G1 = {group 1}, A2 grouping G2 = {group 2}.
 * Learners: Ada and Ben in group 1, Cleo and Dirk in group 2, Eva in none.
 * The non-editing teacher is a member of group 1 only.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\cohort
 * @covers     \local_adele\external\get_lp_user_path_relations
 * @covers     \local_adele\external\get_lp_user_path_relation
 * @covers     \local_adele\external\save_lp_user_path_relation
 * @runTestsInSeparateProcesses
 */
final class teacher_scope_test extends groups_live_testcase {
    /** @var \stdClass[] People by first name. */
    private $p = [];
    /** @var \stdClass Group 1. */
    private $g1;
    /** @var \stdClass Group 2. */
    private $g2;
    /** @var \stdClass Activity A1 (grouping G1). */
    private $a1;
    /** @var \stdClass Activity A2 (grouping G2). */
    private $a2;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->make_world();
        $gen = $this->getDataGenerator();
        $this->g1 = $this->group('One');
        $this->g2 = $this->group('Two');
        $gg1 = $gen->create_grouping(['courseid' => $this->host->id, 'name' => 'G1']);
        $gg2 = $gen->create_grouping(['courseid' => $this->host->id, 'name' => 'G2']);
        $gen->create_grouping_group(['groupingid' => $gg1->id, 'groupid' => $this->g1->id]);
        $gen->create_grouping_group(['groupingid' => $gg2->id, 'groupid' => $this->g2->id]);
        $this->a1 = $this->embed(SEPARATEGROUPS, ['groupingid' => $gg1->id]);
        $this->a2 = $this->embed(SEPARATEGROUPS, ['groupingid' => $gg2->id]);
        $this->p['ada'] = $this->learner('Ada', ['n1'], [$this->g1]);
        $this->p['ben'] = $this->learner('Ben', ['n1', 'n2'], [$this->g1]);
        $this->p['cleo'] = $this->learner('Cleo', ['n1'], [$this->g2]);
        $this->p['dirk'] = $this->learner('Dirk', [], [$this->g2]);
        $this->p['eva'] = $this->learner('Eva', ['n1', 'n2', 'n3']);
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
     * The roster ids as the current user through a context.
     *
     * @param int $contextid
     * @param int $groupid
     * @return int[]
     */
    private function roster(int $contextid, int $groupid = -1): array {
        global $USER;
        $rows = get_lp_user_path_relations::execute((int) $USER->id, $this->lpid, $contextid, $groupid);
        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);
        return $ids;
    }

    /**
     * The course context id of the host.
     *
     * @return int
     */
    private function coursectx(): int {
        return (int) \context_course::instance($this->host->id)->id;
    }

    /**
     * The teacher of group 1, without accessallgroups.
     *
     * @return \stdClass
     */
    private function teacher1(): \stdClass {
        return $this->teacher('teacher', [$this->g1]);
    }

    /**
     * Through the activity's module context: the activity's groups.
     */
    public function test_module_context_gives_the_activitys_groups(): void {
        $this->setUser($this->teacher1());
        $this->assertSame($this->ids('ada', 'ben'), $this->roster($this->ctx($this->a1)));
        // A2's grouping holds no group of the teacher: nobody.
        $this->assertSame([], $this->roster($this->ctx($this->a2)));
    }

    /**
     * Through the course context: not the whole course (it was before) - the
     * union of what the path's activities give, here group 1 only.
     */
    public function test_course_context_is_no_bypass(): void {
        $this->setUser($this->teacher1());
        $this->assertSame($this->ids('ada', 'ben'), $this->roster($this->coursectx()));
    }

    /**
     * Through the module context of another module of the course: the same.
     */
    public function test_other_module_context_is_no_bypass(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->host->id]);
        $this->setUser($this->teacher1());
        $this->assertSame($this->ids('ada', 'ben'), $this->roster((int) \context_module::instance($page->cmid)->id));
    }

    /**
     * Through an ADELE activity of another path without groups: the same -
     * that activity says nothing about this path.
     */
    public function test_activity_of_another_path_is_no_bypass(): void {
        global $DB;
        $otherlp = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Other', 'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'visibility' => 1, 'createdby' => 2,
        ]);
        $other = $this->getDataGenerator()->create_module('adele', [
            'course' => $this->host->id, 'learningpathid' => $otherlp, 'view' => 1,
            'userlist' => 1, 'participantslist' => '0', 'groupmode' => NOGROUPS,
        ]);
        $this->setUser($this->teacher1());
        $this->assertSame($this->ids('ada', 'ben'), $this->roster($this->ctx($other)));
    }

    /**
     * A group of the other activity's grouping, asked for through the first: refused.
     */
    public function test_foreign_group_is_refused(): void {
        $this->setUser($this->teacher1());
        $this->expectException(\invalid_parameter_exception::class);
        $this->roster($this->ctx($this->a1), (int) $this->g2->id);
    }

    /**
     * With accessallgroups: "all participants" of each activity is its
     * grouping; through the course context the union - but not Eva, whom no
     * activity shows.
     */
    public function test_accessallgroups_union_of_groupings(): void {
        $this->setUser($this->teacher('editingteacher'));
        $this->assertSame($this->ids('ada', 'ben'), $this->roster($this->ctx($this->a1), 0));
        $this->assertSame($this->ids('cleo', 'dirk'), $this->roster($this->ctx($this->a2), 0));
        $this->assertSame($this->ids('ada', 'ben', 'cleo', 'dirk'), $this->roster($this->coursectx()));
    }

    /**
     * One activity of the path without groups shows everybody: the course
     * context gives everybody, as that activity does.
     */
    public function test_activity_without_groups_opens_the_course(): void {
        $this->embed(NOGROUPS);
        $this->setUser($this->teacher1());
        $this->assertSame($this->ids('ada', 'ben', 'cleo', 'dirk', 'eva'), $this->roster($this->coursectx()));
    }

    /**
     * One learner's path: a learner of group 2 is refused through every
     * context, one of group 1 is not.
     */
    public function test_single_path_follows_the_scope(): void {
        $this->setUser($this->teacher1());
        // Through A2 Ada is out of reach as well: A2's grouping holds no group of the teacher.
        $cases = [$this->ctx($this->a1) => true, $this->ctx($this->a2) => false, $this->coursectx() => true];
        foreach ($cases as $contextid => $adavisible) {
            $context = \context::instance_by_id($contextid);
            $this->assertSame(
                $adavisible,
                cohort::teacher_may_see($context, (int) $this->host->id, $this->lpid, (int) $this->p['ada']->id)
            );
            $this->assertFalse(cohort::teacher_may_see($context, (int) $this->host->id, $this->lpid, (int) $this->p['cleo']->id));
            try {
                get_lp_user_path_relation::execute($this->lpid, (int) $this->p['cleo']->id, $contextid);
                $this->fail('A learner of a group the teacher may not see must be refused.');
            } catch (\required_capability_exception $e) {
                $this->assertInstanceOf(\required_capability_exception::class, $e);
            }
        }
    }

    /**
     * Changing a learner's path: the same scope (editing teacher limited to
     * group 1 by a course without accessallgroups).
     */
    public function test_save_follows_the_scope(): void {
        global $DB;
        $teacher = $this->teacher('editingteacher', [$this->g1]);
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $role, \context_course::instance($this->host->id));
        $this->setUser($teacher);
        $payload = fn($user) => json_encode([
            'route' => ['learningpathId' => $this->lpid, 'userId' => (int) $user->id],
            'tree' => ['nodes' => $this->nodes(), 'edges' => []],
        ]);
        try {
            save_lp_user_path_relation::execute($this->lpid, (int) $teacher->id, $payload($this->p['cleo']), $this->coursectx());
            $this->fail('Saving the path of a learner of another group must be refused.');
        } catch (\required_capability_exception $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }
        // The control: the same teacher may change a learner of group 1.
        $context = \context_course::instance($this->host->id);
        $this->assertTrue(cohort::teacher_may_see($context, (int) $this->host->id, $this->lpid, (int) $this->p['ada']->id));
        $this->assertSame($this->ids('ada', 'ben'), $this->roster($this->coursectx()));
    }
}
