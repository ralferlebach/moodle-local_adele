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
