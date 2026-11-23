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

use advanced_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * PHPUnit test case for the 'catquiz' class in local_adele.
 *
 * @package     local_adele
 * @author       local_adele
 * @copyright  2023 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(catquiz::class)]
final class catquiz_test extends advanced_testcase {
    /**
     * The class_exists mock is global state; it must not reach the next case.
     */
    protected function tearDown(): void {
        $this->reset_class_exists_mock();
        parent::tearDown();
    }

    /**
     * Test the get_catquiz_tests function.
     */
    public function test_get_catquiz_tests_class_does_not_exist(): void {
        $this->mock_class_exists('local_catquiz\testenvironment', false);

        $result = catquiz::get_catquiz_tests([]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result, 'Expected an empty array when class does not exist');
    }

    /**
     * With catquiz installed, the tests of the path's courses are listed -
     * templates and tests of other courses are not.
     *
     * Real records through catquiz's own query (testenvironment::
     * get_environments), no mocked $DB. Until 2026-11-23 this case mocked $DB
     * and passed course_node_id as an int, which the production code reads as
     * an array; it errored when run alone and was skipped in the suite only
     * because the class_exists mock of the previous case leaked into it.
     */
    public function test_get_catquiz_tests_class_exists_with_records(): void {
        global $DB;
        $this->resetAfterTest();
        $this->reset_class_exists_mock();
        if (!\class_exists('local_catquiz\testenvironment')) {
            // The CI installs local_catquiz (moodle-plugin-ci*.yml); a site
            // without it has nothing to list.
            $this->markTestSkipped('local_catquiz is not installed.');
        }
        $incourse = $this->getDataGenerator()->create_course(['fullname' => 'Course on the path']);
        $other = $this->getDataGenerator()->create_course(['fullname' => 'Course elsewhere']);
        $now = time();
        $test = fn(int $courseid, int $componentid, string $name) => $DB->insert_record('local_catquiz_tests', [
            'componentid' => $componentid, 'component' => 'mod_adaptivequiz', 'courseid' => $courseid,
            'name' => $name, 'json' => json_encode(['name' => $name]), 'status' => 1,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
        $listed = $test($incourse->id, 7, 'Test on the path');
        $test($other->id, 8, 'Test elsewhere');
        $test($incourse->id, 0, 'Template');

        $result = catquiz::get_catquiz_tests([['course_node_id' => [$incourse->id]]]);

        $this->assertCount(1, $result);
        $this->assertSame('Test on the path', $result[0]['name']);
        $this->assertSame('Course on the path', $result[0]['coursename']);
        $this->assertEquals($incourse->id, $result[0]['courseid']);
        $this->assertEquals($listed, $result[0]['id']);
    }

    /**
     * Mock the class_exists function within the local_adele namespace.
     * @param string $classname
     * @param bool $exists
     * @return bool
     */
    protected function mock_class_exists($classname, $exists) {
        if (!function_exists('local_adele\class_exists')) {
            /**
             *  Mock the class_exists return value.
             * @param string $name
             * @return bool
             */
            function class_exists($name) {
                global $classexistsmocks;
                return isset($classexistsmocks[$name]) ? $classexistsmocks[$name] : \class_exists($name);
            }
        }
        global $classexistsmocks;
        $classexistsmocks[$classname] = $exists;
    }

    /**
     * Reset the class_exists mock.
     */
    protected function reset_class_exists_mock() {
        global $classexistsmocks;
        $classexistsmocks = [];
    }
}
