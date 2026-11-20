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

/**
 * Issue #18: the navbar link carries no generic DOM id.
 *
 * The link is on every page of a manager. Its id "dropdownMenuButton" was set
 * by local_catquiz's navbar link as well (removed there in 2026101701), so a
 * page with both plugins had the id twice. The link opens no menu either.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::local_adele_render_navbar_output
 */
final class navbar_output_test extends \advanced_testcase {
    /**
     * The manager's link: there, pointing at the overview, without an id or a menu role.
     */
    public function test_manager_link_has_no_id(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/adele/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = local_adele_render_navbar_output($PAGE->get_renderer('core'));

        $this->assertStringContainsString('/local/adele/index.php#/learningpaths', $html);
        $this->assertStringContainsString(get_string('btnadele', 'local_adele'), $html);
        $this->assertDoesNotMatchRegularExpression('/\sid\s*=/', $html);
        $this->assertStringNotContainsString('dropdownMenuButton', $html);
        $this->assertStringNotContainsString('aria-haspopup', $html);
    }

    /**
     * Nobody without a path to edit gets a link, guests neither.
     */
    public function test_no_link_without_paths(): void {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/local/adele/lib.php');
        $this->resetAfterTest();

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame('', local_adele_render_navbar_output($PAGE->get_renderer('core')));

        $this->setGuestUser();
        $this->assertSame('', local_adele_render_navbar_output($PAGE->get_renderer('core')));
    }
}
