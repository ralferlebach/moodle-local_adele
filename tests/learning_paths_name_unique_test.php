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

/**
 * A learning path must not be saved under a name already used by another path (#492).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::assert_name_unique
 */
final class learning_paths_name_unique_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Insert a learning path with the given name.
     *
     * @param string $name
     * @return int learning path id
     */
    private function make_lp(string $name): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'createdby' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A free name passes.
     */
    public function test_unique_name_is_allowed(): void {
        $this->make_lp('Foo');
        learning_paths::assert_name_unique('Bar', 0);
        $this->assertTrue(true, 'A unique name must be allowed.');
    }

    /**
     * Re-saving a path under its own name is allowed (it is excluded from the check).
     */
    public function test_editing_a_path_keeps_its_own_name(): void {
        $id = $this->make_lp('Foo');
        learning_paths::assert_name_unique('Foo', $id);
        $this->assertTrue(true, 'A path must be re-savable under its own name.');
    }

    /**
     * Creating a new path with a name another path already uses is rejected.
     */
    public function test_new_path_with_taken_name_is_rejected(): void {
        $this->make_lp('Foo');
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/already exists/');
        learning_paths::assert_name_unique('Foo', 0);
    }

    /**
     * The name check is case-insensitive ("Foo" collides with "foo").
     */
    public function test_name_check_is_case_insensitive(): void {
        $this->make_lp('Foo');
        $this->expectException(\moodle_exception::class);
        learning_paths::assert_name_unique('foo', 0);
    }

    /**
     * Renaming one path into a name another path holds is rejected.
     */
    public function test_renaming_into_a_taken_name_is_rejected(): void {
        $this->make_lp('Foo');
        $bazid = $this->make_lp('Baz');
        $this->expectException(\moodle_exception::class);
        learning_paths::assert_name_unique('Foo', $bazid);
    }
}
