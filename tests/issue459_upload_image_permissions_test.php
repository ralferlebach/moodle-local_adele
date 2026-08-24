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
 * Uploading a learning-path default image must work for everyone who may edit
 * the path - not only for Adele Managers.
 *
 * GitHub #459 (Azadeh's retest): an Adele Assistant or a per-path editor got
 * 'Sie haben aktuell nicht das Recht, dies zu tun' when uploading their own
 * default image, because upload_lp_image hard-required local/adele:canmanage.
 * The gate is now the same per-path editor check the other mutating services
 * use (#458): managers/admins always, editors for THEIR paths, any
 * editor/assistant for a still-unsaved path (id 0, where the image picker
 * first appears) - and nobody else (no IDOR via foreign path ids).
 *
 * The external classes require_once() the legacy lib/externallib.php, which
 * demands process isolation under PHPUnit.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The #[...] attributes between the class docblock and the class keyword hide
// the class-level @covers tag from this sniff (same as external_read_services_test).
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing

namespace local_adele;

use advanced_testcase;
use context_system;
use local_adele\external\set_new_image;
use required_capability_exception;

/**
 * Editor-level permission for the LP image upload (#459).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @covers \local_adele\external\set_new_image
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class issue459_upload_image_permissions_test extends advanced_testcase {
    /**
     * A valid 1x1 transparent PNG - asset_handler validates the payload is a
     * real image before storing it.
     */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /**
     * Insert a learning-path row and return its id.
     *
     * @param int $creatorid
     * @return int
     */
    private function make_lp(int $creatorid): int {
        global $DB;
        $json = json_encode(['tree' => ['nodes' => [], 'edges' => []], 'modules' => null]);
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'LP 459',
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
     * A user holding the system Adele Assistant capability.
     *
     * @return \stdClass
     */
    private function make_assistant(): \stdClass {
        $user = self::getDataGenerator()->create_user();
        $roleid = create_role('Adele Assistant', 'adeleassistant459', '');
        assign_capability('local/adele:assist', CAP_ALLOW, $roleid, context_system::instance());
        role_assign($roleid, $user->id, context_system::instance());
        return $user;
    }

    /**
     * A user who is a per-path editor (lp_editors row) without any system role.
     *
     * @param int $lpid
     * @return \stdClass
     */
    private function make_editor(int $lpid): \stdClass {
        global $DB;
        $user = self::getDataGenerator()->create_user();
        $DB->insert_record('local_adele_lp_editors', (object) [
            'learningpathid' => $lpid,
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        return $user;
    }

    /**
     * Upload the test image for a path as the given user.
     *
     * @param \stdClass $caller
     * @param int $lpid
     * @return array
     */
    private function upload(\stdClass $caller, int $lpid): array {
        $this->setUser($caller);
        return set_new_image::execute(context_system::instance()->id, $lpid, self::PNG);
    }

    /**
     * Azadeh's retest case A: an Adele Assistant picks an own image while
     * building a NEW learning path (id 0) - must succeed.
     *
     * @return void
     */
    public function test_assistant_can_upload_for_a_new_path(): void {
        $this->resetAfterTest(true);
        $result = $this->upload($this->make_assistant(), 0);
        $this->assertSame('success', $result['status']);
    }

    /**
     * Azadeh's retest case B: a per-path editor (Bearbeiter) uploads a default
     * image for a path they edit - must succeed.
     *
     * @return void
     */
    public function test_path_editor_can_upload_for_their_path(): void {
        $this->resetAfterTest(true);
        $lpid = $this->make_lp(2);
        $result = $this->upload($this->make_editor($lpid), $lpid);
        $this->assertSame('success', $result['status']);
    }

    /**
     * Being an editor of SOME path grants nothing on OTHER paths (the #458
     * IDOR rule holds for uploads too).
     *
     * @return void
     */
    public function test_editor_of_another_path_stays_blocked(): void {
        $this->resetAfterTest(true);
        $ownlpid = $this->make_lp(2);
        $foreignlpid = $this->make_lp(2);
        $this->expectException(required_capability_exception::class);
        $this->upload($this->make_editor($ownlpid), $foreignlpid);
    }

    /**
     * A user without any adele role or membership stays blocked.
     *
     * @return void
     */
    public function test_plain_user_stays_blocked(): void {
        $this->resetAfterTest(true);
        $lpid = $this->make_lp(2);
        $this->expectException(required_capability_exception::class);
        $this->upload(self::getDataGenerator()->create_user(), $lpid);
    }

    /**
     * Managers/admins keep the upload right (regression guard).
     *
     * @return void
     */
    public function test_manager_keeps_upload(): void {
        $this->resetAfterTest(true);
        $lpid = $this->make_lp(2);
        $this->setAdminUser();
        $result = set_new_image::execute(context_system::instance()->id, $lpid, self::PNG);
        $this->assertSame('success', $result['status']);
    }
}
