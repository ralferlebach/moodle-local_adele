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
use local_adele\external\get_restrictions;
use local_adele\external\get_completions;

/**
 * A user added as an editor of a single learning path (a local_adele_lp_editors row) must
 * be able to edit that path's restrictions and completions.
 *
 * The restriction/completion editor panels (RestrictionFlow.vue / CompletionFlow.vue) load
 * their palette via get_restrictions / get_completions, which gated only on canmanage or
 * teacheredit - so a per-LP editor who is neither a manager nor a course teacher could open
 * and even save the path, but the palette load threw and they could not change restrictions.
 *
 * @package    local_adele
 * @copyright  2026 cbadusch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class per_lp_editor_palette_access_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a user who is an editor of one learning path they did not create, holding no
     * capability (not canmanage, not a course teacher).
     *
     * @return \stdClass the editor user
     */
    private function make_single_lp_editor(): \stdClass {
        global $DB;
        $creator = $this->getDataGenerator()->create_user();
        $editor = $this->getDataGenerator()->create_user();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Shared path',
            'description' => '',
            'json' => json_encode(['tree' => ['nodes' => []]]),
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $creator->id,
            'visibility' => 1,
        ]);
        $DB->insert_record('local_adele_lp_editors', (object) ['learningpathid' => $lpid, 'userid' => $editor->id]);
        return $editor;
    }

    /**
     * A per-LP editor can load the restriction palette to edit that path's restrictions.
     *
     * @covers \local_adele\external\get_restrictions::execute
     */
    public function test_per_lp_editor_can_load_restriction_palette(): void {
        $editor = $this->make_single_lp_editor();
        $this->setUser($editor);
        $result = get_restrictions::execute(context_system::instance()->id);
        $this->assertIsArray($result);
    }

    /**
     * A per-LP editor can load the completion palette to edit that path's completions.
     *
     * @covers \local_adele\external\get_completions::execute
     */
    public function test_per_lp_editor_can_load_completion_palette(): void {
        $editor = $this->make_single_lp_editor();
        $this->setUser($editor);
        $result = get_completions::execute(context_system::instance()->id);
        $this->assertIsArray($result);
    }

    /**
     * A plain user who edits no path is still denied the palette (relaxation is limited to editors).
     *
     * @covers \local_adele\external\get_restrictions::execute
     */
    public function test_plain_user_denied_restriction_palette(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\required_capability_exception::class);
        get_restrictions::execute(context_system::instance()->id);
    }
}
