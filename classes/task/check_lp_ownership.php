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
 * Daily safety net for orphaned learning-path owners (#571).
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use local_adele\ownership;

/**
 * Passes paths of vanished owners to the next editor; leftovers surface as
 * "ownerless" in the tile listing. The primary trigger is the user_deleted
 * event observer; this sweep catches owners removed without an event
 * (external user sync, missed event).
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_lp_ownership extends \core\task\scheduled_task {
    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_lp_ownership', 'local_adele');
    }

    /**
     * Run the ownership sweep.
     *
     * @return void
     */
    public function execute(): void {
        $result = ownership::sweep();
        mtrace(sprintf(
            'local_adele ownership sweep: %d path(s) passed to a successor, %d left ownerless.',
            $result['reassigned'],
            $result['ownerless']
        ));
    }
}
