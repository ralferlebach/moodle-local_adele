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
use context_system;

/**
 * Overview scoping + ownership for the learning-path tiles (#471 / #472 / #487).
 *
 * - scope_paths_for_user(): an Adele Assistant sees every visible path plus their own
 *   (even if hidden), split into editable / view-only; others see only editable paths;
 *   managers see everything (#472).
 * - require_lp_owner_access(): duplicating is reserved for the owner or a manager (#471).
 * - add_path_people(): each tile carries its owner (name/email) and editors (#487).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_adele\learning_paths::scope_paths_for_user
 * @covers \local_adele\learning_paths::require_lp_owner_access
 * @covers \local_adele\learning_paths::add_path_people
 */
final class learning_paths_scope_ownership_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Insert a learning path.
     *
     * @param string $name
     * @param int $createdby
     * @param int $visibility 1 = visible, 0 = hidden.
     * @return int learning path id
     */
    private function make_lp(string $name, int $createdby, int $visibility): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => $name,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
            'createdby' => $createdby,
            'visibility' => $visibility,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Add an lp_editors row.
     *
     * @param int $lpid
     * @param int $userid
     */
    private function add_editor(int $lpid, int $userid): void {
        global $DB;
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $userid]);
    }

    /**
     * Collect the ids present under a scope key ('edit' or 'view').
     *
     * @param array $scoped
     * @param string $key
     * @return int[]
     */
    private function ids(array $scoped, string $key): array {
        return array_map(fn($p) => (int) $p['id'], array_values($scoped[$key]));
    }

    /**
     * An assistant sees every visible path plus their own hidden ones; editable ones
     * (lp_editors membership) go to 'edit', purely-visible ones to 'view'; a hidden
     * path they neither own nor... simply do not own is not shown at all (#472).
     */
    public function test_assistant_sees_all_visible_plus_own(): void {
        $assistant = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;

        $ownvisible   = $this->make_lp('own visible', $assistant, 1);
        $ownhidden    = $this->make_lp('own hidden', $assistant, 0);
        $othervisible = $this->make_lp('other visible', $other, 1);
        $otherhidden  = $this->make_lp('other hidden', $other, 0);
        $editorvisible = $this->make_lp('editor visible', $other, 1);

        // The assistant is an editor of their own paths and of one of the other user's.
        $this->add_editor($ownvisible, $assistant);
        $this->add_editor($ownhidden, $assistant);
        $this->add_editor($editorvisible, $assistant);
        // Ownership rows (the creator is also an editor - what return_learningpaths_owned expects).
        $this->add_editor($othervisible, $other);
        $this->add_editor($otherhidden, $other);

        $allpaths = learning_paths::get_learning_paths(true, []);
        // Editor membership and created-by keys for the assistant.
        $editablekeys = [$ownvisible, $ownhidden, $editorvisible];
        $ownedkeys = [$ownvisible, $ownhidden];

        $scoped = learning_paths::scope_paths_for_user($allpaths, $editablekeys, $ownedkeys, true, false);

        $editids = $this->ids($scoped, 'edit');
        $viewids = $this->ids($scoped, 'view');

        // Editable (own + editor) paths land in edit.
        $this->assertEqualsCanonicalizing([$ownvisible, $ownhidden, $editorvisible], $editids);
        // A visible path they do not edit is view-only.
        $this->assertSame([$othervisible], $viewids);
        // A hidden path they did not create is not shown anywhere.
        $this->assertNotContains($otherhidden, array_merge($editids, $viewids));

        // The isowner flag must reflect creator-ship, not mere editor membership.
        $byid = [];
        foreach (array_merge($scoped['edit'], $scoped['view']) as $p) {
            $byid[(int) $p['id']] = $p['isowner'];
        }
        $this->assertSame('true', $byid[$ownvisible]);
        $this->assertSame('true', $byid[$ownhidden]);
        $this->assertSame('false', $byid[$othervisible]);
        $this->assertSame('false', $byid[$editorvisible]);
    }

    /**
     * A non-assistant (e.g. course teacher) still sees ONLY the paths they may edit -
     * #472 broadens the assistant only.
     */
    public function test_non_assistant_sees_only_editable(): void {
        $teacher = (int) $this->getDataGenerator()->create_user()->id;
        $other = (int) $this->getDataGenerator()->create_user()->id;

        $mine = $this->make_lp('mine', $teacher, 1);
        $this->make_lp('other visible', $other, 1);
        $this->make_lp('other hidden', $other, 0);
        $this->add_editor($mine, $teacher);

        $allpaths = learning_paths::get_learning_paths(true, []);
        $scoped = learning_paths::scope_paths_for_user($allpaths, [$mine], [$mine], false, false);

        $this->assertSame([$mine], $this->ids($scoped, 'edit'));
        $this->assertSame([], $this->ids($scoped, 'view'));
    }

    /**
     * A manager/admin (privileged) sees every path, all flagged as owner.
     */
    public function test_manager_sees_everything(): void {
        $a = (int) $this->getDataGenerator()->create_user()->id;
        $this->make_lp('a visible', $a, 1);
        $this->make_lp('a hidden', $a, 0);

        $allpaths = learning_paths::get_learning_paths(true, []);
        $scoped = learning_paths::scope_paths_for_user($allpaths, [], [], false, true);

        $this->assertCount(2, $scoped['edit']);
        foreach ($scoped['edit'] as $p) {
            $this->assertSame('true', $p['isowner']);
        }
    }

    /**
     * require_lp_owner_access: the creator passes, an editor-but-not-creator is denied,
     * and a manager (canmanage) passes regardless of ownership (#471).
     */
    public function test_require_lp_owner_access(): void {
        $owner = $this->getDataGenerator()->create_user();
        $editor = $this->getDataGenerator()->create_user();
        $syscontext = context_system::instance();
        $lpid = $this->make_lp('owned', (int) $owner->id, 1);

        // Owner passes.
        $this->setUser($owner);
        learning_paths::require_lp_owner_access($lpid, $syscontext);
        $this->assertTrue(true, 'The creator must pass require_lp_owner_access.');

        // Editor-but-not-creator is denied.
        $this->setUser($editor);
        try {
            learning_paths::require_lp_owner_access($lpid, $syscontext);
            $this->fail('A non-creator must be denied duplicate/ownership access.');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(true);
        }

        // A manager (canmanage) passes.
        $manager = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/adele:canmanage', CAP_ALLOW, $roleid, $syscontext->id);
        role_assign($roleid, $manager->id, $syscontext->id);
        $this->setUser($manager);
        learning_paths::require_lp_owner_access($lpid, $syscontext);
        $this->assertTrue(true, 'A manager must pass require_lp_owner_access.');
    }

    /**
     * add_path_people annotates each tile with the owner (name + email) and the list
     * of editor names, and drops the internal createdby (#487).
     */
    public function test_add_path_people(): void {
        $owner = $this->getDataGenerator()->create_user([
            'firstname' => 'Olive',
            'lastname' => 'Owner',
            'email' => 'olive@example.com',
        ]);
        $editor = $this->getDataGenerator()->create_user(['firstname' => 'Eddie', 'lastname' => 'Editor']);
        $lpid = $this->make_lp('people path', (int) $owner->id, 1);
        $this->add_editor($lpid, (int) $owner->id);
        $this->add_editor($lpid, (int) $editor->id);

        $allpaths = learning_paths::get_learning_paths(true, []);
        learning_paths::add_path_people($allpaths);

        $path = null;
        foreach ($allpaths['edit'] as $p) {
            if ((int) $p['id'] === $lpid) {
                $path = $p;
            }
        }
        $this->assertNotNull($path);
        $this->assertSame('Olive Owner', $path['owner']['name']);
        $this->assertSame('olive@example.com', $path['owner']['email']);
        $this->assertArrayNotHasKey('createdby', $path, 'Internal createdby must be dropped.');
        $editornames = array_map(fn($e) => $e['name'], $path['editors']);
        $this->assertContains('Olive Owner', $editornames);
        $this->assertContains('Eddie Editor', $editornames);
    }
}
