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
     * Keep the host-course index in sync with one mod_adele activity.
     *
     * Called from mod_adele's adele_add_instance()/adele_update_instance()
     * lifecycle hooks. This is the write side of the index; enrol_adele reads
     * it via get_host_embeddings() below instead of querying mod_adele's own
     * {adele} table and participantslist format directly. mod_adele already
     * declares a dependency on local_adele, so calling in here introduces no
     * new dependency direction.
     *
     * @param int $adeleinstanceid mod_adele's own adele.id for this activity.
     * @param int $learningpathid The learning path this activity embeds.
     * @param int $courseid The host course this activity lives in.
     * @param string|null $participantslist mod_adele's raw comma-separated
     *     options string (e.g. '2,3'), or null/empty for "no host access".
     * @return void
     */
    public static function sync_host_course_index(
        int $adeleinstanceid,
        int $learningpathid,
        int $courseid,
        ?string $participantslist
    ): void {
        global $DB;

        $options = array_map('trim', explode(',', (string) $participantslist));
        $record = (object) [
            'adeleinstanceid' => $adeleinstanceid,
            'learningpathid' => $learningpathid,
            'courseid' => $courseid,
            'participantoption1' => in_array('1', $options, true) ? 1 : 0,
            'participantoption2' => in_array('2', $options, true) ? 1 : 0,
            'participantoption3' => in_array('3', $options, true) ? 1 : 0,
            'timemodified' => time(),
        ];

        $existing = $DB->get_record('local_adele_host_courses', ['adeleinstanceid' => $adeleinstanceid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_adele_host_courses', $record);
        } else {
            $DB->insert_record('local_adele_host_courses', $record);
        }
    }

    /**
     * Remove one mod_adele activity's row from the host-course index.
     *
     * Called from mod_adele's adele_delete_instance() lifecycle hook.
     *
     * @param int $adeleinstanceid mod_adele's own adele.id for the deleted activity.
     * @return void
     */
    public static function remove_host_course_index(int $adeleinstanceid): void {
        global $DB;
        $DB->delete_records('local_adele_host_courses', ['adeleinstanceid' => $adeleinstanceid]);
    }

    /**
     * Which host courses embed a learning path, and via which options.
     *
     * The read side enrol_adele uses instead of querying mod_adele's own
     * {adele} table directly. Returns already-normalised booleans rather than
     * mod_adele's raw participantslist string, so enrol_adele does not need to
     * know that format.
     *
     * @param int $learningpathid The learning path id.
     * @return array Rows keyed by id: courseid, option1/2/3 (bool each).
     */
    public static function get_host_embeddings(int $learningpathid): array {
        global $DB;
        $rows = $DB->get_records(
            'local_adele_host_courses',
            ['learningpathid' => $learningpathid],
            '',
            'id, courseid, participantoption1, participantoption2, participantoption3'
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'courseid' => (int) $row->courseid,
                'option1' => (bool) $row->participantoption1,
                'option2' => (bool) $row->participantoption2,
                'option3' => (bool) $row->participantoption3,
            ];
        }
        return $result;
    }

    /**
     * Which learning paths have a host-course embedding in the given course.
     *
     * Lets enrol_adele's user_enrolment_deleted observer find affected
     * learning paths without reading mod_adele's {adele} table directly.
     *
     * @param int $courseid The host course id.
     * @return int[] Distinct learning path ids.
     */
    public static function get_learningpaths_embedded_in_course(int $courseid): array {
        global $DB;
        $ids = $DB->get_fieldset_select(
            'local_adele_host_courses',
            'DISTINCT learningpathid',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );
        return array_map('intval', $ids);
    }
}
