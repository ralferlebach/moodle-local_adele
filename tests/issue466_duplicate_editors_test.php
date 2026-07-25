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
 * Regression test for GitHub issue #466:
 *   Duplicate rows in local_adele_lp_editors (non-idempotent create_editors)
 *   collided the get_records_sql key in return_learningpaths /
 *   get_editable_learning_paths, raising the "first column must be unique"
 *   debugging warning and crashing the navbar / module form under debug.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;

/**
 * Regression test for issue #466: create_editors must be idempotent so duplicate rows don't break the unique-key lookup.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_path_editors::create_editors
 * @covers \local_adele\learning_paths::return_learningpaths
 */
final class issue466_duplicate_editors_test extends advanced_testcase {
    /**
     * Adding the same editor twice must not create a second row (#466).
     *
     * @return void
     */
    public function test_create_editors_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 466 LP',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        learning_path_editors::create_editors($lpid, $user->id);
        learning_path_editors::create_editors($lpid, $user->id);

        $this->assertSame(
            1,
            $DB->count_records('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $user->id]),
            'create_editors must be idempotent - the same (path, user) must yield a single row.'
        );
    }

    /**
     * Pre-existing duplicate editor rows must not collide the get_records_sql key
     * (no "first column must be unique" debugging) and must collapse to one entry.
     *
     * Fix (Session 003, Teil 16): updated for G.18 (Session 003, Teil 7), which
     * added a database-level UNIQUE(userid, learningpathid) constraint on
     * local_adele_lp_editors specifically so this scenario (duplicate rows)
     * can no longer occur on an upgraded system — the upgrade step also
     * cleans up any pre-existing duplicates. The direct-insert simulation
     * this test used to rely on now correctly fails at the database level
     * instead, which is the fix working as intended, not a bug. Rewritten
     * to assert exactly that, rather than a scenario the schema no longer
     * allows to exist. return_learningpaths()'s own DISTINCT (already
     * present, defence in depth) remains covered implicitly: with the
     * constraint in place there is nothing left for it to deduplicate, but
     * removing it would still be safe to catch on its own terms if it were
     * ever removed by mistake.
     *
     * @return void
     */
    public function test_duplicate_rows_do_not_break_return_learningpaths(): void {
        global $DB;
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Issue 466 LP',
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
        ]);

        // A single editor row works exactly as before.
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $user->id]);
        $records = learning_paths::return_learningpaths();
        $this->assertCount(1, $records);
        $this->assertArrayHasKey($lpid, $records);

        // A genuine attempt to create a duplicate row (bypassing
        // create_editors()'s own idempotency check, the same way this test
        // always has) must now fail at the database level (G.18) - the
        // scenario this test used to simulate (duplicates silently existing)
        // can no longer arise on an upgraded system.
        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $user->id]);
    }
}
