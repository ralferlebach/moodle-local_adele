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
 * Group rankings and averages of an activity (#12).
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_group_overview extends external_api {
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
     * What the person may see of the groups of the activity.
     *
     * @param int $contextid
     * @param int $groupid
     * @return array
     */
    public static function execute(int $contextid, int $groupid = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid, 'groupid' => $groupid]);
        self::validate_context(\context::instance_by_id($params['contextid']));
        $contract = cohort::contract($params['contextid'], $params['groupid']);
        cohort::require_view($contract, false);
        return \local_adele\group_progress::overview($contract);
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'groupmode' => new external_value(PARAM_INT, 'Effective group mode: 0 none, 1 separate, 2 visible'),
            'groupid' => new external_value(PARAM_INT, 'The group shown: 0 all, -1 none'),
            'allowall' => new external_value(PARAM_BOOL, 'A teacher may choose all participants'),
            'closed' => new external_value(PARAM_BOOL, 'A learner with "only own results": no rankings'),
            'groups' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group id'),
                'name' => new external_value(PARAM_TEXT, 'Group name'),
            ]), 'The groups a teacher may choose'),
            'rankings' => new external_multiple_structure(new external_single_structure([
                'groupid' => new external_value(PARAM_INT, 'Own group'),
                'groupname' => new external_value(PARAM_TEXT, 'Its name'),
                'members' => new external_multiple_structure(new external_single_structure([
                    'rank' => new external_value(PARAM_INT, 'Rank'),
                    'fullname' => new external_value(PARAM_TEXT, 'Name'),
                    'progress' => new external_value(PARAM_FLOAT, 'Progress in percent'),
                    'self' => new external_value(PARAM_BOOL, 'The person asking'),
                ])),
            ]), 'Learners: a ranking per own group'),
            'averages' => new external_multiple_structure(new external_single_structure([
                'groupid' => new external_value(PARAM_INT, 'Group'),
                'groupname' => new external_value(PARAM_TEXT, 'Its name'),
                'own' => new external_value(PARAM_BOOL, 'A group of the person asking'),
                'value' => new external_value(
                    PARAM_FLOAT,
                    'Average progress in percent; null: no data',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
                'measured' => new external_value(PARAM_INT, 'Learners with a path'),
                'members' => new external_value(PARAM_INT, 'Learners of the group'),
            ]), 'Visible groups: averages'),
        ]);
    }
}
