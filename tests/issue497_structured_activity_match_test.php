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

/**
 * Regression tests for issue #497.
 *
 * The event-driven search for learning paths referencing a quiz or CAT instance
 * must be structural and type-safe (integer OR string ids), not a fragile LIKE
 * on the serialized JSON. A malformed snapshot must not abort the rest.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Structured activity-matching regression tests (#497).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue497_structured_activity_match_test extends advanced_testcase {
    /** @var int The learner id used across the test. */
    private int $userid = 0;

    /**
     * Create a user to own the paths.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->userid = (int) self::getDataGenerator()->create_user()->id;
    }

    /**
     * Persist a learning path plus an active user path from the given tree nodes.
     *
     * @param array $treenodes
     * @return int The user-path id.
     */
    private function persist(array $treenodes): int {
        return $this->persist_raw(json_encode(['tree' => ['nodes' => $treenodes], 'modules' => null]));
    }

    /**
     * Persist an active user path with a raw json body (used to inject malformed json).
     *
     * @param string $rawjson
     * @return int The user-path id.
     */
    private function persist_raw(string $rawjson): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP',
            'json' => json_encode(['tree' => ['nodes' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $this->userid,
        ]);
        return (int) $DB->insert_record('local_adele_path_user', [
            'user_id' => $this->userid,
            'course_id' => 1,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $this->userid,
            'json' => $rawjson,
        ]);
    }

    /**
     * Build a node whose completion condition references an activity instance.
     *
     * @param string $id
     * @param string $label
     * @param string $key
     * @param mixed $value
     * @return array
     */
    private function node(string $id, string $label, string $key, $value): array {
        return [
            'id' => $id,
            'data' => ['course_node_id' => ['1'], 'fullname' => 'N'],
            'completion' => [
                'nodes' => [
                    ['id' => 'condition_1', 'data' => ['label' => $label, 'value' => [$key => $value, 'grade' => 1]]],
                ],
            ],
        ];
    }

    /**
     * Collect the objectids of captured user_path_updated events.
     *
     * @param object $sink
     * @return array
     */
    private function fired($sink): array {
        $ids = [];
        foreach ($sink->get_events() as $e) {
            if ($e->eventname === '\\local_adele\\event\\user_path_updated') {
                $ids[] = (int) $e->objectid;
            }
        }
        return $ids;
    }

    /**
     * An integer-serialised quizid must be matched (the case the old LIKE missed).
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_matches_integer_quizid(): void {
        $pathid = $this->persist([$this->node('dndnode_1', 'modquiz', 'quizid', 4242)]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_quiz_paths($this->userid, 4242);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([$pathid], $fired, 'Integer-serialised quizid must be matched (#497).');
    }

    /**
     * A string-serialised quizid must also be matched.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_matches_string_quizid(): void {
        $pathid = $this->persist([$this->node('dndnode_1', 'modquiz', 'quizid', '4242')]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_quiz_paths($this->userid, 4242);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([$pathid], $fired, 'String-serialised quizid must be matched (#497).');
    }

    /**
     * A different quizid must not match.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_no_match_for_different_quizid(): void {
        $this->persist([$this->node('dndnode_1', 'modquiz', 'quizid', 4242)]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_quiz_paths($this->userid, 9999);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([], $fired, 'An unrelated quizid must not trigger a recompute.');
    }

    /**
     * A malformed snapshot must not abort processing of the remaining paths.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_invalid_json_does_not_abort(): void {
        $this->persist_raw('{ this is : not valid json ');
        $good = $this->persist([$this->node('dndnode_1', 'modquiz', 'quizid', 4242)]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_quiz_paths($this->userid, 4242);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([$good], $fired, 'A malformed snapshot must be skipped, the valid path still recomputed.');
    }

    /**
     * A quiz referenced by several nodes triggers one recompute per path.
     *
     * @covers \local_adele\learning_path_update::recompute_quiz_paths
     * @return void
     */
    public function test_single_recompute_when_quiz_in_multiple_nodes(): void {
        $pathid = $this->persist([
            $this->node('dndnode_1', 'modquiz', 'quizid', 4242),
            $this->node('dndnode_2', 'modquiz', 'quizid', 4242),
        ]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_quiz_paths($this->userid, 4242);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([$pathid], $fired, 'A path referencing the quiz twice must recompute exactly once.');
    }

    /**
     * The CAT quiz componentid is matched type-safely as well.
     *
     * @covers \local_adele\learning_path_update::recompute_catquiz_paths
     * @return void
     */
    public function test_catquiz_matches_integer_componentid(): void {
        $pathid = $this->persist([$this->node('dndnode_1', 'catquiz', 'componentid', 777)]);
        $sink = $this->redirectEvents();
        learning_path_update::recompute_catquiz_paths($this->userid, 777);
        $fired = $this->fired($sink);
        $sink->close();
        $this->assertSame([$pathid], $fired, 'Integer componentid must be matched for CAT quiz (#497).');
    }
}
