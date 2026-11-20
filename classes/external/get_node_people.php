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
 * The names behind a node counter, a page at a time (#15).
 *
 * Teachers only, loaded only when a counter is opened.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_node_people extends external_api {
    /** @var int Names per page. */
    public const PERPAGE = 20;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The module context of the ADELE activity', VALUE_REQUIRED),
            'groupid' => new external_value(PARAM_INT, 'The group: -1 the default, 0 all participants', VALUE_DEFAULT, -1),
            'nodeid' => new external_value(PARAM_ALPHANUMEXT, 'Node of the path', VALUE_REQUIRED),
            'kind' => new external_value(PARAM_ALPHA, 'active or completed', VALUE_REQUIRED),
            'page' => new external_value(PARAM_INT, 'Page, from 0', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * A page of names.
     *
     * @param int $contextid
     * @param int $groupid
     * @param string $nodeid
     * @param string $kind
     * @param int $page
     * @return array
     */
    public static function execute(int $contextid, int $groupid, string $nodeid, string $kind, int $page = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid, 'groupid' => $groupid, 'nodeid' => $nodeid, 'kind' => $kind, 'page' => $page,
        ]);
        if (!in_array($params['kind'], ['active', 'completed'], true) || $params['page'] < 0) {
            throw new \invalid_parameter_exception('kind is active or completed, page from 0.');
        }
        self::validate_context(\context::instance_by_id($params['contextid']));
        $contract = cohort::contract($params['contextid'], $params['groupid']);
        cohort::require_view($contract, true);
        return \local_adele\live_activity::people($contract, $params['nodeid'], $params['kind'], $params['page'], self::PERPAGE);
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'All names'),
            'page' => new external_value(PARAM_INT, 'This page'),
            'perpage' => new external_value(PARAM_INT, 'Names per page'),
            'people' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Person'),
                'fullname' => new external_value(PARAM_TEXT, 'Name'),
            ])),
        ]);
    }
}
