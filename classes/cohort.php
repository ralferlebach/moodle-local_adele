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
        // All participants of an activity with a grouping: the people of its groups.
        return self::learners((int) $contract['courseid'], (int) $contract['learningpathid'], self::member_filter($contract));
    }

    /** @var int Group "any group the person may choose" - for checks about one learner. */
    public const ANY_GROUP = -2;

    /**
     * The learners whose data a teacher may read through a context (#12 AK0-L1,
     * AK0-L6, #16 AK0-M2, AK0-M4).
     *
     * The rights of a teacher over learners come from the concrete activity:
     * its group mode, grouping and the person's groups. Every way into the
     * data of a path in a course is held to that:
     *
     *   the module context of an ADELE activity showing the path in that
     *   course: that activity's contract, with the group asked for (a foreign
     *   group is refused by the contract; ANY_GROUP: every group the person
     *   may choose there);
     *   any other context of the course (the course itself, another module,
     *   an ADELE activity of another path): no more than the activities of
     *   the path in the course give together - nobody, if none gives the
     *   person a teacher's view.
     *
     * Site administrators are not limited (has_learner_data_authority()).
     *
     * @param \context $context The context the request names.
     * @param int $courseid The course of that context.
     * @param int $learningpathid The path.
     * @param int $groupid The group asked for (module context only; -1 default, 0 all, ANY_GROUP).
     * @return array|null userid => true; null: no limit
     * @throws \invalid_parameter_exception A group the person may not see.
     */
    public static function teacher_scope(\context $context, int $courseid, int $learningpathid, int $groupid = -1): ?array {
        global $DB, $USER;
        if ($context->contextlevel == CONTEXT_MODULE && class_exists('\mod_adele\local\context_contract')) {
            $cm = get_coursemodule_from_id('adele', $context->instanceid);
            if ($cm && (int) $cm->course === $courseid) {
                $instancepath = (int) $DB->get_field('adele', 'learningpathid', ['id' => $cm->instance]);
                if ($instancepath === $learningpathid) {
                    if ($groupid === self::ANY_GROUP) {
                        return self::contract_scope(self::contract((int) $context->id));
                    }
                    $contract = self::contract((int) $context->id, $groupid);
                    if ($contract['view'] !== 'teacher') {
                        return [];
                    }
                    if ((int) $contract['groupmode'] === NOGROUPS) {
                        return null;
                    }
                    return array_fill_keys(array_keys(self::learners_of_contract($contract)), true);
                }
            }
        }
        if (learning_paths::has_learner_data_authority()) {
            return null;
        }
        return self::course_scope($courseid, $learningpathid, (int) $USER->id);
    }

    /**
     * The learners of a path in a course a person may see as a teacher
     * through the activities showing the path there, together.
     *
     * @param int $courseid
     * @param int $learningpathid
     * @param int $userid The teacher.
     * @return array|null userid => true; null: no limit (an activity without groups shows everybody)
     */
    public static function course_scope(int $courseid, int $learningpathid, int $userid): ?array {
        global $DB;
        if (!class_exists('\mod_adele\local\context_contract') || !$DB->get_manager()->table_exists('adele')) {
            return [];
        }
        $instances = $DB->get_records('adele', ['course' => $courseid, 'learningpathid' => $learningpathid], 'id', 'id');
        if (!$instances) {
            return [];
        }
        $scope = [];
        foreach (get_fast_modinfo($courseid, $userid)->get_instances_of('adele') as $cm) {
            if (!isset($instances[$cm->instance])) {
                continue;
            }
            $part = self::contract_scope(\mod_adele\local\context_contract::for_cm((int) $cm->id, $userid));
            if ($part === null) {
                return null;
            }
            $scope += $part;
        }
        return $scope;
    }

    /**
     * The learners one activity lets a person see as a teacher, over every
     * group they may choose there.
     *
     * @param array $contract The activity's contract for the person (default group).
     * @return array|null userid => true; null: no limit
     */
    protected static function contract_scope(array $contract): ?array {
        if ($contract['view'] !== 'teacher') {
            return [];
        }
        if ((int) $contract['groupmode'] === NOGROUPS) {
            return null;
        }
        if ((int) $contract['groupid'] === 0) {
            // All participants: the course, or the grouping's groups.
            return array_fill_keys(array_keys(self::learners_of_contract($contract)), true);
        }
        $groupids = array_column($contract['groups'], 'id');
        if (!$groupids) {
            return [];
        }
        $members = self::group_members($groupids);
        if (!$members) {
            return [];
        }
        $learners = self::learners((int) $contract['courseid'], (int) $contract['learningpathid'], array_keys($members));
        return array_fill_keys(array_keys($learners), true);
    }

    /**
     * Whether a teacher may read or change one learner through a context.
     *
     * @param \context $context
     * @param int $courseid
     * @param int $learningpathid
     * @param int $learnerid
     * @return bool
     */
    public static function teacher_may_see(\context $context, int $courseid, int $learningpathid, int $learnerid): bool {
        $scope = self::teacher_scope($context, $courseid, $learningpathid, self::ANY_GROUP);
        return $scope === null || isset($scope[$learnerid]);
    }

    /**
     * Whether an activity shows its learners only their own results: the
     * setting "Participant results" = "Only own results" (userlist = 2). It
     * applies without groups only (decision 2026-11-22); with separate or
     * visible groups learners see the ranking of their own group(s) whatever
     * the stored value says (#12), and the form hides the field.
     *
     * @param array $contract
     * @return bool
     */
    public static function own_results_only(array $contract): bool {
        return (int) $contract['groupmode'] === NOGROUPS && (int) $contract['userlist'] === 2;
    }

    /**
     * The learners a learner may see in the participant list of an activity
     * without groups whose "Participant results" says "Results of all other
     * participants" (userlist = 1, decision 2026-11-22): the activity's
     * learners (roster of the course, active path, participation by role).
     * Only through the module context of an ADELE activity showing this path
     * in this course; anything else - another context, a group mode, "only
     * own results" - gives null: the own row only.
     *
     * @param \context $context The context the request names.
     * @param int $courseid The course of that context.
     * @param int $learningpathid The path.
     * @return array|null userid => true; null: the own row only
     */
    public static function learner_roster_scope(\context $context, int $courseid, int $learningpathid): ?array {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE || !class_exists('\mod_adele\local\context_contract')) {
            return null;
        }
        $cm = get_coursemodule_from_id('adele', $context->instanceid);
        if (!$cm || (int) $cm->course !== $courseid) {
            return null;
        }
        if ((int) $DB->get_field('adele', 'learningpathid', ['id' => $cm->instance]) !== $learningpathid) {
            return null;
        }
        $contract = self::contract((int) $context->id);
        if (
            $contract['view'] !== 'student'
            || (int) $contract['groupmode'] !== NOGROUPS
            || (int) $contract['userlist'] !== 1
        ) {
            return null;
        }
        return array_fill_keys(array_keys(self::learners_of_contract($contract)), true);
    }

    /**
     * The people an activity's group choice covers - the group, or with "all
     * participants" and a grouping the people of the grouping's groups.
     *
     * @param array $contract
     * @return array|null userids; null: nobody is left out
     */
    public static function member_filter(array $contract): ?array {
        if ((int) $contract['groupid'] > 0) {
            return array_keys(self::group_members([(int) $contract['groupid']]));
        }
        if ((int) $contract['groupingid'] > 0 && (int) $contract['groupmode'] !== NOGROUPS) {
            $groups = groups_get_all_groups($contract['courseid'], 0, $contract['groupingid'], 'g.id');
            return array_keys(self::group_members(array_keys($groups)));
        }
        return null;
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
