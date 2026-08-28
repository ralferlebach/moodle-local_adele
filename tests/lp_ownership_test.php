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
 * Learning-path ownership: manual transfer by the Adele Manager (#488) and
 * automatic succession when the owner vanishes (#571).
 *
 * Owner = learning_paths.createdby. Before these tickets it was set once at
 * creation and could never change, so a deleted owner permanently blocked the
 * owner-gated actions (visibility toggle, duplicate, delete).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use context_system;
use externallib_advanced_testcase;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Ownership transfer + succession tests (#488 / #571).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\ownership
 * @covers \local_adele\external\set_lp_owner
 */
final class lp_ownership_test extends externallib_advanced_testcase {
    /**
     * Seed a learning path owned by $owner, optionally with named editors.
     *
     * @param \stdClass $owner Owner (createdby) user.
     * @param \stdClass[] $editors Users to add as lp_editors.
     * @return int learning path id
     */
    private function seed_lp(\stdClass $owner, array $editors = []): int {
        global $DB;
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Ownership LP ' . $owner->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $owner->id,
        ]);
        // The creator is an editor of their own path (matches save_learning_path).
        foreach (array_merge([$owner], $editors) as $editor) {
            $DB->insert_record('local_adele_lp_editors', (object) [
                'learningpathid' => $lpid,
                'userid' => $editor->id,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        return $lpid;
    }

    /**
     * The Adele Manager (canmanage / site admin) can transfer ownership; the new
     * owner also becomes an editor of the path (#488).
     *
     * @return void
     */
    public function test_manager_can_transfer_ownership(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $newowner = $gen->create_user();
        $lpid = $this->seed_lp($owner);

        $this->setAdminUser();
        external\set_lp_owner::execute(context_system::instance()->id, $lpid, (int) $newowner->id);

        $this->assertEquals(
            $newowner->id,
            $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $lpid]),
            'The manager must be able to hand the path to a new owner (#488).'
        );
        $this->assertTrue(
            $DB->record_exists('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $newowner->id]),
            'The new owner must also be an editor of the path.'
        );
    }

    /**
     * Transferring a path that has SUBSCRIBED users must neither crash nor kick
     * off a tree resync: ownership is metadata, the tree is untouched. (Live
     * regression: reusing learnpath_updated made the observer chain decode a
     * 'json' payload the transfer never carries -> TypeError on paths with
     * subscribers.)
     *
     * @return void
     */
    public function test_transfer_with_subscribers_does_not_resync(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $newowner = $gen->create_user();
        $student = $gen->create_user();
        $lpid = $this->seed_lp($owner);
        $snapshot = json_encode(['tree' => ['nodes' => [], 'edges' => []], 'user_path_relation' => []]);
        $puid = (int) $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $student->id,
            'course_id' => 0,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'json' => $snapshot,
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $owner->id,
        ]);

        $this->setAdminUser();
        $sink = $this->redirectEvents();
        external\set_lp_owner::execute(context_system::instance()->id, $lpid, (int) $newowner->id);
        $fired = array_map(static fn($e) => $e->eventname, $sink->get_events());
        $sink->close();

        $this->assertEquals(
            $newowner->id,
            $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $lpid])
        );
        $this->assertNotContains(
            '\\local_adele\\event\\learnpath_updated',
            $fired,
            'An ownership transfer must not impersonate a tree update.'
        );
        $this->assertSame(
            $snapshot,
            $DB->get_field('local_adele_path_user', 'json', ['id' => $puid]),
            'Subscriber snapshots must stay untouched by an ownership transfer.'
        );
    }

    /**
     * Ownership transfer is Adele-Manager-only: a plain editor of the path is denied.
     *
     * @return void
     */
    public function test_transfer_requires_manager(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $editor = $gen->create_user();
        $lpid = $this->seed_lp($owner, [$editor]);

        $this->setUser($editor);
        $this->expectException(required_capability_exception::class);
        external\set_lp_owner::execute(context_system::instance()->id, $lpid, (int) $editor->id);
    }

    /**
     * When the owner is DELETED, the most senior editor (lowest user id) succeeds
     * automatically - driven by the real user_deleted event (#571).
     *
     * @return void
     */
    public function test_succession_on_user_deleted_event(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        // Creation order fixes the ids: successor has the LOWER id of the two editors.
        $owner = $gen->create_user();
        $senior = $gen->create_user();
        $junior = $gen->create_user();
        $lpid = $this->seed_lp($owner, [$senior, $junior]);

        delete_user($owner);

        $this->assertEquals(
            $senior->id,
            $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $lpid]),
            'The most senior remaining editor (lowest user id) must succeed the deleted owner (#571).'
        );
    }

    /**
     * A path whose deleted owner has no remaining editors keeps its createdby but is
     * flagged "ownerless" in the tile listing, so Admin/Adele Manager see the warning.
     *
     * @return void
     */
    public function test_no_successor_flags_ownerless(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $lpid = $this->seed_lp($owner); // Only the owner themselves is an editor.

        delete_user($owner);

        $this->assertEquals(
            $owner->id,
            $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $lpid]),
            'Without a successor the createdby must stay untouched.'
        );

        // The tile decoration must expose the orphaned state.
        $paths = ['edit' => [['id' => $lpid, 'createdby' => (int) $owner->id]], 'view' => []];
        learning_paths::add_path_people($paths);
        $this->assertTrue(
            (bool) ($paths['edit'][0]['ownerless'] ?? false),
            'A path with a vanished owner and no successor must be flagged ownerless (#571).'
        );
        $this->assertSame('', $paths['edit'][0]['owner']['name'], 'No stale owner name for a deleted user.');
    }

    /**
     * A living owner must NOT be flagged ownerless.
     *
     * @return void
     */
    public function test_living_owner_not_flagged(): void {
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $lpid = $this->seed_lp($owner);

        $paths = ['edit' => [['id' => $lpid, 'createdby' => (int) $owner->id]], 'view' => []];
        learning_paths::add_path_people($paths);
        $this->assertFalse((bool) ($paths['edit'][0]['ownerless'] ?? false));
    }

    /**
     * The daily task heals paths whose owner vanished WITHOUT the event having been
     * processed (e.g. user marked deleted by external sync) - the safety-net half.
     *
     * @return void
     */
    public function test_daily_task_sweeps_orphaned_paths(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $heir = $gen->create_user();
        $lpid = $this->seed_lp($owner, [$heir]);

        // Vanish silently: no event, exactly what the sweep exists for.
        $DB->set_field('user', 'deleted', 1, ['id' => $owner->id]);

        $task = new task\check_lp_ownership();
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }

        $this->assertEquals(
            $heir->id,
            $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $lpid]),
            'The daily sweep must pass orphaned paths to the next editor (#571).'
        );
    }

    /**
     * Sidequest (#571): removing ANOTHER editor is owner/manager-only; a plain
     * editor may only remove themselves.
     *
     * @return void
     */
    public function test_removing_other_editors_is_owner_or_manager_only(): void {
        global $DB;
        $this->resetAfterTest(true);
        $gen = self::getDataGenerator();
        $owner = $gen->create_user();
        $editora = $gen->create_user();
        $editorb = $gen->create_user();
        $lpid = $this->seed_lp($owner, [$editora, $editorb]);
        $ctxid = context_system::instance()->id;

        // A plain editor may remove THEMSELVES...
        $this->setUser($editora);
        external\remove_lp_edit_users::execute($ctxid, $lpid, (int) $editora->id);
        $this->assertFalse(
            $DB->record_exists('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $editora->id])
        );

        // ...but not another editor.
        $this->setUser($editorb);
        try {
            external\remove_lp_edit_users::execute($ctxid, $lpid, (int) $owner->id);
            $this->fail('A plain editor must not remove other editors (sidequest #571).');
        } catch (required_capability_exception $e) {
            $this->assertTrue(true);
        }

        // The owner may remove another editor.
        $this->setUser($owner);
        external\remove_lp_edit_users::execute($ctxid, $lpid, (int) $editorb->id);
        $this->assertFalse(
            $DB->record_exists('local_adele_lp_editors', ['learningpathid' => $lpid, 'userid' => $editorb->id])
        );
    }
}
