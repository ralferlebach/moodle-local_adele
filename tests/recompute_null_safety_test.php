<?php
// This file is part of Moodle - https://moodle.org/
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

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Null-safety of the recompute path: a node whose restriction/completion sub-structure
 * is partial or null (as can happen for a freshly-subscribed or edge-shaped path) must
 * recompute without throwing "Trying to access array offset on null" - it must be treated
 * exactly like the already-supported empty case.
 *
 * getfeedback() and validatenodecompletion() are the directly-callable seams that carried
 * the unguarded accesses; on the pre-fix code each case below throws.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::getfeedback
 * @covers \local_adele\relation_update::validatenodecompletion
 */
final class recompute_null_safety_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * getfeedback() always returns the completion+restriction envelope.
     *
     * @param mixed $result
     */
    private function assert_envelope($result): void {
        $this->assertIsArray($result);
        $this->assertArrayHasKey('completion', $result);
        $this->assertArrayHasKey('restriction', $result);
    }

    /**
     * A node with NO completion key must not crash getfeedback (foreach over null).
     */
    public function test_getfeedback_missing_completion_key(): void {
        $node = ['id' => 'n1', 'data' => [], 'restriction' => ['nodes' => []]];
        $this->assert_envelope(relation_update::getfeedback($node, [], []));
    }

    /**
     * A node whose completion is explicitly null must not crash (the exact live error).
     */
    public function test_getfeedback_null_completion(): void {
        $node = ['id' => 'n1', 'data' => [], 'completion' => null, 'restriction' => ['nodes' => []]];
        $this->assert_envelope(relation_update::getfeedback($node, [], []));
    }

    /**
     * A restriction feedback-node with null data must not crash (visibility read).
     */
    public function test_getfeedback_restriction_feedbacknode_null_data(): void {
        $node = [
            'id' => 'n1',
            'data' => [],
            'completion' => ['nodes' => []],
            'restriction' => ['nodes' => [['id' => 'condition_1_feedback', 'data' => null]]],
        ];
        $this->assert_envelope(relation_update::getfeedback($node, [], []));
    }

    /**
     * A restriction that has no 'nodes' key must not crash getfeedback.
     */
    public function test_getfeedback_restriction_without_nodes(): void {
        $node = ['id' => 'n1', 'data' => [], 'completion' => ['nodes' => []], 'restriction' => []];
        $this->assert_envelope(relation_update::getfeedback($node, [], []));
    }

    /**
     * validatenodecompletion() over a node with null completion returns the empty envelope.
     */
    public function test_validatenodecompletion_null_completion(): void {
        $node = ['id' => 'n1', 'data' => [], 'completion' => null, 'restriction' => ['nodes' => []]];
        $nodecompletedname = [];
        $userpath = (object) ['user_id' => 1, 'json' => ['tree' => ['nodes' => []]]];

        $result = relation_update::validatenodecompletion(
            [],
            $node,
            [],
            $userpath,
            [],
            1,
            [],
            $nodecompletedname
        );

        // Same shape the empty case already yields, so downstream getconditionnode() sees [].
        $this->assertSame([], $result['completionnodepaths']);
        $this->assertArrayHasKey('feedback', $result);
    }

    /**
     * validatenodecompletion() over a node with BOTH completion and restriction null recomputes safely.
     */
    public function test_validatenodecompletion_null_restriction_and_completion(): void {
        $node = ['id' => 'n1', 'data' => [], 'completion' => null, 'restriction' => null];
        $nodecompletedname = [];
        $userpath = (object) ['user_id' => 1, 'json' => ['tree' => ['nodes' => []]]];

        $result = relation_update::validatenodecompletion(
            [],
            $node,
            [],
            $userpath,
            [],
            1,
            [],
            $nodecompletedname
        );

        $this->assertArrayHasKey('feedback', $result);
        $this->assertArrayHasKey('status', $result['feedback']);
    }

    /**
     * getconditionnode([]) - the consumer of the empty default - is valid=false, not a crash.
     */
    public function test_getconditionnode_empty_is_invalid_not_fatal(): void {
        $result = relation_update::getconditionnode([], 'completion');
        $this->assertFalse($result['valid']);
        $this->assertSame([], $result['conditions']);
    }
}
