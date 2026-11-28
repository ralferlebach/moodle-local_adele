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
 * Live activity markers and node counters (#14, #15): one logic for both.
 *
 * What counts as activity: Moodle recorded on the server that a learner
 * opened a course (\core\event\course_viewed) or one of its activities
 * (every \core\event\course_module_viewed). Nothing else - polling, this
 * snapshot or a teacher's view create no activity. Activity is not presence.
 *
 * Kept: one row per learner and course, the time of the last such event
 * (local_adele_activity), only for people who are on a learning path, only
 * for the retention (setting liveretention, task purge_live_activity). The
 * log store is not read: no scans of the log table.
 *
 * Per node of the path, for the learners of the activity's group (cohort):
 *   active     a learner seen in one of the node's courses within the
 *              activity's window (now - window <= timeseen); shown on EVERY
 *              node holding such a course;
 *   inactive   a learner not active anywhere, shown on the node of the last
 *              course seen; never seen: no marker. When that is not one
 *              node - several courses seen at the same latest second, or the
 *              last course sits in several nodes - no node is picked: the
 *              marker is shown on EVERY such node and says that the last
 *              activity is not unique and on how many nodes it is shown
 *              ('ambiguous', #14 "Mehrdeutigkeitsdarstellung ohne
 *              willkürliche Auswahl", re-audit 2026-11-21; it used to pick
 *              the lower course id and the first node);
 *   completed  a learner whose snapshot has the node completed (the measure
 *              of the participant list), independent of activity.
 * A person counts once per node, also on stacked nodes with several courses.
 * Switched off in the activity, nothing about activity is computed or sent;
 * the completed counter stays.
 *
 * @package     local_adele
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class live_activity {
    /** @var array Site settings: name => [default, min, max]. */
    public const BOUNDS = [
        // Seconds between two snapshots of an open teachers' view.
        'livepollinterval' => [30, 10, 300],
        // Days a record of activity is kept.
        'liveretention' => [30, 1, 365],
    ];

    /** @var int Markers drawn on a node; the rest is "+N". */
    public const MAXMARKERS = 5;

    /** @var int Seconds within which a second event of the same course is not written again. */
    public const THROTTLE = 10;

    /**
     * A site setting within its bounds.
     *
     * @param string $name A key of BOUNDS.
     * @return int
     */
    public static function setting(string $name): int {
        [$default, $min, $max] = self::BOUNDS[$name];
        $value = get_config('local_adele', $name);
        if ($value === false || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    /**
     * Now, from core's clock.
     *
     * @return int
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Observer: a course or one of its activities was opened.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function observe(\core\event\base $event): void {
        global $DB;
        $userid = (int) $event->userid;
        $courseid = (int) $event->courseid;
        $aboutanother = !empty($event->relateduserid) && (int) $event->relateduserid !== $userid;
        if ($userid <= 0 || $courseid <= SITEID || isguestuser($userid) || $aboutanother) {
            return;
        }
        // Only people on a learning path are recorded at all.
        if (!$DB->record_exists('local_adele_path_user', ['user_id' => $userid, 'status' => 'active'])) {
            return;
        }
        $time = (int) $event->timecreated;
        $row = $DB->get_record('local_adele_activity', ['userid' => $userid, 'courseid' => $courseid], 'id, timeseen');
        if (!$row) {
            try {
                $DB->insert_record('local_adele_activity', (object) [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'timeseen' => $time,
                ]);
            } catch (\dml_write_exception $e) {
                // A parallel request wrote it first; its time is as good.
                return;
            }
        } else if ($time > (int) $row->timeseen + self::THROTTLE) {
            $DB->set_field('local_adele_activity', 'timeseen', $time, ['id' => $row->id]);
        }
    }

    /**
     * Remove records older than the retention.
     *
     * @return int The number removed.
     */
    public static function purge(): int {
        global $DB;
        $before = self::now() - self::setting('liveretention') * DAYSECS;
        $count = $DB->count_records_select('local_adele_activity', 'timeseen < ?', [$before]);
        $DB->delete_records_select('local_adele_activity', 'timeseen < ?', [$before]);
        return $count;
    }

    /**
     * The nodes of a path and their courses, in the order of the path.
     *
     * @param int $learningpathid
     * @return array list of ['id' => nodeid, 'courses' => [courseid, ...]]
     */
    public static function path_nodes(int $learningpathid): array {
        global $DB;
        $json = json_decode((string) $DB->get_field('local_adele_learning_paths', 'json', ['id' => $learningpathid]));
        $nodes = [];
        foreach ($json->tree->nodes ?? [] as $node) {
            $id = (string) ($node->id ?? '');
            if ($id === '' || strpos($id, '_module') !== false || ($node->type ?? '') === 'dropzone') {
                continue;
            }
            $courses = array_values(array_unique(array_filter(array_map('intval', (array) ($node->data->course_node_id ?? [])))));
            $nodes[] = ['id' => $id, 'courses' => $courses];
        }
        return $nodes;
    }

    /**
     * Who is where - the data behind markers, counters and name lists.
     *
     * @param array $contract The teacher's contract (cohort::contract()).
     * @return array enabled, window, nodes (nodeid => [completed, active, inactive] lists of userids),
     *     seen (userid => time last seen), ambiguous (userid => nodes the inactive marker is on, if more
     *     than one), learners (userid => record)
     */
    public static function compute(array $contract): array {
        global $DB;
        $enabled = !empty($contract['live']['enabled']);
        $window = (int) $contract['live']['window'];
        $learners = cohort::learners_of_contract($contract);
        $nodes = self::path_nodes((int) $contract['learningpathid']);
        $out = [];
        foreach ($nodes as $node) {
            $out[$node['id']] = ['completed' => [], 'active' => [], 'inactive' => []];
        }
        foreach ($learners as $userid => $record) {
            $json = json_decode((string) $record->json);
            foreach ($nodes as $node) {
                if (cohort::node_completed($json, $node['id'])) {
                    $out[$node['id']]['completed'][] = (int) $userid;
                }
            }
        }
        $seen = [];
        $ambiguous = [];
        if ($enabled && $learners && $nodes) {
            $courses = array_values(array_unique(array_merge(...array_column($nodes, 'courses'))));
            $rows = [];
            if ($courses) {
                [$usql, $uparams] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED, 'lu');
                [$csql, $cparams] = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED, 'lc');
                $rows = $DB->get_records_sql(
                    "SELECT id, userid, courseid, timeseen
                       FROM {local_adele_activity}
                      WHERE userid {$usql} AND courseid {$csql}",
                    $uparams + $cparams
                );
            }
            $byuser = [];
            foreach ($rows as $row) {
                $byuser[(int) $row->userid][(int) $row->courseid] = (int) $row->timeseen;
            }
            $since = self::now() - $window;
            foreach ($byuser as $userid => $times) {
                $activecourses = array_keys(array_filter($times, static fn($t) => $t >= $since));
                $seen[$userid] = max($times);
                if ($activecourses) {
                    foreach ($nodes as $node) {
                        if (array_intersect($node['courses'], $activecourses)) {
                            $out[$node['id']]['active'][] = $userid;
                        }
                    }
                    continue;
                }
                // Not active: the node(s) of the last course(s) seen. Every
                // course seen at the latest time, every node holding one of
                // them - no arbitrary pick among them.
                $latest = max($times);
                $lastcourses = array_keys(array_filter($times, static fn($t) => $t === $latest));
                $onnodes = 0;
                foreach ($nodes as $node) {
                    if (array_intersect($node['courses'], $lastcourses)) {
                        $out[$node['id']]['inactive'][] = $userid;
                        $onnodes++;
                    }
                }
                if ($onnodes > 1) {
                    $ambiguous[$userid] = $onnodes;
                }
            }
        }
        return [
            'enabled' => $enabled,
            'window' => $window,
            'nodes' => $out,
            'seen' => $seen,
            'ambiguous' => $ambiguous,
            'learners' => $learners,
        ];
    }

    /**
     * The snapshot a teachers' view polls: counters and markers per node.
     *
     * @param array $contract The teacher's contract.
     * @return array enabled, window, pollinterval, servertime, nodes (list)
     */
    public static function snapshot(array $contract): array {
        global $DB, $PAGE;
        $data = self::compute($contract);
        $context = \context::instance_by_id($contract['contextid']);
        $shown = [];
        foreach ($data['nodes'] as $nodeid => $sets) {
            $active = $sets['active'];
            usort($active, static fn($a, $b) => [$data['seen'][$b] ?? 0, $a] <=> [$data['seen'][$a] ?? 0, $b]);
            $inactive = $sets['inactive'];
            usort($inactive, static fn($a, $b) => [$data['seen'][$b] ?? 0, $a] <=> [$data['seen'][$a] ?? 0, $b]);
            $data['nodes'][$nodeid]['markers'] = array_slice(
                array_merge(
                    array_map(static fn($id) => [$id, 'active'], $active),
                    array_map(static fn($id) => [$id, 'inactive'], $inactive)
                ),
                0,
                self::MAXMARKERS
            );
            foreach ($data['nodes'][$nodeid]['markers'] as [$id]) {
                $shown[$id] = true;
            }
        }
        $users = [];
        if ($shown) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($shown), SQL_PARAMS_NAMED, 'mk');
            $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
            $users = $DB->get_records_sql("SELECT {$fields} FROM {user} u WHERE u.id {$insql}", $params);
        }
        $viewfull = has_capability('moodle/site:viewfullnames', $context);
        $nodes = [];
        foreach ($data['nodes'] as $nodeid => $sets) {
            $markers = [];
            foreach ($sets['markers'] as [$id, $state]) {
                if (!isset($users[$id])) {
                    continue;
                }
                $picture = new \user_picture($users[$id]);
                $picture->size = 35;
                $markers[] = [
                    'userid' => (int) $id,
                    'fullname' => fullname($users[$id], $viewfull),
                    'avatar' => $picture->get_url($PAGE)->out(false),
                    'state' => $state,
                    'lastseen' => (int) ($data['seen'][$id] ?? 0),
                    // Inactive and the last activity is not one node: on how many it is shown.
                    'ambiguous' => $state === 'inactive' ? (int) ($data['ambiguous'][$id] ?? 0) : 0,
                ];
            }
            $total = count($sets['active']) + count($sets['inactive']);
            $nodes[] = [
                'nodeid' => (string) $nodeid,
                'completed' => count($sets['completed']),
                'active' => $data['enabled'] ? count($sets['active']) : null,
                'markers' => $data['enabled'] ? $markers : [],
                'moremarkers' => $data['enabled'] ? max(0, $total - count($markers)) : 0,
            ];
        }
        return [
            'enabled' => $data['enabled'],
            'window' => $data['window'],
            'pollinterval' => self::setting('livepollinterval'),
            'servertime' => self::now(),
            'nodes' => $nodes,
        ];
    }

    /**
     * The names behind a counter of a node, a page at a time.
     *
     * @param array $contract The teacher's contract.
     * @param string $nodeid A node of the path.
     * @param string $kind 'active' or 'completed'.
     * @param int $page From 0.
     * @param int $perpage Names per page.
     * @return array total, page, perpage, people (list of userid, fullname)
     * @throws \invalid_parameter_exception An unknown node, or 'active' while the markers are off.
     */
    public static function people(array $contract, string $nodeid, string $kind, int $page, int $perpage): array {
        $data = self::compute($contract);
        if (!isset($data['nodes'][$nodeid])) {
            throw new \invalid_parameter_exception('No such node in this learning path.');
        }
        if ($kind === 'active' && !$data['enabled']) {
            throw new \invalid_parameter_exception('Live activity is switched off in this activity.');
        }
        $ids = $kind === 'active' ? $data['nodes'][$nodeid]['active'] : $data['nodes'][$nodeid]['completed'];
        $context = \context::instance_by_id($contract['contextid']);
        $viewfull = has_capability('moodle/site:viewfullnames', $context);
        $people = [];
        foreach ($ids as $id) {
            $record = $data['learners'][$id];
            $people[] = [
                'userid' => (int) $id,
                'fullname' => fullname($record, $viewfull),
                'sort' => \core_text::strtolower(($record->lastname ?? '') . ' ' . ($record->firstname ?? '')),
            ];
        }
        usort($people, static fn($a, $b) => [$a['sort'], $a['userid']] <=> [$b['sort'], $b['userid']]);
        $slice = array_slice($people, $page * $perpage, $perpage);
        return [
            'total' => count($people),
            'page' => $page,
            'perpage' => $perpage,
            'people' => array_map(static fn($p) => ['userid' => $p['userid'], 'fullname' => $p['fullname']], $slice),
        ];
    }
}
