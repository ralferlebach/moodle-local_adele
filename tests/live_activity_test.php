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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/groups_live_testcase.php');

/**
 * Live activity markers (#14) and node counters (#15) with a frozen clock.
 *
 * Path: n1 (c1), n2 (stacked: c2, c3), n3 (c1 again). Window 5 minutes.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\live_activity
 * @covers     \local_adele\external\get_live_snapshot
 * @covers     \local_adele\external\get_node_people
 * @covers     \local_adele\task\purge_live_activity
 * @covers     \local_adele\privacy\provider
 */
final class live_activity_test extends groups_live_testcase {
    /** @var int The frozen now. */
    private const NOW = 1893456000;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->make_world();
        $this->mock_clock_with_frozen(self::NOW);
    }

    /**
     * The snapshot as the current user, by node id.
     *
     * @param \stdClass $activity
     * @param int $groupid
     * @return array nodeid => node
     */
    private function snap(\stdClass $activity, int $groupid = -1): array {
        $result = external_api::clean_returnvalue(
            get_live_snapshot::execute_returns(),
            get_live_snapshot::execute($this->ctx($activity), $groupid)
        );
        $this->assertSame(self::NOW, $result['servertime']);
        return array_column($result['nodes'], null, 'nodeid');
    }

    /**
     * The marker states of a node: userid => state.
     *
     * @param array $node
     * @return array
     */
    private function markers(array $node): array {
        $out = [];
        foreach ($node['markers'] as $marker) {
            $out[$marker['userid']] = $marker['state'];
        }
        ksort($out);
        return $out;
    }

    /**
     * 0, 5 and 6 minutes: active up to and including the window, then inactive (DoD2-L1).
     */
    public function test_window_boundaries(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1, 'livewindow' => 5]);
        $now = $this->learner('Now');
        $five = $this->learner('Five');
        $six = $this->learner('Six');
        $this->seen($now, 'c2', self::NOW);
        $this->seen($five, 'c2', self::NOW - 5 * MINSECS);
        $this->seen($six, 'c2', self::NOW - 6 * MINSECS);
        $this->setUser($this->teacher());
        $nodes = $this->snap($activity);
        $this->assertSame(2, $nodes['n2']['active']);
        $this->assertSame([$now->id => 'active', $five->id => 'active', $six->id => 'inactive'], $this->markers($nodes['n2']));
    }

    /**
     * Active on every node holding the course; inactive only on the last; never seen: nothing.
     */
    public function test_active_everywhere_inactive_once_never_nothing(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        $reused = $this->learner('Reused');
        $last = $this->learner('Last');
        $never = $this->learner('Never');
        // Active in c1, which is in n1 and n3.
        $this->seen($reused, 'c1', self::NOW - 60);
        // Inactive: c2 an hour ago, c1 two hours ago -> last node n2.
        $this->seen($last, 'c2', self::NOW - HOURSECS);
        $this->seen($last, 'c1', self::NOW - 2 * HOURSECS);
        $this->setUser($this->teacher());
        $nodes = $this->snap($activity);
        $this->assertSame([$reused->id => 'active'], $this->markers($nodes['n1']));
        $this->assertSame([$reused->id => 'active'], $this->markers($nodes['n3']));
        $this->assertSame([$last->id => 'inactive'], $this->markers($nodes['n2']));
        $this->assertSame([1, 0, 1], [$nodes['n1']['active'], $nodes['n2']['active'], $nodes['n3']['active']]);
        foreach ($nodes as $node) {
            $this->assertArrayNotHasKey($never->id, $this->markers($node), 'never seen: no marker');
        }
    }

    /**
     * Stacked node: two active courses count once; same time in two courses: the lower course id;
     * a course in two nodes: the first node.
     */
    public function test_stacked_ties_and_reused_course(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        $both = $this->learner('Both');
        $tie = $this->learner('Tie');
        $reused = $this->learner('Reused');
        $clear = $this->learner('Clear');
        $this->seen($both, 'c2', self::NOW - 10);
        $this->seen($both, 'c3', self::NOW - 20);
        $time = self::NOW - DAYSECS;
        // Seen in c3 (node n2) and c1 (nodes n1 and n3) at the same second.
        $this->seen($tie, 'c3', $time);
        $this->seen($tie, 'c1', $time);
        // Last seen in c1 alone - a course in two nodes, n1 and n3.
        $this->seen($reused, 'c3', $time - 60);
        $this->seen($reused, 'c1', $time);
        // Last seen in c2 alone: one node, n2.
        $this->seen($clear, 'c1', $time - 60);
        $this->seen($clear, 'c2', $time);
        $this->setUser($this->teacher());
        $nodes = $this->snap($activity);
        $this->assertSame(1, $nodes['n2']['active'], 'one person once per node');
        $this->assertCount(1, array_filter($nodes['n2']['markers'], fn($m) => (int) $m['userid'] === (int) $both->id));

        // A tie of the last activity: no pick - the marker on every node of
        // the courses seen last (n1, n2, n3), each saying "not unique, 3 nodes".
        foreach (['n1', 'n2', 'n3'] as $nodeid) {
            $marker = $this->marker($nodes[$nodeid], $tie);
            $this->assertSame('inactive', $marker['state'] ?? null, "tie on {$nodeid}");
            $this->assertSame(3, $marker['ambiguous']);
        }
        // A reused course: both of its nodes, "not unique, 2 nodes"; not n2.
        foreach (['n1', 'n3'] as $nodeid) {
            $this->assertSame(2, $this->marker($nodes[$nodeid], $reused)['ambiguous'] ?? null, "reused on {$nodeid}");
        }
        $this->assertNull($this->marker($nodes['n2'], $reused));
        // One node: the marker there only, not ambiguous.
        $this->assertSame(0, $this->marker($nodes['n2'], $clear)['ambiguous'] ?? null);
        $this->assertNull($this->marker($nodes['n1'], $clear));
        $this->assertNull($this->marker($nodes['n3'], $clear));
        // The same input, the same output (deterministic).
        $this->assertSame($nodes, $this->snap($activity));
    }

    /**
     * The marker of a person on a node, or null.
     *
     * @param array $node
     * @param \stdClass $user
     * @return array|null
     */
    private function marker(array $node, \stdClass $user): ?array {
        foreach ($node['markers'] as $marker) {
            if ((int) $marker['userid'] === (int) $user->id) {
                return $marker;
            }
        }
        return null;
    }

    /**
     * Completed counters count distinct learners; active and completed may overlap (AK3.1).
     */
    public function test_completed_counter_and_overlap(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        $done = $this->learner('Done', ['n1', 'n2']);
        $this->learner('Half', ['n1']);
        $this->seen($done, 'c1', self::NOW);
        $this->setUser($this->teacher());
        $nodes = $this->snap($activity);
        $this->assertSame([2, 1, 0], [$nodes['n1']['completed'], $nodes['n2']['completed'], $nodes['n3']['completed']]);
        $this->assertSame(1, $nodes['n1']['active'], 'Done is active on n1 and has completed it');
    }

    /**
     * Switched off: no activity computed or sent, the completed counter stays (AK2-L7, AK3.7).
     */
    public function test_switched_off(): void {
        $activity = $this->embed(NOGROUPS);
        $done = $this->learner('Done', ['n1']);
        $this->seen($done, 'c1', self::NOW);
        $this->setUser($this->teacher());
        $nodes = $this->snap($activity);
        $this->assertNull($nodes['n1']['active']);
        $this->assertSame([], $nodes['n1']['markers']);
        $this->assertSame(1, $nodes['n1']['completed']);
        $people = get_node_people::execute($this->ctx($activity), -1, 'n1', 'completed', 0);
        $this->assertSame(1, $people['total']);
        $this->expectException(\invalid_parameter_exception::class);
        get_node_people::execute($this->ctx($activity), -1, 'n1', 'active', 0);
    }

    /**
     * The group filter: markers and counters of the group chosen only; learners get nothing.
     */
    public function test_groups_and_roles(): void {
        $ga = $this->group('A');
        $gb = $this->group('B');
        $activity = $this->embed(SEPARATEGROUPS, ['livemarkers' => 1]);
        $a = $this->learner('Ina', ['n1'], [$ga]);
        $b = $this->learner('Bob', ['n1'], [$gb]);
        $this->seen($a, 'c1', self::NOW);
        $this->seen($b, 'c1', self::NOW);
        $this->setUser($this->teacher('teacher', [$ga]));
        $nodes = $this->snap($activity);
        $this->assertSame([$a->id => 'active'], $this->markers($nodes['n1']));
        $this->assertSame(1, $nodes['n1']['completed']);
        try {
            $this->snap($activity, (int) $gb->id);
            $this->fail('a foreign group is refused');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('group', $e->getMessage());
        }
        // A learner of the activity gets no live data at all.
        $this->setUser($a);
        $this->expectException(\required_capability_exception::class);
        $this->snap($activity);
    }

    /**
     * Counting by person (#15 AK3.1/AK3.2, DoD3.1; re-audit 2026-11-22): a
     * learner in two groups counts once for "all participants" and once in
     * each of the groups; a learner who also teaches counts as the learner
     * they are; a teacher without a path is not counted, even when seen.
     */
    public function test_multiple_groups_and_dual_roles_count_once(): void {
        global $DB;
        $ga = $this->group('A');
        $gb = $this->group('B');
        $activity = $this->embed(SEPARATEGROUPS, ['livemarkers' => 1]);
        $both = $this->learner('Both', ['n1'], [$ga, $gb]);
        $dual = $this->learner('Dual', ['n1'], [$ga]);
        $teacherrole = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        role_assign($teacherrole, $dual->id, \context_course::instance($this->host->id)->id);
        // The role change recomputes the snapshot from the real completions;
        // give it back node 1 completed, as learner() set it.
        $json = $DB->get_field('local_adele_path_user', 'json', ['user_id' => $both->id, 'learning_path_id' => $this->lpid]);
        $DB->set_field('local_adele_path_user', 'json', $json, ['user_id' => $dual->id, 'learning_path_id' => $this->lpid]);
        $tutor = $this->teacher('teacher', [$ga]);
        $DB->delete_records('local_adele_path_user', ['user_id' => $tutor->id]);
        foreach ([$both, $dual, $tutor] as $person) {
            $this->seen($person, 'c1', self::NOW);
        }
        $this->setUser($this->teacher());
        $all = $this->snap($activity, 0)['n1'];
        $this->assertSame(2, $all['active'], 'all participants: each person once, the tutor not');
        $this->assertSame(2, $all['completed']);
        $this->assertEqualsCanonicalizing([(int) $both->id, (int) $dual->id], array_keys($this->markers($all)));
        $ina = $this->snap($activity, (int) $ga->id)['n1'];
        $this->assertSame(2, $ina['active'], 'group A: both');
        $inb = $this->snap($activity, (int) $gb->id)['n1'];
        $this->assertSame(1, $inb['active'], 'group B: the learner in two groups');
        $this->assertSame([(int) $both->id => 'active'], $this->markers($inb));
    }

    /**
     * Name lists: a page at a time, only for nodes of the path.
     */
    public function test_people_pages_and_unknown_node(): void {
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        for ($i = 0; $i < 23; $i++) {
            $user = $this->learner(sprintf('P%02d', $i), ['n1']);
            $this->seen($user, 'c1', self::NOW);
        }
        $this->setUser($this->teacher());
        $first = get_node_people::execute($this->ctx($activity), -1, 'n1', 'active', 0);
        $second = get_node_people::execute($this->ctx($activity), -1, 'n1', 'active', 1);
        $this->assertSame(23, $first['total']);
        $this->assertCount(20, $first['people']);
        $this->assertCount(3, $second['people']);
        $this->assertSame('L P00', $first['people'][0]['fullname']);
        $nodes = $this->snap($activity);
        $this->assertCount(live_activity::MAXMARKERS, $nodes['n1']['markers']);
        $this->assertSame(23 - live_activity::MAXMARKERS, $nodes['n1']['moremarkers']);
        $this->expectException(\invalid_parameter_exception::class);
        get_node_people::execute($this->ctx($activity), -1, 'nX', 'completed', 0);
    }

    /**
     * The snapshot costs a fixed number of queries, whatever the size of the cohort (DoD2-L5, DoD3.5).
     */
    public function test_query_budget(): void {
        global $DB;
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        $teacher = $this->teacher();
        $count = function () use ($activity, $teacher, $DB) {
            $this->setUser($teacher);
            $before = $DB->perf_get_reads();
            $this->snap($activity);
            return $DB->perf_get_reads() - $before;
        };
        for ($i = 0; $i < 5; $i++) {
            $user = $this->learner("S{$i}", ['n1']);
            $this->seen($user, 'c1', self::NOW - $i);
        }
        $small = $count();
        for ($i = 0; $i < 40; $i++) {
            $user = $this->learner("B{$i}", ['n1']);
            $this->seen($user, 'c2', self::NOW - HOURSECS - $i);
        }
        $large = $count();
        $this->assertLessThanOrEqual($small + 2, $large, "no query per learner ({$small} -> {$large})");
        $this->assertLessThan(40, $large);
    }

    /**
     * A larger cohort (#14 DoD2-L5, #15 DoD3.5; re-audit 2026-11-22: 45
     * learners were no large cohort): 300 learners in four groups, half of
     * them active. The snapshot, the group overview and a page of names cost
     * the same number of queries as for five learners and stay within a
     * second each; every count is exact. The HTTP load with many concurrent
     * teacher views is the k6 plan (tests/load, CONTEXTID).
     */
    public function test_large_cohort(): void {
        global $DB;
        $activity = $this->embed(SEPARATEGROUPS, ['livemarkers' => 1]);
        $groups = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $groups[] = $this->group($name);
        }
        $teacher = $this->teacher();
        $measure = function () use ($activity, $teacher, $DB): array {
            $this->setUser($teacher);
            $out = [];
            foreach (
                [
                'snapshot' => fn() => $this->snap($activity, 0),
                'overview' => fn() => get_group_overview::execute($this->ctx($activity), 0),
                'people' => fn() => get_node_people::execute($this->ctx($activity), 0, 'n1', 'active', 0),
                ] as $name => $call
            ) {
                $reads = $DB->perf_get_reads();
                $start = microtime(true);
                $result = $call();
                $out[$name] = ['reads' => $DB->perf_get_reads() - $reads, 'seconds' => microtime(true) - $start,
                    'result' => $result];
            }
            return $out;
        };
        $add = function (int $from, int $to) use ($groups): void {
            for ($i = $from; $i < $to; $i++) {
                $user = $this->learner(sprintf('Q%03d', $i), $i % 3 ? ['n1'] : [], [$groups[$i % 4]]);
                // Every second learner inside the window, the others an hour ago.
                $this->seen($user, 'c1', $i % 2 ? self::NOW - 60 : self::NOW - HOURSECS);
            }
        };
        $add(0, 5);
        $small = $measure();
        $add(5, 300);
        $large = $measure();

        foreach (['snapshot', 'overview', 'people'] as $name) {
            $this->assertLessThanOrEqual(
                $small[$name]['reads'] + 2,
                $large[$name]['reads'],
                "{$name}: no query per learner ({$small[$name]['reads']} -> {$large[$name]['reads']})"
            );
            $this->assertLessThan(1.0, $large[$name]['seconds'], "{$name}: within a second for 300 learners");
        }
        $node = $large['snapshot']['result']['n1'];
        $this->assertSame(150, $node['active'], 'every second learner is active');
        $this->assertSame(200, $node['completed'], 'two of three completed node 1');
        $this->assertCount(live_activity::MAXMARKERS, $node['markers']);
        // Markers: the 150 active ones and the 150 inactive ones on the node last observed.
        $this->assertSame(300 - live_activity::MAXMARKERS, $node['moremarkers']);
        $this->assertSame(150, $large['people']['result']['total']);
        $this->assertCount(20, $large['people']['result']['people'], 'one page of names');
    }

    /**
     * The observer: course and activity views of path learners, nothing else; the snapshot creates none.
     */
    public function test_observer_and_polling_creates_nothing(): void {
        global $DB;
        $activity = $this->embed(NOGROUPS, ['livemarkers' => 1]);
        $learner = $this->learner('Obs');
        $other = $this->getDataGenerator()->create_user();
        $course = $this->courses['c2'];
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($other->id, $course->id, 'student');

        $this->setUser($learner);
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        \mod_page\event\course_module_viewed::create([
            'context' => \context_module::instance($page->cmid), 'objectid' => $page->id,
        ])->trigger();
        $this->setUser($other);
        \core\event\course_viewed::create(['context' => \context_course::instance($course->id)])->trigger();

        $this->assertSame(1, $DB->count_records('local_adele_activity'), 'one row per learner and course; no row for others');
        $this->assertTrue($DB->record_exists('local_adele_activity', ['userid' => $learner->id, 'courseid' => $course->id]));

        $before = $DB->count_records('local_adele_activity');
        $this->setUser($this->teacher());
        $this->snap($activity);
        get_node_people::execute($this->ctx($activity), -1, 'n2', 'active', 0);
        $this->assertSame($before, $DB->count_records('local_adele_activity'), 'polling creates no activity');
    }

    /**
     * Retention: the task removes rows older than the setting.
     */
    public function test_purge(): void {
        global $DB;
        $user = $this->learner('Old');
        $this->seen($user, 'c1', self::NOW - 31 * DAYSECS);
        $this->seen($user, 'c2', self::NOW - 29 * DAYSECS);
        (new task\purge_live_activity())->execute();
        $this->assertSame(
            [(int) $this->courses['c2']->id],
            array_map('intval', $DB->get_fieldset_select('local_adele_activity', 'courseid', 'userid = ?', [$user->id]))
        );
        $this->expectOutputRegex('/1 record/');
    }

    /**
     * Privacy: exported with the person, deleted with them.
     */
    public function test_privacy(): void {
        global $DB;
        $user = $this->learner('Priv');
        $this->seen($user, 'c1', self::NOW);
        $system = \context_system::instance();
        $contextlist = privacy\provider::get_contexts_for_userid((int) $user->id);
        $this->assertContains((int) $system->id, array_map('intval', $contextlist->get_contextids()));
        $approved = new \core_privacy\local\request\approved_contextlist($user, 'local_adele', [$system->id]);
        privacy\provider::export_user_data($approved);
        $writer = \core_privacy\local\request\writer::with_context($system);
        $this->assertNotEmpty($writer->get_data(['live_activity']));
        privacy\provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('local_adele_activity', ['userid' => $user->id]));
    }
}
