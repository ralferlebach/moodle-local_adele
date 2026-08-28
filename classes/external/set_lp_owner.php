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
 * This class contains a list of webservice functions related to the adele Module by Wunderbyte.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele\external;

use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_adele\ownership;

/**
 * Transfer the ownership of a learning path (#488). Adele-Manager-only.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_lp_owner extends external_api {
    /**
     * Describes the parameters for set_lp_owner webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            'lpid' => new external_value(PARAM_INT, 'learning path id', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, 'new owner user id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Hand the learning path to a new owner.
     *
     * @param int $contextid
     * @param int $lpid
     * @param int $userid
     * @return array
     */
    public static function execute($contextid, $lpid, $userid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'lpid' => $lpid,
            'userid' => $userid,
        ]);

        require_login();
        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        // Changing who OWNS a path is reserved for the Adele Manager (site
        // admins pass implicitly) - deliberately not for editors or the
        // assistant role (#488).
        require_capability('local/adele:canmanage', context_system::instance());

        $newowner = $DB->get_record('user', ['id' => $params['userid']]);
        if (ownership::is_vanished($newowner)) {
            throw new invalid_parameter_exception('The designated owner does not exist or is deleted.');
        }
        ownership::set_owner($params['lpid'], $params['userid']);
        return ['success' => true];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'ownership transferred'),
        ]);
    }
}
