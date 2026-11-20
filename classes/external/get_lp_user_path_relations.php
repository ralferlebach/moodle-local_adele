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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use local_adele\learning_paths;

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
            // The group a teacher chose in an ADELE activity (#12): -1 the
            // activity's default, 0 all participants.
            'groupid'  => new external_value(PARAM_INT, 'groupid', VALUE_DEFAULT, -1),
            ]
        );
    }

    /**
     * Webservice for the local catquiz plugin to get next question.
     *
     * @param int $userid
     * @param int $learningpathid
     * @param int $contextid
     * @param int $groupid
     * @return array
     */
    public static function execute($userid, $learningpathid, $contextid, $groupid = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'learningpathid' => $learningpathid,
            'contextid' => $contextid,
            'groupid' => $groupid,
        ]);
        global $USER;
        require_login();

        $context = context::instance_by_id($contextid);
        // See the identical comment in
        // get_lp_user_path_relation.php (singular) — same reasoning.
        require_capability('local/adele:view', $context);

        // The context is client input. Its course matters only as far as the
        // path is embedded there (see below); a context outside any course
        // (system, user) carries no course rights at all.
        $coursecontext = $context->get_course_context(false);
        $params['courseid'] = $coursecontext ? (int) $coursecontext->instanceid : 0;

        // Local/adele:view is held by every authenticated user, so it is not a
        // boundary. This leaderboard returns the whole participant roster (names +
        // progress) of the path. Who may read it:
        // - site administrators (has_learner_data_authority()). NOT the people
        // who build or manage paths - ADELE managers, the owner and the
        // editors of this path, assistants: building a path gives no view of
        // the learners (#586, addendum);
        // - teachers of a course (local/adele:viewparticipants) and its enrolled
        // participants (StudentView.vue) - but only of a course that EMBEDS
        // this path. The check used to accept any course: a participant or
        // teacher of an unrelated course read the roster of every path by
        // passing the context of their own course, and a learner whose
        // activity says 'Only own results' got the full roster through the
        // context of a node course ADELE itself had enrolled them in.
        $embedded = $coursecontext
            && learning_paths::is_embedded_in_course((int) $params['learningpathid'], (int) $params['courseid']);
        $isprivileged = learning_paths::has_learner_data_authority()
            || ($embedded && learning_paths::can_view_participants($coursecontext));
        if (!$isprivileged && !($embedded && is_enrolled($coursecontext, $USER->id))) {
            throw new \required_capability_exception(
                $coursecontext ?: $context,
                'local/adele:teacheredit',
                'nopermissions',
                ''
            );
        }

        // Asked for through a course that embeds the path, the roster is that
        // course's: its enrolled learners of the path, ranked among themselves
        // (#588). An administrator asking outside such a course gets the path's roster.
        if ($embedded) {
            $params['scopecourseid'] = (int) $params['courseid'];
        }
        $relations = learning_paths::get_learning_user_relations($params);

        // A teacher in an ADELE activity with groups sees the learners of the
        // group chosen (#12, #16): the activity's contract decides which
        // groups may be chosen; a foreign group is refused there.
        if ($isprivileged && isset($relations[0]) && $context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('adele', $context->instanceid);
            if ($cm && (int) $cm->course === (int) $params['courseid']) {
                $contract = \local_adele\cohort::contract((int) $context->id, (int) $params['groupid']);
                if (
                    $contract['view'] === 'teacher' && (int) $contract['learningpathid'] === (int) $params['learningpathid']
                        && (int) $contract['groupmode'] !== NOGROUPS
                ) {
                    $shown = \local_adele\cohort::learners_of_contract($contract);
                    $relations = self::rerank(array_values(array_filter(
                        $relations,
                        static fn($relation) => isset($shown[(int) $relation['id']])
                    )));
                }
            }
        }

        // A learner gets the own row only (#12 AK0-L2, #16 AK0-M4): without
        // groups the learners of an activity are isolated, whatever the
        // setting 'Participant results' says and through whichever context
        // the call comes. The rankings of a group are get_group_overview's,
        // without ids and in the group only. (Before #12, 'all results' gave
        // learners the course's whole roster; 'only own results', #569, the
        // own row.) Filtering after ranking keeps the caller's true rank.
        if (!$isprivileged && isset($relations[0])) {
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
     * Ranks again after a filter: the same rule as the roster (progress,
     * then completed nodes; equal values share a rank).
     *
     * @param array $relations Rows of the roster, in roster order.
     * @return array
     */
    protected static function rerank(array $relations): array {
        $previous = null;
        foreach ($relations as $index => &$relation) {
            if (
                $previous !== null
                && $previous['progress']['progress'] === $relation['progress']['progress']
                && $previous['progress']['completed_nodes'] === $relation['progress']['completed_nodes']
            ) {
                $relation['rank'] = $previous['rank'];
            } else {
                $relation['rank'] = $index + 1;
            }
            $previous = $relation;
        }
        unset($relation);
        return $relations;
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
