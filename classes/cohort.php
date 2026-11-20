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

/**
 * The learners an ADELE activity shows to one person (#12 - #15).
 *
 * One place for who counts as a learner of an activity, so the participant
 * list, the group rankings and averages, the non-learner section, the live
 * markers and the node counters all work on the same people (#12 AK0-L7,
 * #14 AK2-L8, #15 AK3.2):
 *
 *   on the roster of the host course (roster_members_sql: enrolled, also
 *   suspended, and the learners enrol_adele brought there),
 *   with an active snapshot of the path (local_adele_path_user),
 *   taking part by role (not the course's staff, #587),
 *   and, in a group mode, a member of the group(s) asked for.
 *
 * The activity, its group mode, the groups a person may see and the group
 * shown come from mod_adele's context contract - decided on the server from
 * the concrete activity, never from the browser.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort {
    /**
     * The contract of the activity a module context belongs to.
     *
     * @param int $contextid A module context of a mod_adele activity.
     * @param int $groupid The group asked for (-1 default, 0 all participants).
     * @return array See \mod_adele\local\context_contract::for_cm().
     * @throws \invalid_parameter_exception Not an ADELE activity, or a group the person may not see.
     */
    public static function contract(int $contextid, int $groupid = -1): array {
        global $USER;
        $context = \context::instance_by_id($contextid);
        if ($context->contextlevel != CONTEXT_MODULE || !class_exists('\mod_adele\local\context_contract')) {
            throw new \invalid_parameter_exception('Only an ADELE activity has groups and live data.');
        }
        $cm = get_coursemodule_from_id('adele', $context->instanceid);
        if (!$cm) {
            throw new \invalid_parameter_exception('Only an ADELE activity has groups and live data.');
        }
        return \mod_adele\local\context_contract::for_cm((int) $cm->id, (int) $USER->id, $groupid);
    }

    /**
     * Refuse a person the contract gives no view of the activity, or no
     * teacher's view where one is needed.
     *
     * @param array $contract
     * @param bool $teacher Whether the teachers' view is needed.
     * @return void
     * @throws \required_capability_exception
     */
    public static function require_view(array $contract, bool $teacher): void {
        $context = \context::instance_by_id($contract['contextid']);
        if ($contract['view'] === null || ($teacher && $contract['view'] !== 'teacher')) {
            throw new \required_capability_exception(
                $context,
                $teacher ? 'local/adele:viewparticipants' : 'mod/adele:readinstance',
                'nopermissions',
                ''
            );
        }
        if ((int) $contract['learningpathid'] <= 0) {
            throw new \invalid_parameter_exception('The activity shows no learning path.');
        }
    }

    /**
     * The members of groups.
     *
     * @param array $groupids
     * @return array userid => [groupid, ...]
     */
    public static function group_members(array $groupids): array {
        global $DB;
        $groupids = array_values(array_filter(array_map('intval', $groupids)));
        if (!$groupids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');
        $members = [];
        $rs = $DB->get_recordset_sql(
            "SELECT id, userid, groupid FROM {groups_members} WHERE groupid {$insql} ORDER BY id",
            $params
        );
        foreach ($rs as $row) {
            $members[(int) $row->userid][] = (int) $row->groupid;
        }
        $rs->close();
        return $members;
    }

    /**
     * The learners of an activity: their snapshot and name.
     *
     * @param int $courseid The host course.
     * @param int $learningpathid The path.
     * @param array|null $userids Only these people (null: everybody on the roster).
     * @return array userid => record (user_id, json, firstname, lastname, ...name fields)
     */
    public static function learners(int $courseid, int $learningpathid, ?array $userids = null): array {
        global $DB;
        if ($userids !== null && !$userids) {
            return [];
        }
        [$rostersql, $params] = learning_paths::roster_members_sql($courseid);
        $params['learningpathid'] = $learningpathid;
        $usersql = '';
        if ($userids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $userids), SQL_PARAMS_NAMED, 'coh');
            $usersql = "AND lpu.user_id {$insql}";
            $params += $inparams;
        }
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $records = $DB->get_records_sql(
            "SELECT lpu.user_id, lpu.json, {$namefields}
               FROM {local_adele_path_user} lpu
               JOIN {user} u ON u.id = lpu.user_id AND u.deleted = 0
               JOIN ({$rostersql}) roster ON roster.id = lpu.user_id
              WHERE lpu.learning_path_id = :learningpathid
                    AND lpu.status = 'active'
                    {$usersql}",
            $params
        );
        if (!$records) {
            return [];
        }
        $staff = array_flip(enrol_state::get_nonparticipant_userids($courseid, array_map('intval', array_keys($records))));
        $learners = [];
        foreach ($records as $userid => $record) {
            if (!isset($staff[(int) $userid])) {
                $learners[(int) $userid] = $record;
            }
        }
        ksort($learners);
        return $learners;
    }

    /**
     * The learners shown for the group of a contract.
     *
     * @param array $contract The contract (courseid, learningpathid, groupid).
     * @return array userid => record, see learners()
     */
    public static function learners_of_contract(array $contract): array {
        if ((int) $contract['groupid'] < 0) {
            return [];
        }
        $userids = null;
        if ((int) $contract['groupid'] > 0) {
            $userids = array_keys(self::group_members([(int) $contract['groupid']]));
        } else if ((int) $contract['groupingid'] > 0 && (int) $contract['groupmode'] !== NOGROUPS) {
            // All participants of an activity with a grouping: the people of its groups.
            $groups = groups_get_all_groups($contract['courseid'], 0, $contract['groupingid'], 'g.id');
            $userids = array_keys(self::group_members(array_keys($groups)));
        }
        return self::learners((int) $contract['courseid'], (int) $contract['learningpathid'], $userids);
    }

    /**
     * Whether a node of a snapshot is completed (the roster's measure, getnodeprogress()).
     *
     * @param object|null $json The decoded snapshot.
     * @param string $nodeid
     * @return bool
     */
    public static function node_completed($json, string $nodeid): bool {
        return !empty($json->user_path_relation->{$nodeid}->completionnode->valid);
    }
}
