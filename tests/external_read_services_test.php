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
use context_system;
use local_adele\external\get_availablecourses;
use local_adele\external\get_learningpaths;
use local_adele\external\get_learningpath;

// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Coverage-gap tests for the READ web services, exercised through ::execute().
 *
 * These are not asserted anywhere else: they check that the read services return
 * a sane structure (no exception), that the course summary is purified server-side
 * (#464 M5), and that a deleted master path is signalled to the student view (#446)
 * rather than returning a stale/exploded structure.
 *
 * The external classes require_once() the legacy lib/externallib.php, which demands
 * process isolation under PHPUnit (require_phpunit_isolation()).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @covers \local_adele\external\get_availablecourses
 * @covers \local_adele\external\get_learningpaths
 * @covers \local_adele\external\get_learningpath
 */
final class external_read_services_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // Get_availablecourses builds its SQL from these config values; make them
        // explicit so the query is deterministic and free of "undefined property".
        set_config('includetags', '', 'local_adele');
        set_config('excludetags', '', 'local_adele');
        set_config('catfilter', '', 'local_adele');
        set_config('selectconfig', 'all', 'local_adele');
    }

    /**
     * Insert a learning-path row with a minimal tree and return its id.
     *
     * @param string $name
     * @param int $creatorid
     * @return int
     */
    private function make_lp(string $name, int $creatorid): int {
        global $DB;
        $json = json_encode(['tree' => ['nodes' => [], 'edges' => []], 'modules' => null]);
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name,
            'description' => 'desc',
            'image' => '',
            'json' => $json,
            'visibility' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creatorid,
        ]);
    }

    /**
     * get_availablecourses returns the visible courses, and a <script> embedded
     * in a course summary is stripped by the server-side purify (#464 M5), while a
     * course NAME containing markup is returned inert (PARAM_TEXT).
     *
     * @return void
     */
    public function test_get_availablecourses_returns_and_purifies_summary(): void {
        $this->setAdminUser();

        $this->getDataGenerator()->create_course([
            'fullname' => 'Attack <b>Name</b>',
            'summary' => 'Hello <script>alert(1)</script> world',
            'summaryformat' => FORMAT_HTML,
        ]);

        $ctxid = context_system::instance()->id;
        $result = get_availablecourses::execute(2, 0, $ctxid);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result, 'At least the created course must be returned.');

        // Find our course by name (fullname is PARAM_TEXT / inert - no exception thrown).
        $found = null;
        foreach ($result as $course) {
            if (strpos((string) $course['fullname'], 'Attack') !== false) {
                $found = $course;
                break;
            }
        }
        $this->assertNotNull($found, 'The created course must appear in availablecourses.');

        // The <script> must be stripped from the purified summary.
        $this->assertStringNotContainsString('<script>', (string) $found['summary']);
        $this->assertStringContainsString('Hello', (string) $found['summary']);
        $this->assertStringContainsString('world', (string) $found['summary']);
    }

    /**
     * get_learningpaths returns the edit/view structure for a manager (admin),
     * including the created path in the edit bucket - no exception thrown.
     *
     * @return void
     */
    public function test_get_learningpaths_returns_for_admin(): void {
        $this->setAdminUser();
        $lpid = $this->make_lp('Admin visible LP', 2);

        $ctxid = context_system::instance()->id;
        $result = get_learningpaths::execute(2, 0, $ctxid);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('edit', $result);
        $ids = array_map(fn($lp) => (int) $lp['id'], $result['edit']);
        $this->assertContains($lpid, $ids, 'Admin must see the created path in the edit bucket.');
    }

    /**
     * A plain user with no editor access is cleanly denied listing paths (#458).
     *
     * @return void
     */
    public function test_get_learningpaths_denies_plain_user(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $ctxid = context_system::instance()->id;

        $this->expectException(\required_capability_exception::class);
        get_learningpaths::execute(2, 0, $ctxid);
    }

    /**
     * get_learningpath returns the requested path (with its json) for an admin.
     *
     * @return void
     */
    public function test_get_learningpath_returns_path(): void {
        $this->setAdminUser();
        $lpid = $this->make_lp('Fetch me', 2);

        $ctxid = context_system::instance()->id;
        $result = get_learningpath::execute(2, $lpid, $ctxid);

        $this->assertIsArray($result);
        $this->assertSame($lpid, (int) $result['id']);
        $this->assertSame('Fetch me', $result['name']);
        // Json is present and decodes back to the stored tree.
        $decoded = json_decode($result['json'], true);
        $this->assertArrayHasKey('tree', $decoded);
    }

    /**
     * Requesting a non-existent (e.g. deleted) master path returns the
     * "not found" signalling structure instead of throwing (#446).
     *
     * @return void
     */
    public function test_get_learningpath_signals_deleted_path(): void {
        $this->setAdminUser();
        $ctxid = context_system::instance()->id;

        // An id that does not exist - simulates a deleted master path.
        $result = get_learningpath::execute(2, 999999, $ctxid);

        $this->assertIsArray($result);
        $this->assertSame(999999, (int) $result['id']);
        $this->assertSame(get_string('not_found', 'local_adele'), $result['name']);
        $this->assertSame('', $result['json'], 'json must be empty for a deleted/missing path.');
    }
}
