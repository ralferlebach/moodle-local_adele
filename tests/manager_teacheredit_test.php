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
use context_course;

/**
 * A Moodle Manager must be able to operate a learning path in a course exactly like an
 * editing teacher (GitHub #482). The manager archetype only carried system-level
 * local/adele:canmanage, so a Manager assigned at course/category level failed
 * check_access() (which checks canmanage at CONTEXT_SYSTEM) and lacked the
 * course-level local/adele:teacheredit entirely - producing "No permission" errors
 * across the editor. Granting the manager archetype local/adele:teacheredit (the same
 * capability that makes the editing-teacher view work) restores parity.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class manager_teacheredit_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Assign the standard Moodle Manager role to a fresh user inside a course and
     * return [$user, $coursecontext].
     *
     * @return array{0: \stdClass, 1: \context_course}
     */
    private function course_manager(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $managerroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerroleid, $user->id, $context->id);

        return [$user, $context];
    }

    /**
     * A course-level Manager holds local/adele:teacheredit in that course context -
     * the capability that gates the teacher/editor web services.
     */
    public function test_course_manager_has_teacheredit(): void {
        [$user, $context] = $this->course_manager();
        $this->setUser($user);

        $this->assertTrue(
            has_capability('local/adele:teacheredit', $context),
            'A Moodle Manager must have local/adele:teacheredit in a course, like an editing teacher.'
        );
    }

    /**
     * The web services the ticket fails on - get_learningpaths (opening the editor)
     * and get_lp_user_path_relation(s) (opening a learning-path user) - gate ONLY on
     * (teacheredit || check_access()); they do not fall back to canmanage. A
     * course-level Manager fails check_access() (it checks canmanage at CONTEXT_SYSTEM,
     * which a course-scoped role lacks), so teacheredit is what has to carry them.
     * Asserted on the gate condition directly, because the WS classes require an
     * isolated process to include externallib.
     */
    public function test_course_manager_satisfies_teacheredit_only_gate(): void {
        [$user, $context] = $this->course_manager();
        $this->setUser($user);

        // Sanity: this Manager is course-scoped, so the system-level check_access()
        // is empty for them - proving teacheredit is the only thing that can pass.
        $this->assertEmpty(
            learning_paths::check_access(),
            'A course-scoped Manager must fail the system-level check_access().'
        );

        $gatepasses = has_capability('local/adele:teacheredit', $context)
            || !empty(learning_paths::check_access());

        $this->assertTrue(
            $gatepasses,
            'A course Manager must satisfy the teacheredit-only editor gate (get_learningpaths, ' .
            'get_lp_user_path_relation) and not get "No permission".'
        );
    }

    /**
     * Granting a course Manager teacheredit must NOT hand them the global learning-path
     * creation button. That button is gated on learning_paths::check_access() (canmanage
     * /assist at CONTEXT_SYSTEM, or LP-editor membership) - which teacheredit does not
     * feed - so a course-scoped Manager stays without it, exactly like an editing teacher
     * who has not been added to a path (#482).
     */
    public function test_course_manager_does_not_get_global_button(): void {
        [$user, $context] = $this->course_manager();
        $this->setUser($user);

        // Sanity: they DO have the in-course editor capability...
        $this->assertTrue(
            has_capability('local/adele:teacheredit', $context),
            'Course Manager must be able to edit the learning path in their course.'
        );

        // ...but the global navbar/creation button (check_access) stays hidden.
        $this->assertEmpty(
            learning_paths::check_access(),
            'A course Manager must NOT see the global learning-path creation button.'
        );
    }
}
