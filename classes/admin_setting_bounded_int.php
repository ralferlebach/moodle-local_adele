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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * A whole number setting with a lower and an upper bound (mod_adele #26).
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_bounded_int extends \admin_setting_configtext {
    /** @var int The smallest value allowed. */
    private int $min;

    /** @var int The largest value allowed. */
    private int $max;

    /**
     * Constructor.
     *
     * @param string $name Setting name (component/name).
     * @param string $visiblename Label.
     * @param string $description Description.
     * @param int $default Default value.
     * @param int $min Smallest value allowed.
     * @param int $max Largest value allowed.
     */
    public function __construct(string $name, string $visiblename, string $description, int $default, int $min, int $max) {
        $this->min = $min;
        $this->max = $max;
        parent::__construct($name, $visiblename, $description, (string) $default, PARAM_RAW_TRIMMED, 8);
    }

    /**
     * A whole number within the bounds, or the reason why not.
     *
     * @param string $data The submitted value.
     * @return true|string
     */
    public function validate($data) {
        if (!preg_match('/^\d+$/', (string) $data)) {
            return get_string('setting_bounded_int_invalid', 'local_adele', (object) ['min' => $this->min, 'max' => $this->max]);
        }
        $value = (int) $data;
        if ($value < $this->min || $value > $this->max) {
            return get_string('setting_bounded_int_invalid', 'local_adele', (object) ['min' => $this->min, 'max' => $this->max]);
        }
        return true;
    }
}
