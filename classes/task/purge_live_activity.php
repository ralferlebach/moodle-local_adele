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

namespace local_adele\task;

use local_adele\live_activity;

/**
 * Removes records of live activity older than the retention (#14).
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_live_activity extends \core\task\scheduled_task {
    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_live_activity', 'local_adele');
    }

    /**
     * Run the purge.
     *
     * @return void
     */
    public function execute(): void {
        mtrace('local_adele live activity: ' . live_activity::purge() . ' record(s) older than the retention removed.');
    }
}
