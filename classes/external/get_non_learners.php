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
 * The people of the course who are no learners of the path (#13).
 *
 * Teachers only, loaded only when they ask for it.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_non_learners extends external_api {
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
     * The non-learners of the group shown.
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
        return \local_adele\group_progress::non_learners($contract);
    }

    /**
     * Result.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Person'),
            'fullname' => new external_value(PARAM_TEXT, 'Name'),
            'reason' => new external_value(PARAM_ALPHA, 'nonlearner: takes no part by role; nopath: no learning path'),
            'roles' => new external_value(PARAM_TEXT, 'Roles in the course'),
        ]));
    }
}
