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
 * Regression tests for the info-symbol / locked-node feedback cluster found while
 * reviewing the student view (#483 follow-up). Four defects, all in the recompute
 * feedback engine, reproduced end-to-end through relation_update::updated_single():
 *
 *  #0  A single-course completion info-symbol dropped its requirement word
 *      (" bearbeiten." instead of "diesen Kurs bearbeiten.") because the info
 *      template's {item}->{item_total} swap resolved to empty: item_total was only
 *      built for multi-course stacks. course_completed now sets item_total for the
 *      single-course case too.
 *  #1  When a completion feedback node's `information` field is unset (paths created
 *      before the frontend auto-seeded it), the info-symbol was blank. getfeedback()
 *      now falls back to the condition's lang default.
 *  #2  Same for a restriction feedback node's `information`: the info-symbol now falls
 *      back to the restriction condition's lang default (e.g. "Vorgaengernode ... abschliessen").
 *  #3  A merely-locked (still satisfiable) node whose restriction condition has an empty
 *      childCondition reported status_restriction 'after' ("kann nicht mehr freigeschaltet
 *      werden") because before_valid was never populated. It must be 'before' with the
 *      requirement text available.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\course_completion\conditions\course_completed;
use local_adele\event\user_path_updated;

/**
 * Info-symbol default fallback + locked-node feedback regression tests (#483 follow-up).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\relation_update::getfeedback
 * @covers \local_adele\relation_update::get_default_condition_string
 * @covers \local_adele\relation_update::getnodestatusforrestriciton
 * @covers \local_adele\course_completion\conditions\course_completed::get_completion_status
 */
final class info_symbol_and_locked_feedback_test extends advanced_testcase {
    /** @var int */
    private int $c1;
    /** @var int */
    private int $c2;
    /** @var int */
    private int $userid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $this->c1 = (int) $gen->create_course(['enablecompletion' => 1])->id;
        $this->c2 = (int) $gen->create_course(['enablecompletion' => 1])->id;
        $user = $gen->create_user();
        $this->userid = (int) $user->id;
        $this->setUser($user);
        $gen->enrol_user($this->userid, $this->c1);
        $gen->enrol_user($this->userid, $this->c2);
    }

    /**
     * Recompute a tree for the test user and return the persisted user_path_relation.
     *
     * @param array $tree Decoded learning-path json.
     * @return array The decoded user_path_relation feedback map keyed by node id.
     */
    private function recompute(array $tree): array {
        global $DB;
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'info/feedback LP', 'json' => json_encode($tree),
            'timecreated' => time(), 'timemodified' => time(), 'createdby' => $this->userid,
        ]);
        $id = $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $this->userid, 'course_id' => $this->c1, 'learning_path_id' => $lpid,
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
        return json_decode($DB->get_record('local_adele_path_user', ['id' => $id])->json, true)['user_path_relation'];
    }

    /**
     * A single completion node (course_completed on one course), min_courses 1.
     * $withinfo controls whether the feedback node carries an `information` template.
     *
     * @param bool $withinfo
     * @return array
     */
    private function build_completion_tree(bool $withinfo): array {
        $feedbackdata = [
            'label' => 'feedback', 'visibility' => true,
            'feedback_before' => '{item} erfolgreich bearbeiten',
            'feedback_inbetween' => '{item} erfolgreich bearbeiten',
            'feedback_after' => '{item} erfolgreich bearbeitet haben',
        ];
        if ($withinfo) {
            $feedbackdata['information'] = '{item} bearbeiten.';
        }
        return [
            'tree' => [
                'nodes' => [[
                    'id' => 'dndnode_1', 'type' => 'circle',
                    'parentCourse' => ['starting_node'], 'childCourse' => [],
                    'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Node A'],
                    'restriction' => ['nodes' => [], 'edges' => []],
                    'completion' => [
                        'nodes' => [
                            [
                                'id' => 'condition_1',
                                'parentCondition' => ['starting_condition'],
                                'childCondition' => ['condition_1_feedback'],
                                'data' => [
                                    'id' => 170, 'label' => 'course_completed',
                                    'value' => ['min_courses' => 1],
                                    'description_before' => 'complete the course',
                                ],
                            ],
                            [
                                'id' => 'condition_1_feedback',
                                'parentCondition' => ['condition_1'],
                                'childCondition' => [],
                                'data' => $feedbackdata,
                            ],
                        ],
                        'edges' => [],
                    ],
                ]],
                'edges' => [],
            ],
            'modules' => null,
        ];
    }

    /**
     * Two nodes: dndnode_1 (a course_completed node, left INCOMPLETE) and dndnode_2,
     * which is locked behind a parent_courses restriction on dndnode_1. The restriction
     * condition's childCondition is deliberately EMPTY (unwired feedback node) - the #3
     * repro. The restriction feedback node carries NO `information` field - the #2 repro.
     *
     * @return array
     */
    private function build_restriction_tree(): array {
        return [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1', 'type' => 'circle',
                        'parentCourse' => ['starting_node'], 'childCourse' => ['dndnode_2'],
                        'data' => ['course_node_id' => [(string) $this->c1], 'fullname' => 'Testkurs 01'],
                        'restriction' => ['nodes' => [], 'edges' => []],
                        'completion' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => ['condition_1_feedback'],
                                    'data' => [
                                        'id' => 170, 'label' => 'course_completed',
                                        'value' => ['min_courses' => 1],
                                        'description_before' => 'complete the course',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => ['condition_1'], 'childCondition' => [],
                                    'data' => ['label' => 'feedback', 'visibility' => true,
                                        'feedback_before' => '{item} erfolgreich bearbeiten'],
                                ],
                            ],
                            'edges' => [],
                        ],
                    ],
                    [
                        'id' => 'dndnode_2', 'type' => 'circle',
                        'parentCourse' => ['dndnode_1'], 'childCourse' => [],
                        'data' => ['course_node_id' => [(string) $this->c2], 'fullname' => 'Testkurs 02'],
                        'restriction' => [
                            'nodes' => [
                                [
                                    'id' => 'condition_1',
                                    'parentCondition' => ['starting_condition'],
                                    'childCondition' => [],
                                    'data' => [
                                        'id' => 150, 'label' => 'parent_courses',
                                        'value' => ['courses_id' => ['dndnode_1'], 'min_courses' => 1],
                                        'description_before' => 'Abschluss der Vorgaengernode  „{node_name}“',
                                    ],
                                ],
                                [
                                    'id' => 'condition_1_feedback',
                                    'parentCondition' => null, 'childCondition' => null,
                                    // No `information` field: exercises the #2 default fallback.
                                    'data' => ['label' => 'feedback', 'visibility' => true,
                                        'feedback_before' => 'Abschluss der Vorgaengernode  „{node_name}“'],
                                ],
                            ],
                            'edges' => [],
                        ],
                        'completion' => ['nodes' => [], 'edges' => []],
                    ],
                ],
                'edges' => [['source' => 'dndnode_1', 'target' => 'dndnode_2', 'data' => []]],
            ],
            'modules' => null,
        ];
    }

    /**
     * #0: a single-course completion info-symbol must render the full requirement
     * ("diesen Kurs bearbeiten."), not drop the leading word to " bearbeiten." because
     * the {item}->{item_total} swap found no item_total.
     *
     * @return void
     */
    public function test_single_course_info_symbol_keeps_requirement_word(): void {
        $item = get_string('course_description_before_condition_course_completed_item', 'local_adele');
        $relation = $this->recompute($this->build_completion_tree(true));
        $information = $relation['dndnode_1']['feedback']['completion']['information'] ?? [];
        $this->assertNotEmpty($information, 'The info-symbol must render for a visible completion condition.');
        $this->assertStringContainsString(
            $item,
            (string) $information[0],
            'The single-course info-symbol must keep its requirement word (regression #483).'
        );
        $this->assertStringStartsNotWith(
            ' ',
            (string) $information[0],
            'The info-symbol must not start with a dropped ({item_total}) placeholder.'
        );
    }

    /**
     * #1: when a completion feedback node has no `information` template, the info-symbol
     * falls back to the condition's lang default rather than rendering blank.
     *
     * @return void
     */
    public function test_completion_info_symbol_falls_back_to_lang_default(): void {
        $item = get_string('course_description_before_condition_course_completed_item', 'local_adele');
        $relation = $this->recompute($this->build_completion_tree(false));
        $information = $relation['dndnode_1']['feedback']['completion']['information'] ?? [];
        $this->assertNotEmpty($information, 'The info-symbol must fall back to a default when the field is unset.');
        $this->assertStringContainsString(
            $item,
            (string) $information[0],
            'The unset completion info-symbol must fall back to course_completed lang default.'
        );
    }

    /**
     * #2: when a restriction feedback node has no `information` template, the info-symbol
     * falls back to the restriction condition's lang default, showing the requirement
     * (the parent node name) rather than rendering blank.
     *
     * @return void
     */
    public function test_restriction_info_symbol_falls_back_to_lang_default(): void {
        $relation = $this->recompute($this->build_restriction_tree());
        $information = $relation['dndnode_2']['feedback']['restriction']['information'] ?? [];
        $text = \is_array($information) ? implode(' ', $information) : (string) $information;
        $this->assertNotSame('', trim($text), 'The unset restriction info-symbol must fall back to a default.');
        $this->assertStringContainsString(
            'Testkurs 01',
            $text,
            'The restriction info-symbol default must name the parent node it depends on.'
        );
    }

    /**
     * #3: a node locked behind an as-yet-unmet (but satisfiable) parent_courses
     * restriction whose condition has an empty childCondition must report
     * status_restriction 'before' (pending) with the requirement in before_valid -
     * NOT 'after' ("kann nicht mehr freigeschaltet werden").
     *
     * @return void
     */
    public function test_locked_parent_courses_node_is_before_not_after(): void {
        $relation = $this->recompute($this->build_restriction_tree());
        $fb = $relation['dndnode_2']['feedback'];
        $this->assertSame(
            'before',
            $fb['status_restriction'],
            'A satisfiable locked node must be "before" (pending), not "after" (permanently closed).'
        );
        $this->assertSame('not_accessible', $fb['status'], 'A locked node is not accessible.');
        $this->assertNotEmpty(
            array_filter((array) ($fb['restriction']['before_valid'] ?? [])),
            'before_valid must carry the requirement text so the student sees what to do.'
        );
    }

    /**
     * #0 (unit): course_completed must set both item and item_total for a single course,
     * so the info-symbol swap has an absolute placeholder to render.
     *
     * @return void
     */
    public function test_single_course_sets_item_total_placeholder(): void {
        $node = [
            'data' => ['course_node_id' => [(string) $this->c1]],
            'completion' => ['nodes' => [[
                'id' => 'condition_1',
                'data' => ['label' => 'course_completed', 'value' => ['min_courses' => 1]],
            ]]],
        ];
        $placeholders = (new course_completed())->get_completion_status($node, $this->userid)['condition_1']['placeholders'] ?? [];
        $this->assertArrayHasKey('item_total', $placeholders, 'Single-course completion must set item_total.');
        $this->assertSame(
            $placeholders['item'] ?? null,
            $placeholders['item_total'] ?? null,
            'For a single course the absolute item_total equals the state item.'
        );
    }
}
