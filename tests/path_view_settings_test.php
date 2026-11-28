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

/**
 * Refresh and auto-focus settings of the open learning path view
 * (mod_adele #26, #27).
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\path_view_settings
 * @covers     \local_adele\admin_setting_bounded_int
 */
final class path_view_settings_test extends advanced_testcase {
    /**
     * Defaults on a new site: mode 2, 3000 ms, 300 ms, auto-focus on,
     * teachers 1, learners 2 - as installed by the settings page.
     *
     * @return void
     */
    public function test_defaults(): void {
        $this->resetAfterTest();
        $expected = [
            'pollingmode' => 2, 'pollinginterval' => 3000, 'pollingdebounce' => 300,
            'autofocusenabled' => 1, 'autofocusteachermode' => 1, 'autofocuslearnermode' => 2,
        ];
        foreach (
            ['pathpollingmode', 'pathpollinginterval', 'pathpollingdebounce', 'pathautofocusenabled',
            'pathautofocusteachermode', 'pathautofocuslearnermode'] as $name
        ) {
            unset_config($name, 'local_adele');
        }
        $this->assertSame($expected, path_view_settings::get(), 'Nothing stored.');

        // What the settings page installs as defaults.
        global $CFG;
        $this->setAdminUser();
        require_once($CFG->libdir . '/adminlib.php');
        $root = admin_get_root(true, true);
        $page = $root->locate('local_adele_settings');
        $this->assertNotNull($page);
        $defaults = [];
        foreach ($page->settings as $setting) {
            $defaults[$setting->name] = $setting->get_defaultsetting();
        }
        $this->assertEquals(2, $defaults['pathpollingmode']);
        $this->assertEquals(3000, $defaults['pathpollinginterval']);
        $this->assertEquals(300, $defaults['pathpollingdebounce']);
        $this->assertEquals(1, $defaults['pathautofocusenabled']);
        $this->assertEquals(1, $defaults['pathautofocusteachermode']);
        $this->assertEquals(2, $defaults['pathautofocuslearnermode']);
        $modes = array_values(array_filter((array) $page->settings, fn($x) => $x->name === 'pathpollingmode'))[0];
        $this->assertSame([0, 1, 2, 3], array_keys($modes->choices), 'Exactly modes 0-3.');
    }

    /**
     * Stored values are taken; invalid stored values count as the default.
     *
     * @return void
     */
    public function test_stored_values_and_invalid_ones(): void {
        $this->resetAfterTest();
        foreach (
            ['pathpollingmode' => 3, 'pathpollinginterval' => 1000, 'pathpollingdebounce' => 10000,
            'pathautofocusenabled' => 0, 'pathautofocusteachermode' => 2, 'pathautofocuslearnermode' => 0] as $name => $v
        ) {
            set_config($name, $v, 'local_adele');
        }
        $this->assertSame([
            'pollingmode' => 3, 'pollinginterval' => 1000, 'pollingdebounce' => 10000,
            'autofocusenabled' => 0, 'autofocusteachermode' => 2, 'autofocuslearnermode' => 0,
        ], path_view_settings::get());
        $this->assertSame(path_view_settings::get(), json_decode(path_view_settings::for_template(), true));

        foreach (
            ['pathpollingmode' => 4, 'pathpollinginterval' => 999, 'pathpollingdebounce' => 'x',
            'pathautofocusenabled' => 2, 'pathautofocusteachermode' => -1, 'pathautofocuslearnermode' => '1.5'] as $name => $v
        ) {
            set_config($name, $v, 'local_adele');
        }
        $this->assertSame([
            'pollingmode' => 2, 'pollinginterval' => 3000, 'pollingdebounce' => 300,
            'autofocusenabled' => 1, 'autofocusteachermode' => 1, 'autofocuslearnermode' => 2,
        ], path_view_settings::get());
    }

    /**
     * The numbers are validated on the settings page: whole numbers within
     * their bounds, the bounds included.
     *
     * @return void
     */
    public function test_bounded_numbers_are_validated(): void {
        $setting = new admin_setting_bounded_int('local_adele/pathpollinginterval', 'x', 'y', 3000, 1000, 300000);
        $this->assertTrue($setting->validate('1000'));
        $this->assertTrue($setting->validate('300000'));
        foreach (['999', '300001', '-5', '1.5', 'abc', ''] as $bad) {
            $this->assertIsString($setting->validate($bad), $bad);
        }
        $setting = new admin_setting_bounded_int('local_adele/pathpollingdebounce', 'x', 'y', 300, 0, 10000);
        $this->assertTrue($setting->validate('0'));
        $this->assertIsString($setting->validate('10001'));
    }

    /**
     * The page carries the settings: the view of the activity renders them
     * into the app element.
     *
     * @return void
     */
    public function test_the_template_carries_the_settings(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        set_config('pathpollingmode', 1, 'local_adele');
        $html = $OUTPUT->render_from_template('local_adele/initview', [
            'userid' => 2, 'learningpath' => 1, 'pathviewsettings' => path_view_settings::for_template(),
        ]);
        $this->assertMatchesRegularExpression('/pathviewsettings="([^"]+)"/', $html);
        preg_match('/pathviewsettings="([^"]+)"/', $html, $m);
        $this->assertSame(1, json_decode(html_entity_decode($m[1]), true)['pollingmode']);
    }
}
