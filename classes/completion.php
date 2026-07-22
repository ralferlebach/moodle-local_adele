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

use core\event\course_completed;
use local_adele\helper\user_path_relation;
use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External Service for local adele.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion {
    /**
     * Observer for the core course_completed event.
     *
     * When a Moodle course is completed, every active learning path of the
     * affected student that references this course in one of its nodes has to be
     * re-evaluated so that the corresponding node-completion criterion can turn
     * the node into "completed".
     *
     * Important: on \core\event\course_completed the affected student is carried
     * in $event->relateduserid. $event->userid is the ACTOR that caused the
     * completion (a grading teacher, an administrator or - most commonly - the
     * cron/system process aggregating the completion criteria) and must NOT be
     * used to look up the learning path owner.
     *
     * @param course_completed $event The Moodle course_completed event.
     * @return void
     */
    public static function completed(course_completed $event): void {
        // The student whose course was completed - NOT the acting user.
        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;

        // Nothing sensible to do without both a student and a course.
        if ($userid <= 0 || $courseid <= 0) {
            return;
        }

        $userpathrelation = new user_path_relation();
        $learningpaths = $userpathrelation->get_learning_paths($userid);

        if (empty($learningpaths)) {
            return;
        }

        foreach ($learningpaths as $learningpath) {
            $pathjson = json_decode($learningpath->json, true);

            // A single malformed or incomplete snapshot must not abort the
            // processing of the remaining (valid) learning paths.
            if (
                !is_array($pathjson) ||
                empty($pathjson['tree']['nodes']) ||
                !is_array($pathjson['tree']['nodes'])
            ) {
                continue;
            }

            foreach ($pathjson['tree']['nodes'] as $node) {
                $courseids = $node['data']['course_node_id'] ?? [];

                if (!is_array($courseids)) {
                    $courseids = [$courseids];
                }

                // The stored course_node_id values may be strings or integers
                // depending on the JSON origin - normalise both sides for a
                // type-safe match.
                $courseids = array_map('intval', $courseids);

                if (!in_array($courseid, $courseids, true)) {
                    continue;
                }

                // Hand the decoded snapshot to the central update service, which
                // re-evaluates every node of the path against the current state.
                $learningpath->json = $pathjson;
                learning_path_update::trigger_user_path_update($learningpath);

                // The recompute already covers all nodes of this path, so a
                // single trigger per learning path is sufficient even if the
                // course is referenced by more than one node.
                break;
            }
        }
    }

    /**
     * Observer for course completed
     *
     * @param array $haystack
     * @param string $needle
     * @param string $key
     */
    public static function searchnestedarray($haystack, $needle, $key) {
        foreach ($haystack as $item) {
            if (isset($item[$key]) && $item[$key] === $needle) {
                return $item;
            }
        }
        return null;
    }
}
