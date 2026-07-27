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

namespace local_adele;

use local_adele\event\user_path_updated;
use local_adele\helper\user_path_relation;
use context_system;
use core_completion\progress;
use mod_adele_observer;
use stdClass;

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_update {
    /**
     * All courses
     * @var array
     */
    public static $courses = [];

    /**
     * Return single course from id
     *
     * @param int $courseid
     */
    public static function get_course($courseid) {
        if (!isset(self::$courses[$courseid])) {
            $course = get_course($courseid);
            self::$courses[$courseid] = $course;
        }
        return self::$courses[$courseid];
    }

    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function updated_learning_path($event) {
        // Get the user path relations.
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_user_path_relations($event->other['learningpathid']);
        foreach ($records as $userpath) {
            $userpath->json = self::passnodevalues($event->other['json'], $userpath->json, $userpath->user_id);
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Observer for course completed
     *
     * @param object $event
     */
    public static function user_views_learning_path($event) {

        $userpathrelation = new user_path_relation();
        $eventdata = $event->get_data();
        $records = $userpathrelation->get_active_user_path_relation($eventdata['userid'], $eventdata['courseid']);
        foreach ($records as $userpath) {
            self::trigger_user_path_update($userpath);
        }
    }


    /**
     * Triggers an update event for a user's learning path.
     *
     * @param object $userpath The user path object containing path information to be updated
     * @return void
     */
    public static function trigger_user_path_update($userpath) {
        $eventsingle = user_path_updated::create([
            'objectid' => $userpath->id,
            'context' => context_system::instance(),
            'other' => [
                'userpath' => $userpath,
            ],
        ]);
        $eventsingle->trigger();
    }

    /**
     * Observer entry point for the mod_quiz attempt_submitted event.
     *
     * The affected student is carried in $event->relateduserid. $event->userid
     * is the acting user (e.g. a teacher submitting on behalf of a student) and
     * must NOT be used to look up the learning-path owner.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function quiz_finished(\mod_quiz\event\attempt_submitted $event) {
        self::recompute_quiz_paths(
            (int) $event->relateduserid,
            (int) $event->other['quizid']
        );
    }

    /**
     * Recompute every active learning path of a user that references a quiz.
     *
     * Event-independent so that all quiz change events (submission, regrading,
     * manual grading, deletion, reopening) can share the same recompute path.
     *
     * @param int $userid The affected student.
     * @param int $quizid The quiz instance id.
     */
    public static function recompute_quiz_paths(int $userid, int $quizid) {
        self::recompute_activity_paths($userid, 'modquiz', 'quizid', $quizid);
    }

    /**
     * Recompute every active learning path of a user that references a CAT quiz.
     *
     * @param int $userid The affected student.
     * @param int $componentid The adaptivequiz instance id.
     */
    public static function recompute_catquiz_paths(int $userid, int $componentid) {
        self::recompute_activity_paths($userid, 'catquiz', 'componentid', $componentid);
    }

    /**
     * Finished CAT quiz.
     *
     * @param object $event
     */
    public static function catquiz_finished($event) {
        $cm = get_coursemodule_from_id(null, $event->contextinstanceid);
        if (!$cm) {
            return;
        }
        self::recompute_catquiz_paths((int) $event->userid, (int) $cm->instance);
    }

    /**
     * Recompute the active learning paths of a user that reference a given
     * activity instance in a completion condition.
     *
     * The affected paths are found by structurally decoding the
     * stored snapshot and comparing ids type-safely, instead of a fragile LIKE
     * on the serialized JSON (which only matched the quoted-string form and
     * missed integer-serialised ids). Each matching path is recomputed at most
     * once; a single malformed snapshot never aborts the remaining paths.
     *
     * @param int $userid The affected student.
     * @param string $conditionlabel The completion condition label ('modquiz'|'catquiz').
     * @param string $valuekey The id key inside data.value ('quizid'|'componentid').
     * @param int $instanceid The activity instance id to match.
     */
    private static function recompute_activity_paths(
        int $userid,
        string $conditionlabel,
        string $valuekey,
        int $instanceid
    ) {
        if ($userid <= 0 || $instanceid <= 0) {
            return;
        }
        $userpathrelation = new user_path_relation();
        $records = $userpathrelation->get_learning_paths($userid);
        foreach ($records as $userpath) {
            $decoded = json_decode($userpath->json, true);
            if (
                !is_array($decoded) ||
                empty($decoded['tree']['nodes']) ||
                !is_array($decoded['tree']['nodes'])
            ) {
                continue;
            }
            if (!self::path_references_activity($decoded['tree']['nodes'], $conditionlabel, $valuekey, $instanceid)) {
                continue;
            }
            $userpath->json = $decoded;
            $eventsingle = user_path_updated::create([
                'objectid' => $userpath->id,
                'context' => context_system::instance(),
                'other' => [
                    'userpath' => $userpath,
                ],
            ]);
            $eventsingle->trigger();
        }
    }

    /**
     * Whether any node of a path references the given activity instance in a
     * completion condition, comparing ids type-safely (#497).
     *
     * @param array $nodes The tree nodes of the snapshot.
     * @param string $conditionlabel The completion condition label to match.
     * @param string $valuekey The id key inside data.value.
     * @param int $instanceid The activity instance id to match.
     * @return bool
     */
    private static function path_references_activity(
        array $nodes,
        string $conditionlabel,
        string $valuekey,
        int $instanceid
    ) {
        foreach ($nodes as $node) {
            if (empty($node['completion']['nodes']) || !is_array($node['completion']['nodes'])) {
                continue;
            }
            foreach ($node['completion']['nodes'] as $condition) {
                if (($condition['data']['label'] ?? null) !== $conditionlabel) {
                    continue;
                }
                $value = $condition['data']['value'][$valuekey] ?? null;
                if ($value !== null && (int) $value === $instanceid) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Observer for course completed
     *
     * @param string $newtree
     * @param string $oldtree
     * @param string $userid
     * @return array
     */
    public static function passnodevalues($newtree, $oldtree, $userid) {
        $oldpath = json_decode($oldtree, true);
        $userpathjson = json_decode($newtree, true);
        $userpathjson['user_path_relation'] = $oldpath['user_path_relation'];
        $oldvalues = [];

        foreach ($oldpath['tree']['nodes'] as $node) {
            // Null-coalesce every read: nodes legitimately omit these keys (e.g. a
            // node with no completion/restriction or manual condition), and the
            // raw accesses emitted a cascade of "Undefined array key" / "access
            // array offset on null" warnings on every learning-path edit.
            $oldvalues[$node['id']] = [
                'firstcompleted' => $node['firstcompleted'] ?? false,
                'manualcompletion' => $node['data']['manualcompletion'] ?? null,
                'manualcompletionvalue' => $node['data']['manualcompletionvalue'] ?? null,
                'manualrestriction' => $node['data']['manualrestriction'] ?? null,
                'manualrestrictionvalue' => $node['data']['manualrestrictionvalue'] ?? null,
                'mastercompletion' => $node['data']['completion']['master']['completion'] ?? null,
                'masterrescrtriction' => $node['data']['completion']['master']['restriction'] ?? null,
                'first_enrolled' => $node['data']['first_enrolled'] ?? null,
            ];
        }

        foreach ($userpathjson['tree']['nodes'] as &$node) {
            if (isset($oldvalues[$node['id']]) && $oldvalues[$node['id']]['mastercompletion'] == true) {
                $node['data']['completion']['master']['completion'] = true;
            }
            if (isset($oldvalues[$node['id']]) && $oldvalues[$node['id']]['masterrescrtriction'] == true) {
                $node["data"]["completion"]["master"]["restriction"] = true;
            }
            if (isset($oldvalues[$node['id']]) && $oldvalues[$node['id']]['firstcompleted'] == true) {
                $node['firstcompleted'] = true;
            }
            if (isset($oldvalues[$node['id']]) && $oldvalues[$node['id']]['first_enrolled'] == true) {
                $node['data']['first_enrolled'] = $oldvalues[$node['id']]['first_enrolled'];
            }
            $manualrestriction = false;
            foreach (($node['restriction']['nodes'] ?? []) as $restrictionnode) {
                // Feedback nodes carry no 'label'; coalesce to avoid a warning.
                if (($restrictionnode['data']['label'] ?? '') == 'manual') {
                    $manualrestriction = true;
                }
            }
            $manualcompletion = false;
            foreach (($node['completion']['nodes'] ?? []) as $completionnode) {
                if (($completionnode['data']['label'] ?? '') == 'manual') {
                    $manualcompletion = true;
                }
            }
            if (isset($oldvalues[$node['id']])) {
                if ($manualrestriction) {
                    $node['data']['manualrestriction'] = $oldvalues[$node['id']]['manualrestriction'];
                    $node['data']['manualrestrictionvalue'] = $oldvalues[$node['id']]['manualrestrictionvalue'];
                }
                if ($manualcompletion) {
                    $node['data']['manualcompletion'] = $oldvalues[$node['id']]['manualcompletion'];
                    $node['data']['manualcompletionvalue'] = $oldvalues[$node['id']]['manualcompletionvalue'];
                }
            }
            $nodeobj = json_decode(json_encode($node));
            $node = json_decode(json_encode(learning_paths::checknodeprogression($nodeobj, $userid)), true);
        }
        return $userpathjson;
    }

    /**
     * Trigger user path subscription
     *
     * @param string $learningpathid
     */
    public static function trigger_user_subscription($learningpathid) {
        global $DB, $USER;
        $adeleactivities = $DB->get_records('adele', ['learningpathid' => $learningpathid]);
        $data = (object) [
          'courseid' => 0,
          'userid' => $USER->id,
        ];
        foreach ($adeleactivities as $adeleactivity) {
            $adeleactivity->participantslist = explode(',', $adeleactivity->participantslist);
            $data->courseid = $adeleactivity->course;
            foreach ($adeleactivity->participantslist as $participantslist) {
                if ($participantslist == '1') {
                    mod_adele_observer::enroll_all_participants($adeleactivity, $data, true);
                } else if ($participantslist == '2') {
                    mod_adele_observer::enroll_starting_nodes_participants($adeleactivity, $data, true);
                }
            }
        }
    }

    /**
     * Trigger user path subscription
     *
     * @param string $lpid
     * @param bool $visibility
     */
    public static function update_visiblity($lpid, $visibility) {
        global $DB;
        $data = new stdClass();
        $data->id = $lpid;
        $data->visibility = $visibility;
        $result = $DB->update_record('local_adele_learning_paths', $data);
        return ['success' => $result];
    }

    /**
     * Observer for course completed
     *
     * @param string $learningpathid
     * @param string $userid
     * @param string $nodeid
     * @param string $animations
     * @return array
     */
    public static function update_animations(
        $learningpathid,
        $userid,
        $nodeid,
        $animations
    ) {
        global $DB;
        $record = $DB->get_record(
            'local_adele_path_user',
            [
            'user_id' => $userid,
            'learning_path_id' => $learningpathid,
            'status' => 'active',
            ],
            'id, json',
        );
        $animations = (array) json_decode($animations);
        $json = json_decode($record->json);
        foreach ($json->tree->nodes as $node) {
            if ($node->id == $nodeid) {
                $node->data->animations = $animations;
                break;
            }
        }
        $DB->update_record(
            'local_adele_path_user',
            ['id' => $record->id, 'json' => json_encode($json)]
        );
        return ['success' => true];
    }
}
