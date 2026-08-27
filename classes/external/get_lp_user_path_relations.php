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
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace local_adele\external;

use context;
use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use local_adele\learning_paths;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_lp_user_path_relations extends external_api {
    /**
     * Describes the parameters for get_next_question webservice.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'userid'  => new external_value(PARAM_INT, 'userid', VALUE_REQUIRED),
            'learningpathid'  => new external_value(PARAM_INT, 'learningpathid', VALUE_REQUIRED),
            'contextid'  => new external_value(PARAM_INT, 'contextid', VALUE_REQUIRED),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $learningpathid
     * @param int $contextid
     * @return array
     */
    public static function execute($userid, $learningpathid, $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learningpathid' => $learningpathid,
            'contextid' => $contextid,
        ]);
        global $USER;
        require_login();

        $context = context::instance_by_id($contextid);
        // See the identical comment in
        // get_lp_user_path_relation.php (singular) — same reasoning.
        require_capability('local/adele:view', $context);

        if ($context->contextlevel == CONTEXT_COURSE) {
            $params['courseid'] = $context->instanceid;
        } else {
            $coursecontext = $context->get_course_context();
            $params['courseid'] = $coursecontext->instanceid;
        }

        // Local/adele:view is held by every authenticated user, so it is not a
        // boundary. This leaderboard returns the whole participant roster (names +
        // progress) for the path/course, so restrict it to course teachers / managers /
        // path editors, or a user actually enrolled in the course it belongs to. The
        // leaderboard is shown to participants (StudentView.vue) and teachers
        // (TeacherView.vue), so enrolled participants must still pass; this only blocks
        // enumeration of unrelated courses/paths.
        $coursecontext = \context_course::instance($params['courseid']);
        $isprivileged = has_capability('local/adele:teacheredit', $coursecontext)
            || learning_paths::check_access();
        if (!$isprivileged && !is_enrolled($coursecontext, $USER->id)) {
            throw new \required_capability_exception(
                $coursecontext,
                'local/adele:teacheredit',
                'nopermissions',
                ''
            );
        }

        $relations = learning_paths::get_learning_user_relations($params);

        // The mod_adele setting 'Participant results' = 'Only own results' must
        // hold HERE, not only in the client: the UserList.vue filter still
        // shipped every participant's name, progress and rank to any enrolled
        // student's browser (#569). Teachers and path editors keep the full
        // roster; filtering after ranking keeps the caller's true rank.
        if (!$isprivileged && isset($relations[0]) && self::only_own_results($context, $params)) {
            $ownid = (int) $USER->id;
            $relations = array_values(array_filter(
                $relations,
                static function ($relation) use ($ownid) {
                    return (int) $relation['id'] === $ownid;
                }
            ));
        }

        return $relations;
    }

    /**
     * Whether the calling context demands the 'Only own results' filter (#569).
     *
     * Called from a mod_adele activity (module context) the instance's own
     * userlist setting decides; for course-level calls the strictest embedding
     * of this path in the course wins.
     *
     * @param context $context The context the SPA was booted with.
     * @param array $params Validated parameters (learningpathid, courseid).
     * @return bool
     */
    protected static function only_own_results(context $context, array $params): bool {
        global $DB;
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('adele', $context->instanceid);
            if ($cm) {
                $userlist = $DB->get_field('adele', 'userlist', ['id' => $cm->instance]);
                if ($userlist !== false) {
                    return (int) $userlist == 2;
                }
            }
        }
        return $DB->record_exists('adele', [
            'course' => $params['courseid'],
            'learningpathid' => $params['learningpathid'],
            'userlist' => 2,
        ]);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Item id'),
                    'username' => new external_value(PARAM_TEXT, 'Username'),
                    'firstname' => new external_value(PARAM_TEXT, 'Firstname'),
                    'lastname' => new external_value(PARAM_TEXT, 'Lastname'),
                    'progress' => new external_single_structure([
                        'completed_nodes' => new external_value(PARAM_TEXT, 'completed nodes'),
                        'progress' => new external_value(PARAM_FLOAT, 'progress'),
                        'route_completed' => new external_value(PARAM_INT, 'completed nodes on the best route'),
                        'route_total' => new external_value(PARAM_INT, 'length of the best route'),
                        'total_nodes' => new external_value(PARAM_INT, 'total nodes of the path'),
                    ]),
                    'rank' => new external_value(PARAM_INT, 'Ranking'),
                ])
        );
    }
}
