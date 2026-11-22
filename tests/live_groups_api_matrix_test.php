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
use local_adele\external\get_node_people;
use local_adele\external\get_non_learners;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * The web services of groups, non-learners, live markers and node counters,
 * each against every manipulation the issues name (#12 DoD0-L2, #13 DoD1.2,
 * #14 DoD2-L2, #15 DoD3.2, #16 DoD0-M2, #17 DoD2-M3; re-audit 2026-11-21:
 * "not every combination proven").
 *
 * Endpoints: overview (learners too), non-learners, snapshot, node people.
 * Manipulations: a learner, a teacher without the right in the activity, a
 * teacher of another course, the course context instead of the activity,
 * another module, an unknown context, a foreign group, an unknown node, a
 * node of another path, a negative page, an unknown kind, live markers
 * switched off. Each case names the exception the server must throw.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\external\get_group_overview
 * @covers     \local_adele\external\get_non_learners
 * @covers     \local_adele\external\get_live_snapshot
 * @covers     \local_adele\external\get_node_people
 * @covers     \local_adele\cohort
 * @covers     \mod_adele\local\context_contract
 */
final class live_groups_api_matrix_test extends groups_live_testcase {
    /** @var \stdClass Activity with separate groups, live markers on. */
    private $activity;
    /** @var \stdClass The same, live markers off. */
    private $off;
    /** @var \stdClass Group A. */
    private $ga;
    /** @var \stdClass Group B. */
    private $gb;
    /** @var \stdClass A learner of group A. */
    private $learner;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->make_world();
        $this->ga = $this->group('A');
        $this->gb = $this->group('B');
        $this->activity = $this->embed(SEPARATEGROUPS, ['livemarkers' => 1]);
        $this->off = $this->embed(SEPARATEGROUPS, ['livemarkers' => 0]);
        $this->learner = $this->learner('Lea', ['n1'], [$this->ga]);
        $other = $this->learner('Ole', ['n1'], [$this->gb]);
        $this->seen($this->learner, 'c1', time());
        $this->seen($other, 'c1', time());
    }

    /**
     * Call an endpoint.
     *
     * @param string $endpoint overview, nonlearners, snapshot, people
     * @param int $contextid
     * @param int $groupid
     * @param string $nodeid
     * @param string $kind
     * @param int $page
     * @return array
     */
    private function call(
        string $endpoint,
        int $contextid,
        int $groupid = -1,
        string $nodeid = 'n1',
        string $kind = 'active',
        int $page = 0
    ): array {
        switch ($endpoint) {
            case 'overview':
                return external_api::clean_returnvalue(
                    get_group_overview::execute_returns(),
                    get_group_overview::execute($contextid, $groupid)
                );
            case 'nonlearners':
                return external_api::clean_returnvalue(
                    get_non_learners::execute_returns(),
                    get_non_learners::execute($contextid, $groupid)
                );
            case 'snapshot':
                return external_api::clean_returnvalue(
                    get_live_snapshot::execute_returns(),
                    get_live_snapshot::execute($contextid, $groupid)
                );
            default:
                return external_api::clean_returnvalue(
                    get_node_people::execute_returns(),
                    get_node_people::execute($contextid, $groupid, $nodeid, $kind, $page)
                );
        }
    }

    /**
     * The cases: who, through what, which group/node/page, expected exception.
     *
     * @return array
     */
    public static function manipulation_provider(): array {
        $cases = [];
        foreach (['overview', 'nonlearners', 'snapshot', 'people'] as $endpoint) {
            $teacherendpoint = $endpoint !== 'overview';
            if ($teacherendpoint) {
                $cases["{$endpoint}: a learner"] = [$endpoint, 'learner', 'activity', 'own', \required_capability_exception::class];
            }
            $cases["{$endpoint}: a teacher without the right in the activity"] =
                [$endpoint, 'norights', 'activity', 'own', \required_capability_exception::class];
            $cases["{$endpoint}: a teacher of another course"] =
                [$endpoint, 'stranger', 'activity', 'own', \require_login_exception::class];
            $cases["{$endpoint}: the course context"] =
                [$endpoint, 'teacher', 'course', 'own', \invalid_parameter_exception::class];
            $cases["{$endpoint}: another module"] =
                [$endpoint, 'teacher', 'page', 'own', \invalid_parameter_exception::class];
            $cases["{$endpoint}: an unknown context"] =
                [$endpoint, 'teacher', 'unknown', 'own', \dml_missing_record_exception::class];
            $cases["{$endpoint}: a foreign group"] =
                [$endpoint, 'teacher', 'activity', 'foreign', \invalid_parameter_exception::class];
        }
        $cases['people: an unknown node'] = ['people', 'teacher', 'activity', 'own', \invalid_parameter_exception::class, 'nX'];
        $cases['people: a node of another path'] =
            ['people', 'teacher', 'activity', 'own', \invalid_parameter_exception::class, 'dndnode_1'];
        $cases['people: a negative page'] =
            ['people', 'teacher', 'activity', 'own', \invalid_parameter_exception::class, 'n1', 'active', -1];
        $cases['people: an unknown kind'] =
            ['people', 'teacher', 'activity', 'own', \invalid_parameter_exception::class, 'n1', 'everybody'];
        $cases['people: active names with live markers off'] =
            ['people', 'teacher', 'off', 'own', \invalid_parameter_exception::class];
        return $cases;
    }

    /**
     * Every manipulation is refused with the exception named.
     *
     * @dataProvider manipulation_provider
     * @param string $endpoint
     * @param string $who learner, norights, stranger, teacher
     * @param string $where activity, off, course, page, unknown
     * @param string $group own, foreign
     * @param string $exception
     * @param string $nodeid
     * @param string $kind
     * @param int $page
     */
    public function test_manipulation_is_refused(
        string $endpoint,
        string $who,
        string $where,
        string $group,
        string $exception,
        string $nodeid = 'n1',
        string $kind = 'active',
        int $page = 0
    ): void {
        global $DB;
        $gen = $this->getDataGenerator();
        switch ($who) {
            case 'learner':
                $user = $this->learner;
                break;
            case 'norights':
                $user = $this->teacher('teacher', [$this->ga]);
                $role = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
                $context = \context_module::instance($this->activity->cmid);
                foreach (['local/adele:viewparticipants', 'local/adele:teacheredit', 'mod/adele:readinstance'] as $cap) {
                    assign_capability($cap, CAP_PROHIBIT, $role, $context);
                }
                break;
            case 'stranger':
                $user = $gen->create_user();
                $gen->enrol_user($user->id, $gen->create_course()->id, 'editingteacher');
                break;
            default:
                $user = $this->teacher('teacher', [$this->ga]);
        }
        switch ($where) {
            case 'off':
                $contextid = $this->ctx($this->off);
                break;
            case 'course':
                $contextid = (int) \context_course::instance($this->host->id)->id;
                break;
            case 'page':
                $page2 = $gen->create_module('page', ['course' => $this->host->id]);
                $contextid = (int) \context_module::instance($page2->cmid)->id;
                break;
            case 'unknown':
                $contextid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}') + 1000;
                break;
            default:
                $contextid = $this->ctx($this->activity);
        }
        $groupid = $group === 'foreign' ? (int) $this->gb->id : -1;
        $this->setUser($user);
        $this->expectException($exception);
        $this->call($endpoint, $contextid, $groupid, $nodeid, $kind, $page);
    }

    /**
     * The controls: the same teacher, the own group, a page past the end -
     * allowed, and nothing of group B in any answer.
     */
    public function test_allowed_calls_hold_nothing_foreign(): void {
        $teacher = $this->teacher('teacher', [$this->ga]);
        $this->setUser($teacher);
        $contextid = $this->ctx($this->activity);
        $answers = [
            $this->call('overview', $contextid),
            $this->call('nonlearners', $contextid),
            $this->call('snapshot', $contextid),
            $this->call('people', $contextid),
            $this->call('people', $contextid, -1, 'n1', 'completed'),
        ];
        foreach ($answers as $answer) {
            $this->assertStringNotContainsString('Ole', json_encode($answer), 'nothing of group B');
        }
        $this->assertSame(1, $answers[3]['total']);
        $past = $this->call('people', $contextid, -1, 'n1', 'active', 5);
        $this->assertSame([1, []], [$past['total'], $past['people']], 'a page past the end is empty, the total stays');
        // Switched off: the snapshot says so and holds no markers; completed names stay.
        $off = $this->call('snapshot', $this->ctx($this->off));
        $this->assertFalse($off['enabled']);
        foreach ($off['nodes'] as $node) {
            $this->assertSame([], $node['markers']);
            $this->assertNull($node['active']);
        }
        $this->assertSame(1, $this->call('people', $this->ctx($this->off), -1, 'n1', 'completed')['total']);
        // A learner gets the own group's overview, nothing of B.
        $this->setUser($this->learner);
        $this->assertStringNotContainsString('Ole', json_encode($this->call('overview', $contextid)));
    }
}
