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
 * Intended enrolment state of a user on a learning path.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

/**
 * Intended enrolment state of a user on a learning path.
 *
 * This is the single place that interprets the user path JSON for enrolment
 * purposes; enrol_adele consumes only its result (specification 2.2). Keeping
 * the JSON knowledge here means structural changes to the user path only ever
 * touch this class.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_state {
    /**
     * Node feedback statuses that entitle the user to the node's courses.
     *
     * 'completed' counts: a finished course must remain accessible.
     */
    const ENTITLING_STATUSES = ['accessible', 'completed'];

    /**
     * The kind of enrol_adele instance that holds the enrolments granted
     * through the teacher view (enrol_adele instance_manager::KIND_TEACHER,
     * local_adele #600). Repeated here: local_adele does not depend on
     * enrol_adele's classes being loadable.
     */
    const TEACHER_GRANT_KIND = 3;

    /**
     * Course ids the user is entitled to right now on this learning path.
     *
     * Aggregated as a set across ALL nodes, so a shared target course stays
     * entitled as long as any node still grants it. Returns
     * an empty array when the learning path no longer exists or the user has
     * no active user path — both mean: no entitlement.
     *
     * @param int $learningpathid The learning path id.
     * @param int $userid The user id.
     * @return int[] Target course ids.
     */
    public static function get_entitled_courseids(int $learningpathid, int $userid): array {
        global $DB;

        if (!$DB->record_exists('local_adele_learning_paths', ['id' => $learningpathid])) {
            return [];
        }
        $userpaths = $DB->get_records(
            'local_adele_path_user',
            [
                'learning_path_id' => $learningpathid,
                'user_id' => $userid,
                'status' => 'active',
            ],
            'id DESC',
            'id, json'
        );
        $entitled = [];
        foreach ($userpaths as $userpath) {
            $json = json_decode($userpath->json, true);
            if (!is_array($json)) {
                continue;
            }
            foreach (($json['tree']['nodes'] ?? []) as $node) {
                $status = $json['user_path_relation'][$node['id']]['feedback']['status'] ?? '';
                if (!in_array($status, self::ENTITLING_STATUSES, true)) {
                    continue;
                }
                $courseids = $node['data']['course_node_id'] ?? [];
                if (!is_array($courseids)) {
                    continue;
                }
                foreach ($courseids as $courseid) {
                    $entitled[(int) $courseid] = true;
                }
            }
        }
        unset($entitled[0]);
        return array_keys($entitled);
    }

    /**
     * Whether the ADELE enrolment plugin handles target course enrolments.
     *
     * @return bool
     */
    public static function adele_enrol_active(): bool {
        return class_exists('\enrol_adele\local\reconciler')
            && \enrol_adele\local\reconciler::is_active();
    }

    /**
     * Emit a clear, admin-visible signal that enrol_adele is required but
     * missing or inactive.
     *
     * local_adele and mod_adele do not fall back to enrol_manual for
     * ADELE-caused target- or host-course enrolments when enrol_adele is
     * absent; no enrolment happens at all, and this method surfaces that
     * fact rather than leaving it silent. The end user's request is
     * deliberately not interrupted with a fatal error — a missing companion
     * plugin is an admin misconfiguration, not a reason to break a student's
     * page — so this only logs via debugging() (visible from DEBUG_NORMAL
     * upward). Admins additionally see a standing warning on the local_adele
     * settings page whenever this condition holds.
     *
     * @return void
     */
    public static function warn_enrol_adele_missing(): void {
        debugging(
            'local_adele: enrol_adele is not installed or not active. ' .
            'ADELE target/host course enrolments are NOT being created or ' .
            'maintained until this is fixed. Install and enable enrol_adele.',
            DEBUG_NORMAL
        );
    }

    /**
     * Ask enrol_adele to reconcile one user path, if the plugin is available.
     *
     * Warns via {@see warn_enrol_adele_missing()} instead of silently doing
     * nothing when enrol_adele is absent.
     *
     * @param int $learningpathid The learning path id.
     * @param int $userid The user id.
     * @return void
     */
    public static function request_reconcile(int $learningpathid, int $userid): void {
        if (self::adele_enrol_active()) {
            \enrol_adele\local\reconciler::reconcile_user($learningpathid, $userid);
            return;
        }
        self::warn_enrol_adele_missing();
    }

    /**
     * Ask enrol_adele to purge everything a learning path created, if available.
     *
     * Warns via {@see warn_enrol_adele_missing()} instead of silently doing
     * nothing when enrol_adele is absent.
     *
     * @param int $learningpathid The learning path id.
     * @return void
     */
    public static function request_purge(int $learningpathid): void {
        if (self::adele_enrol_active()) {
            \enrol_adele\local\reconciler::purge_learning_path($learningpathid);
            return;
        }
        self::warn_enrol_adele_missing();
    }

    /**
     * Which host courses embed a learning path, and via which options.
     *
     * Reads mod_adele's own {adele} table through
     * mod_adele\local\host_policy, which owns the semantics of
     * participantslist and hostenrolmentmode. This plugin already declares a
     * dependency on mod_adele, so the call introduces no new coupling;
     * enrol_adele reaches the same derivation through here without ever
     * touching {adele} itself.
     *
     * @param int $learningpathid The learning path id.
     * @return array Rows: courseid, option1/2/3 (bool each), mode (string).
     */
    public static function get_host_embeddings(int $learningpathid): array {
        if (!self::host_policy_available()) {
            return [];
        }
        $result = [];
        foreach (\mod_adele\local\host_policy::get_embeddings($learningpathid) as $embedding) {
            $result[] = [
                'courseid' => (int) $embedding['courseid'],
                'option1' => (bool) $embedding['option1'],
                'option2' => (bool) $embedding['option2'],
                'option3' => (bool) $embedding['option3'],
                'mode' => (string) $embedding['mode'],
            ];
        }
        return $result;
    }

    /**
     * Which learning paths have a host-course embedding in the given course.
     *
     * @param int $courseid The host course id.
     * @return int[] Distinct learning path ids.
     */
    public static function get_learningpaths_embedded_in_course(int $courseid): array {
        if (!self::host_policy_available()) {
            return [];
        }
        return \mod_adele\local\host_policy::get_learningpaths_embedded_in_course($courseid);
    }

    /**
     * Every learning path embedded anywhere with subscription option 2 or 3.
     *
     * The entry point for enrol_adele's host-course sweep: only these
     * learning paths can own host-course enrolments at all.
     *
     * @return int[] Distinct learning path ids.
     */
    public static function get_learningpaths_with_host_embeddings(): array {
        if (!self::host_policy_available()) {
            return [];
        }
        return \mod_adele\local\host_policy::get_learningpaths_with_host_embeddings();
    }

    /**
     * Every user an embedding could entitle, including those ADELE has never
     * seen before.
     *
     * Needed so enrol_adele's sweep can heal a WIDENED subscription option,
     * not only a narrowed one. See mod_adele\local\host_policy for why the
     * other candidate populations cannot contain these users.
     *
     * @param int $learningpathid The learning path id.
     * @param int $hostcourseid The host course id.
     * @return int[] Candidate user ids.
     */
    public static function get_host_candidate_userids(int $learningpathid, int $hostcourseid): array {
        if (!self::host_policy_available()) {
            return [];
        }
        if (!method_exists('\\mod_adele\\local\\host_policy', 'get_candidate_userids')) {
            // Loudly, not quietly. An empty list here is indistinguishable
            // from "nobody is a candidate", so a mod_adele that is merely too
            // old would silently produce a WRONG answer rather than no answer
            // — which is how a version skew during deployment turns into a
            // single mysterious failure somewhere far away instead of a
            // message naming its cause.
            debugging(
                'local_adele: the installed mod_adele predates ' .
                'host_policy::get_candidate_userids(). Host course entitlements ' .
                'cannot be derived for users without an existing subscription ' .
                'until both plugins are upgraded.',
                DEBUG_NORMAL
            );
            return [];
        }
        return \mod_adele\local\host_policy::get_candidate_userids($learningpathid, $hostcourseid);
    }

    /**
     * Whether one user is entitled to host-course access for one embedding,
     * and in which visibility mode.
     *
     * The counterpart of {@see get_entitled_courseids()} for host courses.
     * Unlike that method, the derivation is not this plugin's own: it belongs
     * to mod_adele, which owns the subscription options. This method only
     * routes the question there, so that enrol_adele's nightly sweep and
     * mod_adele's live observer answer it with identical code.
     *
     * Returns null — deliberately not [false, ...] — when mod_adele is not
     * available. "I cannot tell" and "not entitled" must not collapse into
     * one answer: a caller acting on false would revoke access from every
     * user the moment mod_adele is uninstalled or mid-upgrade.
     *
     * @param int $learningpathid The learning path id.
     * @param int $hostcourseid The host course id.
     * @param int $userid The user id.
     * @return array|null [bool $entitled, string $mode], or null if unknown.
     */
    public static function get_host_entitlement(int $learningpathid, int $hostcourseid, int $userid): ?array {
        if (!self::host_policy_available()) {
            return null;
        }
        return \mod_adele\local\host_policy::get_entitlement($learningpathid, $hostcourseid, $userid);
    }

    /**
     * The one definition of an effective course enrolment of one's own
     * (mod_adele #30), as SQL over a user_enrolments alias and its enrol
     * alias: not made by ADELE itself (mod_adele #37), the method enabled,
     * the enrolment active (not suspended) and inside its time window.
     *
     * The site setting mod_adele/sourcesincludesuspended lets suspended
     * enrolments count as well (the behaviour before #30); the time window
     * and the method's status always count.
     *
     * @param string $ue Alias of {user_enrolments}.
     * @param string $e Alias of {enrol}.
     * @return array [string $sql, array $params] with unique named parameters.
     */
    public static function effective_enrolment_sql(string $ue = 'ue', string $e = 'e'): array {
        static $counter = 0;
        $counter++;
        $p = 'effenrol' . $counter;
        $now = \core\di::get(\core\clock::class)->time();
        $sql = "{$e}.enrol <> 'adele'
                AND {$e}.status = :{$p}enabled
                AND ({$ue}.timestart = 0 OR {$ue}.timestart <= :{$p}now1)
                AND ({$ue}.timeend = 0 OR {$ue}.timeend > :{$p}now2)";
        $params = [$p . 'enabled' => ENROL_INSTANCE_ENABLED, $p . 'now1' => $now, $p . 'now2' => $now];
        if (!self::suspended_enrolments_count()) {
            $sql .= " AND {$ue}.status = :{$p}active";
            $params[$p . 'active'] = ENROL_USER_ACTIVE;
        }
        return [$sql, $params];
    }

    /**
     * Whether the site lets suspended enrolments count as a source
     * (setting of mod_adele, off by default; mod_adele #30).
     *
     * @return bool
     */
    public static function suspended_enrolments_count(): bool {
        return !empty(get_config('mod_adele', 'sourcesincludesuspended'));
    }

    /**
     * Of the given courses: those in which the user holds an effective
     * enrolment of their own ({@see effective_enrolment_sql()}).
     *
     * @param int $userid The user.
     * @param int[] $courseids The courses.
     * @return int[] Course ids.
     */
    public static function get_effectively_enrolled_courseids(int $userid, array $courseids): array {
        global $DB;
        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        if (!$courseids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'effc');
        [$effsql, $effparams] = self::effective_enrolment_sql();
        return array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :effuserid
                    AND {$effsql}
                    AND e.courseid {$insql}",
            ['effuserid' => $userid] + $effparams + $inparams
        ));
    }

    /**
     * Whether a user holds an effective enrolment of their own in a course
     * and takes part in it as a participant - the full test of a
     * participant source (mod_adele #30, #37; local_adele #587).
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @return bool
     */
    public static function is_effective_participant(int $courseid, int $userid): bool {
        return self::get_effectively_enrolled_courseids($userid, [$courseid])
            && self::is_participant($courseid, $userid);
    }

    /**
     * Whether a user takes part in a course AS A PARTICIPANT, by their own
     * right (#587).
     *
     * Being enrolled is not enough: a teacher is enrolled too. Taking part
     * is having, in the course (or above it), one of the site's GRADED ROLES
     * (participant_roleids(), #593) - the list by which Moodle itself decides
     * who appears in grades and completion reports. And not through a role
     * ADELE assigned itself: the student role enrol_adele gives a learner in
     * a node or host course must not make that learner a participant there
     * by own right (the same reason ADELE's own enrolments do not count,
     * mod_adele #37).
     *
     * The one definition for every place that asks - subscribing on
     * enrolment (local_adele), the participant sources (mod_adele), whether
     * a subscription is still carried (enrol_adele).
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @return bool
     */
    public static function is_participant(int $courseid, int $userid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        $graded = array_flip(self::participant_roleids());
        if (!$graded) {
            return false;
        }
        foreach (get_user_roles($context, $userid, true) as $assignment) {
            if ($assignment->component !== 'enrol_adele' && isset($graded[(int) $assignment->roleid])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The roles whose holders take part in a course's learning paths: the
     * site's graded roles (Site administration > Grades > General settings >
     * Graded roles). Read on every call - a change of the setting counts from
     * the next question on, without an upgrade step (#593).
     *
     * @return int[] Role ids.
     */
    public static function participant_roleids(): array {
        global $CFG;
        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) ($CFG->gradebookroles ?? '')))
        )));
    }

    /**
     * Of the given users: those who are in the course by their own right and
     * do NOT take part in it - its teachers, in short (#587).
     *
     * For the roster of an embedding. Somebody who is in the course only
     * through ADELE (host modes) has no own-right enrolment and is not
     * returned: such a learner is on the roster because of the path.
     * Decides like {@see is_participant()}, for many users in two queries -
     * with one addition for the roster: an active enrolment through the
     * teacher view (enrol_adele kind 3, local_adele #600) is the person's
     * own right in the course, and the roles it gives count. A tutor whose
     * only way into the course is such a grant is staff of the course, not
     * a learner - even with a snapshot of the path from an earlier time
     * (re-audit 2026-11-27, section 3; it used to be listed as a learner).
     *
     * @param int $courseid The course.
     * @param int[] $userids The users to look at.
     * @return int[] User ids.
     */
    public static function get_nonparticipant_userids(int $courseid, array $userids): array {
        global $DB;
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$context || !$userids) {
            return [];
        }
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        [$effsql, $effparams] = self::effective_enrolment_sql();
        // The teacher grants of the course: active, inside their window.
        $now = \core\di::get(\core\clock::class)->time();
        $grantsql = "e.enrol = 'adele' AND e.customint2 = :grantkind AND e.status = :grantenabled
                     AND ue.status = :grantactive
                     AND (ue.timestart = 0 OR ue.timestart <= :grantnow1)
                     AND (ue.timeend = 0 OR ue.timeend > :grantnow2)";
        $grantparams = ['grantkind' => self::TEACHER_GRANT_KIND, 'grantenabled' => ENROL_INSTANCE_ENABLED,
            'grantactive' => ENROL_USER_ACTIVE, 'grantnow1' => $now, 'grantnow2' => $now];
        $ownright = $DB->get_fieldset_sql(
            "SELECT DISTINCT ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                    AND (({$effsql}) OR ({$grantsql}))
                    AND ue.userid {$usersql}",
            ['courseid' => $courseid] + $effparams + $grantparams + $userparams
        );
        if (!$ownright) {
            return [];
        }
        $needed = array_flip(self::participant_roleids());
        $participants = [];
        if ($needed) {
            [$ctxsql, $ctxparams] = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'ctx');
            [$ownsql, $ownparams] = $DB->get_in_or_equal($ownright, SQL_PARAMS_NAMED, 'own');
            $assignments = $DB->get_recordset_sql(
                "SELECT ra.id, ra.userid, ra.roleid
                   FROM {role_assignments} ra
                  WHERE ra.contextid {$ctxsql}
                        AND (ra.component <> 'enrol_adele'
                             OR ra.itemid IN (SELECT ge.id
                                                FROM {enrol} ge
                                               WHERE ge.courseid = :racourseid AND ge.enrol = 'adele'
                                                     AND ge.customint2 = :rakind))
                        AND ra.userid {$ownsql}",
                $ctxparams + $ownparams + ['racourseid' => $courseid, 'rakind' => self::TEACHER_GRANT_KIND]
            );
            foreach ($assignments as $assignment) {
                if (isset($needed[(int) $assignment->roleid])) {
                    $participants[(int) $assignment->userid] = true;
                }
            }
            $assignments->close();
        }
        $staff = [];
        foreach ($ownright as $userid) {
            $userid = (int) $userid;
            if (empty($participants[$userid])) {
                $staff[] = $userid;
            }
        }
        return $staff;
    }

    /**
     * Whether mod_adele's host policy can be reached.
     *
     * mod_adele is a declared dependency, so a missing class means a broken
     * or partially upgraded installation rather than a supported
     * configuration — hence the debugging() notice.
     *
     * @return bool
     */
    private static function host_policy_available(): bool {
        if (class_exists('\mod_adele\local\host_policy')) {
            return true;
        }
        debugging(
            'local_adele: mod_adele\\local\\host_policy is not available. ' .
            'Host course entitlements cannot be derived until mod_adele is ' .
            'installed and upgraded.',
            DEBUG_NORMAL
        );
        return false;
    }
}
