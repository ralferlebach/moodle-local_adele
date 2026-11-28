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
 * Group rankings, group averages and the people who are no learners (#12, #13).
 *
 * Who sees what (#12, visibility matrix):
 *
 *   no groups        learner: the own path; the participant list holds the
 *                    own row, or with "Participant results: results of all
 *                    other participants" the ranking of the activity's
 *                    learners (no ids, nothing to click); teacher: the
 *                    learners of the course.
 *   separate groups  learner: per own group a ranking of its members - name
 *                    and progress, no ids, nothing to click; teacher: the
 *                    learners of the group chosen.
 *   visible groups   learner: as separate, plus the average of each own group
 *                    above its ranking and the averages of the other groups -
 *                    no names, ids or single values of other groups; teacher:
 *                    the learners of the group chosen plus the averages of
 *                    the groups.
 *
 * The activity setting "Participant results" is shown and applies without
 * groups only (decision 2026-11-22, cohort::own_results_only()); with groups
 * the matrix above holds whatever value is stored. Every number is computed
 * here; foreign single values never leave the server.
 *
 * An average is the arithmetic mean of the progress (the roster's best-route
 * percentage) over the group's distinct learners - every participant of the
 * group who takes part by role, also those without a path or progress yet.
 * They are not dropped from the denominator: they count with 0 %, and the
 * result says how many they are ("members" - "measured"). A group without
 * learners has no value (null, shown as "no data"), never 0 % or NaN. A
 * person in several groups counts once in each of them.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_progress {
    /**
     * The overview for the person the contract is for.
     *
     * @param array $contract See cohort::contract().
     * @return array groupmode, groupid, groups, rankings, averages
     */
    public static function overview(array $contract): array {
        global $USER;
        $mode = (int) $contract['groupmode'];
        $result = [
            'groupmode' => $mode,
            'groupid' => (int) $contract['groupid'],
            'groups' => $contract['view'] === 'teacher' ? $contract['groups'] : [],
            // Whether a teacher may choose "all participants".
            'allowall' => $contract['view'] === 'teacher'
                && ($mode === NOGROUPS || $mode === VISIBLEGROUPS || !empty($contract['accessallgroups'])),
            'rankings' => [],
            'averages' => [],
            // A learner whose activity says "only own results" - without groups
            // only (decision 2026-11-22): the own row, no rankings.
            'closed' => $contract['view'] !== 'teacher' && cohort::own_results_only($contract),
        ];
        if ($mode === NOGROUPS) {
            return $result;
        }
        $allgroups = groups_get_all_groups($contract['courseid'], 0, $contract['groupingid'], 'g.id, g.name');
        $context = \context::instance_by_id($contract['contextid']);
        $name = static fn($group) => format_string($group->name, true, ['context' => $context]);

        if ($contract['view'] === 'teacher') {
            if ($mode === VISIBLEGROUPS) {
                $ids = array_column($contract['groups'], 'id');
                $result['averages'] = self::averages($contract, $allgroups, $ids, []);
            }
            return $result;
        }

        // A learner: the ranking of each own group - whatever "Participant
        // results" says, which applies without groups only (decision 2026-11-22).
        $own = array_values(array_intersect($contract['owngroups'], array_map('intval', array_keys($allgroups))));
        $members = cohort::group_members($own);
        $learners = $own ? cohort::learners($contract['courseid'], $contract['learningpathid'], array_keys($members)) : [];
        foreach ($own as $groupid) {
            $records = array_filter(
                $learners,
                static fn($record) => in_array($groupid, $members[(int) $record->user_id] ?? [], true)
            );
            $ranking = [
                'groupid' => $groupid,
                'groupname' => $name($allgroups[$groupid]),
                'members' => array_map(static function ($row) use ($USER) {
                    return [
                        'rank' => $row['rank'],
                        'fullname' => $row['fullname'],
                        'progress' => $row['progress'],
                        'self' => (int) $row['userid'] === (int) $USER->id,
                    ];
                }, self::ranking($records, $context)),
            ];
            $result['rankings'][] = $ranking;
        }
        if ($mode === VISIBLEGROUPS) {
            $result['averages'] = self::averages($contract, $allgroups, array_map('intval', array_keys($allgroups)), $own);
        }
        return $result;
    }

    /**
     * The averages of groups.
     *
     * @param array $contract The contract.
     * @param array $allgroups id => group record of the grouping.
     * @param array $groupids The groups to compute.
     * @param array $own The person's own groups (first, marked).
     * @return array list of groupid, groupname, own, value (null: no learners), measured, members
     */
    public static function averages(array $contract, array $allgroups, array $groupids, array $own): array {
        $groupids = array_values(array_intersect(array_map('intval', $groupids), array_map('intval', array_keys($allgroups))));
        if (!$groupids) {
            return [];
        }
        $context = \context::instance_by_id($contract['contextid']);
        $members = cohort::group_members($groupids);
        $learners = cohort::learners($contract['courseid'], $contract['learningpathid'], array_keys($members));
        // The group's learners without a path: participants on the roster, not staff.
        $withoutpath = self::participants_without_path(
            $contract['courseid'],
            array_diff(array_keys($members), array_keys($learners))
        );
        $rows = [];
        foreach ($groupids as $groupid) {
            $sum = 0.0;
            $measured = 0;
            $unmeasured = 0;
            foreach ($members as $userid => $groups) {
                if (!in_array($groupid, $groups, true)) {
                    continue;
                }
                if (isset($learners[$userid])) {
                    $progress = learning_paths::getnodeprogress(json_decode($learners[$userid]->json));
                    $sum += (float) ($progress['progress'] ?? 0);
                    $measured++;
                } else if (isset($withoutpath[$userid])) {
                    $unmeasured++;
                }
            }
            $rows[] = [
                'groupid' => $groupid,
                'groupname' => format_string($allgroups[$groupid]->name, true, ['context' => $context]),
                'own' => in_array($groupid, $own, true),
                // Learners without progress count with 0 % (not out of the denominator).
                'value' => ($measured + $unmeasured) ? round($sum / ($measured + $unmeasured), 1) : null,
                'measured' => $measured,
                'members' => $measured + $unmeasured,
            ];
        }
        usort(
            $rows,
            static fn($a, $b) => [$b['own'], $a['groupname'], $a['groupid']] <=> [$a['own'], $b['groupname'], $b['groupid']]
        );
        return $rows;
    }

    /**
     * A ranking: progress descending, then completed nodes, then name - a fixed order for ties.
     *
     * @param array $records userid => record with json and name fields.
     * @param \context $context For the display of full names.
     * @return array list of userid, fullname, progress, completed, rank
     */
    public static function ranking(array $records, \context $context): array {
        $viewfull = has_capability('moodle/site:viewfullnames', $context);
        $rows = [];
        foreach ($records as $record) {
            $progress = learning_paths::getnodeprogress(json_decode($record->json));
            $rows[] = [
                'userid' => (int) $record->user_id,
                'fullname' => fullname($record, $viewfull),
                'sortname' => \core_text::strtolower(($record->lastname ?? '') . ' ' . ($record->firstname ?? '')),
                'progress' => (float) ($progress['progress'] ?? 0),
                'completed' => (int) ($progress['completed_nodes'] ?? 0),
            ];
        }
        usort($rows, static fn($a, $b) => [$b['progress'], $b['completed'], $a['sortname'], $a['userid']]
            <=> [$a['progress'], $a['completed'], $b['sortname'], $b['userid']]);
        $rank = 0;
        $previous = null;
        foreach ($rows as $index => &$row) {
            if ($previous === null || $previous['progress'] !== $row['progress'] || $previous['completed'] !== $row['completed']) {
                $rank = $index + 1;
            }
            $row['rank'] = $rank;
            $previous = $row;
            unset($row['sortname']);
        }
        unset($row);
        return $rows;
    }

    /**
     * Participants of the course among some people (taking part by role, on the roster).
     *
     * @param int $courseid
     * @param array $userids
     * @return array userid => true
     */
    public static function participants_without_path(int $courseid, array $userids): array {
        global $DB;
        $userids = array_values(array_map('intval', $userids));
        if (!$userids) {
            return [];
        }
        [$rostersql, $params] = learning_paths::roster_members_sql($courseid);
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'wp');
        $onroster = $DB->get_fieldset_sql("SELECT r.id FROM ({$rostersql}) r WHERE r.id {$insql}", $params + $inparams);
        $staff = array_flip(enrol_state::get_nonparticipant_userids($courseid, $onroster));
        $out = [];
        foreach ($onroster as $userid) {
            if (!isset($staff[(int) $userid])) {
                $out[(int) $userid] = true;
            }
        }
        return $out;
    }

    /**
     * The people of the course who are no learners of the path (#13).
     *
     * Enrolled in the host course (and, in a group, members of the group
     * chosen), and either not taking part by role (the course's staff) or
     * without a snapshot of the path. Nothing is created for them - no
     * subscription, no enrolment, no progress: they have none.
     *
     * @param array $contract The teacher's contract.
     * @return array list of userid, fullname, reason ('nonlearner'|'nopath'), roles
     */
    public static function non_learners(array $contract): array {
        global $DB;
        if ((int) $contract['groupid'] < 0) {
            return [];
        }
        $context = \context::instance_by_id($contract['contextid']);
        $coursecontext = \context_course::instance($contract['courseid']);
        [$rostersql, $params] = learning_paths::roster_members_sql((int) $contract['courseid']);
        $people = $DB->get_fieldset_sql("SELECT r.id FROM ({$rostersql}) r", $params);
        // The same people the participant list covers (#12 AK0-L7, #13 AK1.6):
        // the group chosen, or with "all participants" and a grouping the
        // people of the grouping's groups (re-audit 2026-11-21: groupid 0
        // used to ignore the grouping).
        $filter = cohort::member_filter($contract);
        if ($filter !== null) {
            $people = array_intersect($people, $filter);
        }
        $people = array_map('intval', array_values($people));
        if (!$people) {
            return [];
        }
        $learners = cohort::learners($contract['courseid'], $contract['learningpathid'], $people);
        $staff = array_flip(enrol_state::get_nonparticipant_userids($contract['courseid'], $people));
        $others = array_values(array_diff($people, array_keys($learners)));
        if (!$others) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($others, SQL_PARAMS_NAMED, 'nl');
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $users = $DB->get_records_sql("SELECT u.id, {$namefields} FROM {user} u WHERE u.id {$insql} AND u.deleted = 0", $inparams);
        $viewfull = has_capability('moodle/site:viewfullnames', $context);
        $rolenames = role_fix_names(get_all_roles($coursecontext), $coursecontext, ROLENAME_ALIAS, true);
        $out = [];
        foreach ($users as $user) {
            $roles = array_map(
                static fn($ra) => $rolenames[$ra->roleid] ?? '',
                get_user_roles($coursecontext, $user->id, true)
            );
            $out[] = [
                'userid' => (int) $user->id,
                'fullname' => fullname($user, $viewfull),
                'reason' => isset($staff[(int) $user->id]) ? 'nonlearner' : 'nopath',
                'roles' => implode(', ', array_unique(array_filter($roles))),
                'sortname' => \core_text::strtolower(($user->lastname ?? '') . ' ' . ($user->firstname ?? '')),
            ];
        }
        usort($out, static fn($a, $b) => [$a['sortname'], $a['userid']] <=> [$b['sortname'], $b['userid']]);
        return array_map(static function ($row) {
            unset($row['sortname']);
            return $row;
        }, $out);
    }
}
