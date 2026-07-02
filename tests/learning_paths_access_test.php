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
 * Coverage-gap tests for the learning_paths access helpers.
 *
 * check_access(), return_learningpaths() and require_lp_editor_access() are the
 * central gate used by the web services. The IDOR tests (#458) exercise them only
 * indirectly through save/delete/etc.; here they are asserted directly across the
 * relevant roles (plain user, lp-editor, manager, admin).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use advanced_testcase;
use context_system;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::check_access
 * @covers \local_adele\learning_paths::return_learningpaths
 * @covers \local_adele\learning_paths::require_lp_editor_access
 */
final class learning_paths_access_test extends advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Make $user an editor of a freshly created learning path; return the LP id.
     *
     * @param int $userid
     * @return int
     */
    private function make_owned_lp(int $userid): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP for ' . $userid,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
        ]);
        $DB->insert_record('local_adele_lp_editors', (object) [
            'learningpathid' => $lpid,
            'userid' => $userid,
        ]);
        return $lpid;
    }

    // -------------------------------------------------------------------------
    // check_access() / return_learningpaths()

    /**
     * A plain user who is not an editor of anything and has no manage/assist
     * capability has no access.
     *
     * @return void
     */
    public function test_plain_user_has_no_access(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertEmpty(learning_paths::return_learningpaths());
        $this->assertFalse(learning_paths::check_access());
    }

    /**
     * A user listed in lp_editors (an editor of some path) is granted access, and
     * return_learningpaths() lists that path.
     *
     * @return void
     */
    public function test_lp_editor_has_access(): void {
        $editor = $this->getDataGenerator()->create_user();
        $lpid = $this->make_owned_lp((int) $editor->id);
        $this->setUser($editor);

        $records = learning_paths::return_learningpaths();
        $this->assertArrayHasKey($lpid, $records, 'return_learningpaths must list the editor\'s own path.');
        $this->assertTrue(learning_paths::check_access());
    }

    /**
     * A site admin always has access (canmanage), even with no editor rows.
     *
     * @return void
     */
    public function test_admin_has_access(): void {
        $this->setAdminUser();
        $this->assertTrue(learning_paths::check_access());
    }

    /**
     * A user with the canmanage capability (manager archetype at system context)
     * has access without any lp_editors membership.
     *
     * @return void
     */
    public function test_manager_capability_grants_access(): void {
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontext = context_system::instance();
        assign_capability('local/adele:canmanage', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $manager->id, $syscontext->id);

        $this->setUser($manager);
        $this->assertTrue(
            learning_paths::check_access(),
            'A user with local/adele:canmanage must have access without lp_editors membership.'
        );
    }

    // -------------------------------------------------------------------------
    // require_lp_editor_access()

    /**
     * The owner (lp_editors member) may edit their own path - no exception.
     *
     * @return void
     */
    public function test_owner_passes_require_lp_editor_access(): void {
        $owner = $this->getDataGenerator()->create_user();
        $lpid = $this->make_owned_lp((int) $owner->id);
        $this->setUser($owner);

        learning_paths::require_lp_editor_access($lpid, context_system::instance());
        $this->assertTrue(true, 'Owner must pass require_lp_editor_access without throwing.');
    }

    /**
     * A non-owner editor (editor of a DIFFERENT path) is denied editing a path
     * they do not own - the IDOR guard (#458).
     *
     * @return void
     */
    public function test_non_owner_denied_require_lp_editor_access(): void {
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $lpa = $this->make_owned_lp((int) $a->id);
        $this->make_owned_lp((int) $b->id); // B is an editor of something else.
        $this->setUser($b);

        $this->expectException(\required_capability_exception::class);
        learning_paths::require_lp_editor_access($lpa, context_system::instance());
    }

    /**
     * A manager/admin bypasses per-path ownership (full access).
     *
     * @return void
     */
    public function test_admin_bypasses_require_lp_editor_access(): void {
        $owner = $this->getDataGenerator()->create_user();
        $lpid = $this->make_owned_lp((int) $owner->id);
        $this->setAdminUser();

        learning_paths::require_lp_editor_access($lpid, context_system::instance());
        $this->assertTrue(true, 'Admin must bypass per-path ownership.');
    }

    /**
     * A brand-new, unsaved path (id 0) requires only general editor access, not
     * ownership - so any editor may work with it (#458 regression guard).
     *
     * @return void
     */
    public function test_new_unsaved_path_allowed_for_editor(): void {
        $editor = $this->getDataGenerator()->create_user();
        $this->make_owned_lp((int) $editor->id); // Editor of something -> passes check_access.
        $this->setUser($editor);

        learning_paths::require_lp_editor_access(0, context_system::instance());
        $this->assertTrue(true, 'An unsaved path (id 0) must be workable by any editor.');
    }

    /**
     * A plain user (not an editor of anything) cannot even work with a new,
     * unsaved path - check_access() is empty for them.
     *
     * @return void
     */
    public function test_new_unsaved_path_denied_for_plain_user(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        learning_paths::require_lp_editor_access(0, context_system::instance());
    }
}
