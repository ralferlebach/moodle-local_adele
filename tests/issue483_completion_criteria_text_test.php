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
use context_system;
use local_adele\course_completion\conditions\course_completed;
use local_adele\event\user_path_updated;

/**
 * A stack's completion criterion is shown in two places, which must stay consistent
 * with the editor setting and the progress bar (GitHub #483):
 *  - the info-symbol (ⓘ) shows the ABSOLUTE criterion: the configured minimum of the
 *    total ("min von total"), regardless of how many courses the participant finished;
 *  - the feedback messages show the CURRENT state (completed / remaining count).
 *
 * Scenario from the ticket: a stack of 3 courses, the participant has completed 2.
 * As the editor's min_courses is set to 1 / 2 / 3, the info-symbol must read 1 / 2 / 3
 * "von 3", while the feedback narrative reads 2 / 2 / 1 (done, done, one-to-go).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
 * @covers \local_adele\relation_update::getfeedback
 */
final class issue483_completion_criteria_text_test extends advanced_testcase {
    /** @var int[] The three course ids of the stack. */
    private array $courseids = [];
    /** @var int */
    private int $userid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $gen = self::getDataGenerator();
        for ($i = 0; $i < 3; $i++) {
            $this->courseids[] = (int) $gen->create_course(['enablecompletion' => 1])->id;
        }
        $user = $gen->create_user();
        $this->userid = (int) $user->id;
        foreach ($this->courseids as $cid) {
            $gen->enrol_user($this->userid, $cid);
        }
        // The participant has completed 2 of the 3 courses.
        $this->mark_course_complete($this->courseids[0], $this->userid);
        $this->mark_course_complete($this->courseids[1], $this->userid);
    }

    /**
     * Insert a course_completions row (timecompleted) and purge the completion cache
     * so is_course_complete() sees it - same approach as adele_learningpath_testcase.
     *
     * @param int $courseid
     * @param int $userid
     */
    private function mark_course_complete(int $courseid, int $userid): void {
        global $DB;
        $DB->insert_record('course_completions', (object) [
            'course' => $courseid,
            'userid' => $userid,
            'timeenrolled' => time(),
            'timestarted' => time(),
            'timecompleted' => time(),
            'reaggregate' => 0,
        ]);
        \cache::make('core', 'coursecompletion')->delete($userid . '_' . $courseid);
    }

    /**
     * The leading integer of a rendered criterion string (the "X" in "X von 3 ...").
     *
     * @param string $s
     * @return int
     */
    private function leading(string $s): int {
        preg_match('/\d+/', $s, $m);
        return isset($m[0]) ? (int) $m[0] : -1;
    }

    /**
     * Run get_completion_status() for a configured min_courses and return the leading
     * numbers of both placeholders: ['item' => state, 'item_total' => absolute].
     *
     * @param int $min
     * @return array{item:int,item_total:int}
     */
    private function placeholders(int $min): array {
        $node = [
            'data' => ['course_node_id' => $this->courseids],
            'completion' => ['nodes' => [
                [
                    'id' => 'condition_1',
                    'data' => ['label' => 'course_completed', 'value' => ['min_courses' => $min]],
                ],
            ]],
        ];
        $ph = (new course_completed())->get_completion_status($node, $this->userid)['condition_1']['placeholders'] ?? [];
        return [
            'item' => $this->leading((string) ($ph['item'] ?? '')),
            'item_total' => $this->leading((string) ($ph['item_total'] ?? '')),
        ];
    }

    /**
     * The info-symbol placeholder (item_total) must show the CONFIGURED minimum for each
     * editor setting - 1 / 2 / 3 - regardless of the participant's progress.
     */
    public function test_info_symbol_shows_absolute_minimum(): void {
        $actual = [];
        foreach ([1, 2, 3] as $min) {
            $actual[$min] = $this->placeholders($min)['item_total'];
        }
        $this->assertSame(
            [1 => 1, 2 => 2, 3 => 3],
            $actual,
            'The info-symbol (item_total) must show the configured min_courses (1/2/3 von 3).'
        );
    }

    /**
     * The feedback placeholder (item) must reflect the CURRENT state: the completed
     * count once the minimum is met (2 / 2), otherwise the remaining count (1).
     */
    public function test_feedback_shows_current_state(): void {
        $actual = [];
        foreach ([1, 2, 3] as $min) {
            $actual[$min] = $this->placeholders($min)['item'];
        }
        $this->assertSame(
            [1 => 2, 2 => 2, 3 => 1],
            $actual,
            'The feedback (item) must reflect the completed (2/2) or remaining (1) count.'
        );
    }

    /**
     * A stack node with a course_completed completion criterion and a wired feedback
     * node whose templates all use {item}. After recompute, the info-symbol
     * (completion.information) must render the ABSOLUTE minimum while the feedback
     * slots (completion.before) render the CURRENT-state count.
     *
     * @param int $min configured min_courses
     * @return array{info:int,feedback:int,inforaw:string,feedbackraw:string}
     */
    private function recompute_stack(int $min): array {
        global $DB;
        $tree = [
            'tree' => [
                'nodes' => [[
                    'id' => 'dndnode_1', 'type' => 'orcourses',
                    'parentCourse' => ['starting_node'], 'childCourse' => [],
                    'data' => ['course_node_id' => array_map('strval', $this->courseids), 'fullname' => 'Stack'],
                    'restriction' => ['nodes' => [], 'edges' => []],
                    'completion' => [
                        'nodes' => [
                            [
                                'id' => 'condition_1',
                                'parentCondition' => ['starting_condition'],
                                'childCondition' => ['condition_1_feedback'],
                                'data' => [
                                    'id' => 170,
                                    'label' => 'course_completed',
                                    'value' => ['min_courses' => $min],
                                    'description_before' => 'complete the courses',
                                ],
                            ],
                            [
                                'id' => 'condition_1_feedback',
                                'parentCondition' => ['condition_1'],
                                'childCondition' => [],
                                'data' => [
                                    'label' => 'feedback',
                                    'visibility' => true,
                                    'information' => '{item} bearbeiten.',
                                    'feedback_before' => '{item} erfolgreich bearbeiten ',
                                    'feedback_inbetween' => '{item} erfolgreich bearbeiten',
                                    'feedback_after' => '{item} erfolgreich bearbeitet haben',
                                    'feedback_before_checkmark' => false,
                                    'feedback_inbetween_checkmark' => false,
                                    'feedback_after_checkmark' => false,
                                ],
                            ],
                        ],
                        'edges' => [],
                    ],
                ]],
                'edges' => [],
            ],
            'modules' => null,
        ];
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'stack ' . $min, 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $this->userid, 'course_id' => $this->courseids[0], 'learning_path_id' => $lpid,
            'status' => 'active', 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $userpath = $DB->get_record('local_adele_path_user', ['id' => $id]);
        $userpath->json = json_decode($userpath->json, true);
        $event = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => ['userpath' => $userpath],
        ]);
        relation_update::updated_single($event);

        $json = json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true);
        $fb = $json['user_path_relation']['dndnode_1']['feedback']['completion'] ?? [];
        $info = implode(' ', (array) ($fb['information'] ?? []));
        // The feedback narrative lands in before/inbetween depending on state; join both.
        $feedback = trim(implode(' ', (array) ($fb['before'] ?? [])) . ' ' . implode(' ', (array) ($fb['inbetween'] ?? [])));
        return [
            'info' => $this->leading($info),
            'feedback' => $this->leading($feedback),
            'inforaw' => $info,
            'feedbackraw' => $feedback,
        ];
    }

    /**
     * End-to-end through the recompute: the info-symbol shows the absolute minimum
     * (1/2/3) while the feedback shows the current-state count (2/2/1) - and they never
     * collide, proving the {item} / {item_total} split works through getfeedback.
     */
    public function test_recompute_splits_info_and_feedback(): void {
        $info = [];
        $feedback = [];
        foreach ([1, 2, 3] as $min) {
            $r = $this->recompute_stack($min);
            $info[$min] = $r['info'];
            $feedback[$min] = $r['feedback'];
            // Guard against an empty render (unresolved placeholder stripped to '').
            $this->assertNotSame('', $r['inforaw'], "min=$min: info-symbol must not be empty.");
            $this->assertNotSame('', $r['feedbackraw'], "min=$min: feedback must not be empty.");
        }
        $this->assertSame(
            [1 => 1, 2 => 2, 3 => 3],
            $info,
            'Info-symbol (completion.information) must show the configured minimum.'
        );
        $this->assertSame(
            [1 => 2, 2 => 2, 3 => 1],
            $feedback,
            'Feedback (completion.before/inbetween) must show the current-state count.'
        );
    }
}
