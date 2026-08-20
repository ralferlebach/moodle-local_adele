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
 * The modquiz completion condition must judge Moodle's OFFICIAL scaled quiz
 * grade as a PERCENTAGE - not the raw point sum of arbitrary attempts.
 *
 * GitHub #499: the condition compared quiz_attempts.sumgrades (raw points,
 * including previews and unfinished attempts) against the configured value.
 * A quiz worth 20 raw points scaled to a max grade of 10 with threshold 8
 * counted 12 raw points (= grade 6/10, a fail) as passed. Decision (Ralf +
 * product): the configured threshold is a PERCENTAGE of the quiz's maximum
 * grade, immune to rescaling and question-weight changes.
 *
 * All scenarios drive the REAL quiz engine (question usage, attempt
 * processing, Moodle's own grading) - no synthetic grade rows.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use local_adele\course_completion\conditions\modquiz;
use mod_quiz\quiz_settings;
use mod_quiz\quiz_attempt;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Percent semantics for the modquiz completion condition (#499).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\course_completion\conditions\modquiz
 */
final class issue499_modquiz_percent_test extends advanced_testcase {
    /** @var \stdClass Quiz instance. */
    private $quiz;
    /** @var \stdClass Student. */
    private $student;

    /**
     * A real quiz: 5 shortanswer questions (1 raw point each, sumgrades 5),
     * scaled to a maximum quiz grade of 10 - so every correct answer is worth
     * 20% of the final grade.
     *
     * @param float $maxgrade Quiz maximum grade.
     * @return void
     */
    private function build_quiz(float $maxgrade = 10.0): void {
        $gen = self::getDataGenerator();
        $course = $gen->create_course();
        $this->student = $gen->create_user();
        $gen->enrol_user($this->student->id, $course->id);

        $quizgen = $gen->get_plugin_generator('mod_quiz');
        $this->quiz = $quizgen->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => $maxgrade,
            'sumgrades' => 5,
            'attempts' => 0,
        ]);
        $questiongen = $gen->get_plugin_generator('core_question');
        $cat = $questiongen->create_question_category();
        for ($i = 0; $i < 5; $i++) {
            $q = $questiongen->create_question('shortanswer', null, ['category' => $cat->id]);
            quiz_add_quiz_question($q->id, $this->quiz);
        }
    }

    /**
     * Run one attempt through the real engine, answering $correct of the five
     * questions correctly ('frog' is the generator's correct answer).
     *
     * @param int $correct Number of correctly answered questions.
     * @param bool $finish Whether to submit the attempt.
     * @param bool $preview Whether this is a preview attempt.
     * @return void
     */
    private function attempt(int $correct, bool $finish = true, bool $preview = false): void {
        global $DB;
        $quizobj = quiz_settings::create($this->quiz->id, $this->student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attemptnumber = 1 + (int) $DB->count_records(
            'quiz_attempts',
            ['quiz' => $this->quiz->id, 'userid' => $this->student->id]
        );
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, false, $timenow, $preview, $this->student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $tosubmit = [];
        for ($slot = 1; $slot <= 5; $slot++) {
            $tosubmit[$slot] = ['answer' => $slot <= $correct ? 'frog' : 'wrong answer'];
        }
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $tosubmit);
        if ($finish) {
            $attemptobj = quiz_attempt::create($attempt->id);
            $attemptobj->process_finish($timenow, false);
        }
    }

    /**
     * Build the completion node for the condition class.
     *
     * @param string $threshold Configured minimum percentage.
     * @return array
     */
    private function node(string $threshold): array {
        return [
            'completion' => [
                'nodes' => [[
                    'id' => 'condition_1',
                    'data' => [
                        'label' => 'modquiz',
                        'value' => ['quizid' => $this->quiz->id, 'grade' => $threshold],
                    ],
                ]],
            ],
        ];
    }

    /**
     * Evaluate the condition for the student.
     *
     * @param string $threshold Configured minimum percentage.
     * @return array
     */
    private function evaluate(string $threshold): array {
        return (new modquiz())->get_completion_status($this->node($threshold), (int) $this->student->id);
    }

    /**
     * The ticket's core scenario: 60% (grade 6/10) must NOT satisfy an 80%
     * threshold; a second attempt at 80% (grade 8/10) must.
     *
     * @return void
     */
    public function test_scaled_percent_semantics(): void {
        $this->resetAfterTest(true);
        $this->build_quiz();

        // 3 of 5 correct = grade 6/10 = 60%.
        $this->attempt(3);
        $result = $this->evaluate('80');
        $this->assertFalse(
            $result['completed']['condition_1'],
            '60% (grade 6/10) must not satisfy an 80% threshold (#499).'
        );

        // Second attempt: 4 of 5 = grade 8/10 = 80% (grade method: highest).
        $this->attempt(4);
        $result = $this->evaluate('80');
        $this->assertTrue(
            $result['completed']['condition_1'],
            '80% (grade 8/10) must satisfy the 80% threshold.'
        );
    }

    /**
     * A percentage threshold works identically after the quiz is RESCALED -
     * the whole point of the percent decision (#499, Ralf's comment).
     *
     * @return void
     */
    public function test_rescaling_does_not_change_the_verdict(): void {
        global $DB;
        $this->resetAfterTest(true);
        $this->build_quiz();
        $this->attempt(4); // 80%.
        $this->assertTrue($this->evaluate('80')['completed']['condition_1']);

        // Rescale the quiz maximum grade 10 -> 100 (Moodle rescales quiz_grades).
        $quizobj = quiz_settings::create($this->quiz->id);
        $quizobj->get_grade_calculator()->update_quiz_maximum_grade(100.0);
        $this->assertTrue(
            $this->evaluate('80')['completed']['condition_1'],
            'Rescaling the quiz grade must not change a percentage verdict.'
        );
    }

    /**
     * Preview attempts (teacher testing) must neither complete the node nor
     * count as "in progress" - previously any attempt row did (#499).
     *
     * @return void
     */
    public function test_preview_attempt_is_ignored(): void {
        $this->resetAfterTest(true);
        $this->build_quiz();
        $this->attempt(5, true, true); // Perfect PREVIEW attempt.

        $result = $this->evaluate('4');
        $this->assertFalse(
            $result['completed']['condition_1'],
            'A preview attempt must never complete the node - the old raw comparison counted it.'
        );
        $this->assertFalse(
            $result['inbetween']['condition_1'],
            'A preview attempt must not show the quiz as "in progress".'
        );
    }

    /**
     * An unfinished (in-progress) attempt must not complete the node and must
     * not count as a graded try.
     *
     * @return void
     */
    public function test_unfinished_attempt_is_ignored(): void {
        $this->resetAfterTest(true);
        $this->build_quiz();
        $this->attempt(5, false); // All answers given, never submitted.

        $result = $this->evaluate('4');
        $this->assertFalse($result['completed']['condition_1']);
        $this->assertFalse(
            $result['inbetween']['condition_1'],
            'An in-progress attempt is not a finished try.'
        );
    }

    /**
     * A real finished attempt marks the quiz "in progress" even when below the
     * threshold (unchanged behaviour, now on filtered data).
     *
     * @return void
     */
    public function test_finished_attempt_counts_as_inbetween(): void {
        $this->resetAfterTest(true);
        $this->build_quiz();
        $this->attempt(1); // 20% - below any sane threshold.
        $result = $this->evaluate('80');
        $this->assertFalse($result['completed']['condition_1']);
        $this->assertTrue($result['inbetween']['condition_1']);
    }

    /**
     * A quiz with maximum grade 0 must not divide by zero and never completes
     * a positive threshold.
     *
     * @return void
     */
    public function test_zero_grade_quiz_is_guarded(): void {
        $this->resetAfterTest(true);
        $this->build_quiz(0.0);
        $this->attempt(5);
        $result = $this->evaluate('50');
        $this->assertFalse($result['completed']['condition_1']);
        $this->assertDebuggingNotCalled();
    }
}
