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
use local_adele\external\get_learningpaths;

/**
 * A course editing-teacher who is NOT a system-level learning-path editor must still
 * be able to open the learning-path activity's teacher view in their own course.
 *
 * The teacher view (LearningpathsEdit.vue, view=teacher) loads get_learningpaths on
 * mount; that service gated only on check_access() (system editor status), so a plain
 * editing teacher was denied before reaching any checkbox. "Editor" means editing the
 * path structure at system level; operating the path inside a course is a teacher right.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @covers \local_adele\external\get_learningpaths
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class teacher_course_load_access_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * An editing teacher who owns no learning path may load the teacher-view list
     * service in their course context without a capability exception.
     */
    public function test_editing_teacher_can_load_learningpaths(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $coursecontext = context_course::instance($course->id);

        $result = get_learningpaths::execute($teacher->id, 0, $coursecontext->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('edit', $result);
        $this->assertArrayHasKey('view', $result);
        // The teacher owns no path, so the list stays scoped to their own (zero) editor
        // paths - relaxing the gate must not leak other people's learning paths.
        $this->assertEmpty($result['edit']);
        $this->assertEmpty($result['view']);
    }

    /**
     * A plain enrolled student (no teacheredit, no editor rights) is still denied the
     * editor-list service, so the relaxation stays limited to course teachers.
     */
    public function test_plain_student_still_denied_learningpaths(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $coursecontext = context_course::instance($course->id);

        $this->expectException(\required_capability_exception::class);
        get_learningpaths::execute($student->id, 0, $coursecontext->id);
    }
}
