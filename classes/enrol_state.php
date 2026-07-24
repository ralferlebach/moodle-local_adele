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
     * entitled as long as any node still grants it (requirement A-6). Returns
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
     * Decision G-Q1a (Session 003) revokes L-Q-08: local_adele and mod_adele
     * no longer silently fall back to enrol_manual for ADELE-caused target-
     * or host-course enrolments when enrol_adele is absent. Instead, no
     * enrolment happens at all, and this method surfaces that fact clearly
     * rather than leaving it silent. The end user's request is deliberately
     * not interrupted with a fatal error — a missing companion plugin is an
     * admin misconfiguration, not a reason to break a student's page — so
     * this only logs via debugging() (visible from DEBUG_NORMAL upward).
     * Admins additionally see a standing warning on the local_adele settings
     * page whenever this condition holds (settings.php).
     *
     * @return void
     */
    public static function warn_enrol_adele_missing(): void {
        debugging(
            'local_adele: enrol_adele is not installed or not active. ' .
            'ADELE target/host course enrolments are NOT being created or ' .
            'maintained until this is fixed. Install and enable enrol_adele ' .
            '(decision G-Q1a, revokes L-Q-08).',
            DEBUG_NORMAL
        );
    }

    /**
     * Ask enrol_adele to reconcile one user path, if the plugin is available.
     *
     * Warns via {@see warn_enrol_adele_missing()} instead of silently doing
     * nothing when enrol_adele is absent (decision G-Q1a, revokes L-Q-08).
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
     * nothing when enrol_adele is absent (decision G-Q1a, revokes L-Q-08).
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
}
