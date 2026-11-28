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
 * GDPR Provider
 *
 *
 * @package    local_adele
 * @copyright  2024 Wunderbyte GmbH
 * @author     Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

namespace local_adele\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\transform;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\manager;

/**
 * Class provider
 *
 *
 * @package   local_adele
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\core_user_data_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The initialized collection to add items to.
     * @return collection The collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_adele_learning_paths',
            [
                'name' => 'privacy:metadata:local_adele_learning_paths:name',
                'description' => 'privacy:metadata:local_adele_learning_paths:description',
                'createdby' => 'privacy:metadata:local_adele_learning_paths:createdby',
                'timecreated' => 'privacy:metadata:local_adele_learning_paths:timecreated',
                'timemodified' => 'privacy:metadata:local_adele_learning_paths:timemodified',
                'json' => 'privacy:metadata:local_adele_learning_paths:json',
            ],
            'privacy:metadata:local_adele_learning_paths'
        );

        $collection->add_database_table(
            'local_adele_path_user',
            [
                'user_id' => 'privacy:metadata:local_adele_path_user:user_id',
                'course_id' => 'privacy:metadata:local_adele_path_user:course_id',
                'learning_path_id' => 'privacy:metadata:local_adele_path_user:learning_path_id',
                'status' => 'privacy:metadata:local_adele_path_user:status',
                'createdby' => 'privacy:metadata:local_adele_path_user:createdby',
                'timecreated' => 'privacy:metadata:local_adele_path_user:timecreated',
                'timemodified' => 'privacy:metadata:local_adele_path_user:timemodified',
                'json' => 'privacy:metadata:local_adele_path_user:json',
            ],
            'privacy:metadata:local_adele_path_user'
        );

        $collection->add_database_table(
            'local_adele_lp_editors',
            [
                'userid' => 'privacy:metadata:local_adele_lp_editors:userid',
                'learningpathid' => 'privacy:metadata:local_adele_lp_editors:learningpathid',
            ],
            'privacy:metadata:local_adele_lp_editors'
        );

        $collection->add_database_table(
            'local_adele_activity',
            [
                'userid' => 'privacy:metadata:local_adele_activity:userid',
                'courseid' => 'privacy:metadata:local_adele_activity:courseid',
                'timeseen' => 'privacy:metadata:local_adele_activity:timeseen',
            ],
            'privacy:metadata:local_adele_activity'
        );

        return $collection;
    }

    /**
     * Returns all contexts that contain user information for the specified user.
     *
     * @param int $userid The user ID to search.
     * @return contextlist The list of contexts that contain user information.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // This plugin stores user data at the system context level.
        // Add system context if user has any data in any of the tables.
        $sql = "SELECT DISTINCT c.id
                  FROM {context} c
                 WHERE c.contextlevel = :contextlevel
                   AND (
                       EXISTS (SELECT 1 FROM {local_adele_learning_paths} lap WHERE lap.createdby = :userid1)
                       OR EXISTS (
                            SELECT 1 FROM {local_adele_path_user} lpu WHERE lpu.user_id = :userid2 OR lpu.createdby = :userid3
                       )
                       OR EXISTS (SELECT 1 FROM {local_adele_lp_editors} lpe WHERE lpe.userid = :userid4)
                       OR EXISTS (SELECT 1 FROM {local_adele_activity} laa WHERE laa.userid = :userid5)
                   )";

        $params = [
            'contextlevel' => CONTEXT_SYSTEM,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_system) {
            return;
        }

        // Users who created learning paths.
        $sql = "SELECT DISTINCT createdby as userid
                  FROM {local_adele_learning_paths}
                 WHERE createdby > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Users in path_user table (both user_id and createdby).
        $sql = "SELECT DISTINCT user_id as userid
                  FROM {local_adele_path_user}
                 WHERE user_id > 0";
        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT DISTINCT createdby as userid
                  FROM {local_adele_path_user}
                 WHERE createdby > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Users who are editors.
        $sql = "SELECT DISTINCT userid
                  FROM {local_adele_lp_editors}
                 WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);

        // Live activity (#14).
        $sql = "SELECT DISTINCT userid
                  FROM {local_adele_activity}
                 WHERE userid > 0";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        // Check if system context is in the list.
        $systemcontext = \context_system::instance();
        if (!in_array($systemcontext->id, $contextlist->get_contextids())) {
            return;
        }

        // Export data from local_adele_learning_paths.
        $learningpaths = $DB->get_records('local_adele_learning_paths', ['createdby' => $userid]);
        if (!empty($learningpaths)) {
            $data = [];
            foreach ($learningpaths as $path) {
                $data[] = (object) [
                    'name' => $path->name,
                    'description' => $path->description,
                    'timecreated' => $path->timecreated ? transform::datetime($path->timecreated) : null,
                    'timemodified' => $path->timemodified ? transform::datetime($path->timemodified) : null,
                    'json' => $path->json,
                ];
            }
            writer::with_context($systemcontext)->export_data(['learning_paths'], (object) ['paths' => $data]);
        }

        // Export data from local_adele_path_user where user is the participant.
        $pathusers = $DB->get_records('local_adele_path_user', ['user_id' => $userid]);
        if (!empty($pathusers)) {
            $data = [];
            foreach ($pathusers as $pathuser) {
                $data[] = (object) [
                    'learning_path_id' => $pathuser->learning_path_id,
                    'course_id' => $pathuser->course_id,
                    'status' => $pathuser->status,
                    'timecreated' => $pathuser->timecreated ? transform::datetime($pathuser->timecreated) : null,
                    'timemodified' => $pathuser->timemodified ? transform::datetime($pathuser->timemodified) : null,
                    'json' => $pathuser->json,
                ];
            }
            writer::with_context($systemcontext)->export_data(['learning_path_assignments'], (object) ['assignments' => $data]);
        }

        // Export data from local_adele_path_user where user is the creator.
        $pathusers = $DB->get_records('local_adele_path_user', ['createdby' => $userid]);
        if (!empty($pathusers)) {
            $data = [];
            foreach ($pathusers as $pathuser) {
                $data[] = (object) [
                    'learning_path_id' => $pathuser->learning_path_id,
                    'user_id' => $pathuser->user_id,
                    'course_id' => $pathuser->course_id,
                    'status' => $pathuser->status,
                    'timecreated' => $pathuser->timecreated ? transform::datetime($pathuser->timecreated) : null,
                    'timemodified' => $pathuser->timemodified ? transform::datetime($pathuser->timemodified) : null,
                ];
            }
            writer::with_context($systemcontext)->export_data(
                ['learning_path_assignments_created'],
                (object) ['created_assignments' => $data]
            );
        }

        // Export data from local_adele_lp_editors.
        $editors = $DB->get_records('local_adele_lp_editors', ['userid' => $userid]);
        if (!empty($editors)) {
            $data = [];
            foreach ($editors as $editor) {
                $data[] = (object) [
                    'learningpathid' => $editor->learningpathid,
                ];
            }
            writer::with_context($systemcontext)->export_data(
                ['learning_path_editor_permissions'],
                (object) ['permissions' => $data]
            );
        }

        // Export the live activity (#14): course and time last seen.
        $activity = $DB->get_records('local_adele_activity', ['userid' => $userid], 'courseid ASC');
        if (!empty($activity)) {
            $data = [];
            foreach ($activity as $row) {
                $data[] = (object) [
                    'courseid' => $row->courseid,
                    'timeseen' => transform::datetime($row->timeseen),
                ];
            }
            writer::with_context($systemcontext)->export_data(['live_activity'], (object) ['activity' => $data]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * The participations and editor rights of everybody go; the learning
     * paths stay - they are the site's content, embedded and worked on by
     * others - with their author fields anonymised (no owner until somebody
     * with local/adele:canmanage hands them on). ADELE's enrolments that
     * hung on the participations are taken back.
     *
     * @param \context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $pairs = $DB->get_records_sql(
            "SELECT DISTINCT " . $DB->sql_concat('learning_path_id', "'_'", 'user_id') . " AS pair,
                    learning_path_id, user_id
               FROM {local_adele_path_user}"
        );
        $DB->delete_records('local_adele_path_user');
        foreach ($pairs as $pair) {
            self::take_back_enrolments((int) $pair->learning_path_id, (int) $pair->user_id);
        }
        $DB->delete_records('local_adele_lp_editors');
        $DB->delete_records('local_adele_activity');
        $DB->set_field('local_adele_learning_paths', 'createdby', 0);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        if (empty($contextlist->count())) {
            return;
        }
        if (!in_array(\context_system::instance()->id, $contextlist->get_contextids())) {
            return;
        }
        self::delete_user((int) $contextlist->get_user()->id);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user((int) $userid);
        }
    }

    /**
     * Delete one person's data - and only theirs (decision 2026-10-17,
     * mod_adele #33).
     *
     * - Their own participations go, with ADELE's enrolments that hung on them.
     * - Their editor rights go.
     * - Learning paths they created stay: the most senior remaining editor
     *   takes them over (#571); without one the path is left without an
     *   owner (createdby = 0) and shown as such to managers.
     * - Participations of others that they created stay, anonymised.
     *
     * @param int $userid The person.
     * @return void
     */
    private static function delete_user(int $userid): void {
        global $DB;

        $DB->delete_records('local_adele_activity', ['userid' => $userid]);
        $lpids = $DB->get_fieldset_select('local_adele_path_user', 'learning_path_id', 'user_id = ?', [$userid]);
        $DB->delete_records('local_adele_path_user', ['user_id' => $userid]);
        foreach (array_unique(array_map('intval', $lpids)) as $lpid) {
            self::take_back_enrolments($lpid, $userid);
        }
        $DB->set_field('local_adele_path_user', 'createdby', 0, ['createdby' => $userid]);
        $DB->delete_records('local_adele_lp_editors', ['userid' => $userid]);
        foreach ($DB->get_records('local_adele_learning_paths', ['createdby' => $userid], '', 'id') as $path) {
            if (!\local_adele\ownership::handle_vanished_owner((int) $path->id, $userid)) {
                $DB->set_field('local_adele_learning_paths', 'createdby', 0, ['id' => $path->id]);
            }
        }
    }

    /**
     * Take back ADELE's enrolments of a person on a path whose participation
     * record is gone (target and host courses): without the record they
     * would be orphaned (enrolments exist only for an active participation).
     *
     * @param int $lpid The learning path.
     * @param int $userid The person.
     * @return void
     */
    private static function take_back_enrolments(int $lpid, int $userid): void {
        if (!class_exists('\enrol_adele\local\reconciler')) {
            return;
        }
        \enrol_adele\local\reconciler::purge_user($lpid, $userid);
        \enrol_adele\local\reconciler::purge_all_host_user($lpid, $userid);
    }
}
