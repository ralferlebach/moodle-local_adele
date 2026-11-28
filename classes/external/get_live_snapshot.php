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

declare(strict_types=1);

namespace local_adele\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_adele\cohort;

/**
 * Live markers and node counters of an activity (#14, #15).
 *
 * Teachers only. Reads; creates no activity.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_live_snapshot extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The module context of the ADELE activity', VALUE_REQUIRED),
            'groupid' => new external_value(PARAM_INT, 'The group: -1 the default, 0 all participants', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * The snapshot for the group shown.
     *
     * @param int $contextid
     * @param int $groupid
     * @return array
     */
    public static function execute(int $contextid, int $groupid = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid, 'groupid' => $groupid]);
        self::validate_context(\context::instance_by_id($params['contextid']));
        $contract = cohort::contract($params['contextid'], $params['groupid']);
        cohort::require_view($contract, true);
        return \local_adele\live_activity::snapshot($contract);
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Live markers switched on in the activity'),
            'window' => new external_value(PARAM_INT, 'Activity window in seconds'),
            'pollinterval' => new external_value(PARAM_INT, 'Seconds between two snapshots'),
            'servertime' => new external_value(PARAM_INT, 'Time of the snapshot'),
            'nodes' => new external_multiple_structure(new external_single_structure([
                'nodeid' => new external_value(PARAM_TEXT, 'Node'),
                'completed' => new external_value(PARAM_INT, 'Learners who completed it'),
                'active' => new external_value(
                    PARAM_INT,
                    'Learners active on it; null when switched off',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
                'markers' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Person'),
                    'fullname' => new external_value(PARAM_TEXT, 'Name'),
                    'avatar' => new external_value(PARAM_URL, 'Picture'),
                    'state' => new external_value(PARAM_ALPHA, 'active or inactive'),
                    'lastseen' => new external_value(PARAM_INT, 'Last activity seen'),
                    'ambiguous' => new external_value(
                        PARAM_INT,
                        'Inactive: the number of nodes the last activity is shown on when it is not one node, else 0'
                    ),
                ])),
                'moremarkers' => new external_value(PARAM_INT, 'Markers not listed'),
            ])),
        ]);
    }
}
