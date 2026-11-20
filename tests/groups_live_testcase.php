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
 * Fixtures for the group, non-learner, live and counter tests (#12 - #17).
 *
 * A host course embeds one path of three nodes:
 *   n1  course c1
 *   n2  stacked: courses c2 and c3
 *   n3  course c1 again (a course used in two nodes)
 * The learners' snapshots complete the nodes given per learner.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class groups_live_testcase extends \advanced_testcase {
    /** @var \stdClass The host course. */
    protected $host;
    /** @var \stdClass[] Node courses c1, c2, c3. */
    protected $courses = [];
    /** @var int The path. */
    protected $lpid;

    /**
     * The path, its courses and the host course.
     *
     * @return void
     */
    protected function make_world(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $this->host = $gen->create_course(['shortname' => 'HOST']);
        foreach (['c1', 'c2', 'c3'] as $name) {
            $this->courses[$name] = $gen->create_course(['shortname' => $name]);
        }
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Groups and live',
            'json' => json_encode(['tree' => ['nodes' => $this->nodes(), 'edges' => []]]),
            'visibility' => 1,
            'createdby' => 2,
        ]);
    }

    /**
     * The three nodes.
     *
     * @return array
     */
    protected function nodes(): array {
        $c = fn($name) => (string) $this->courses[$name]->id;
        return [
            ['id' => 'n1', 'type' => 'custom', 'parentCourse' => ['starting_node'], 'childCourse' => ['n2'],
                'data' => ['course_node_id' => [$c('c1')], 'fullname' => 'Node 1']],
            ['id' => 'n2', 'type' => 'orcourses', 'parentCourse' => ['n1'], 'childCourse' => ['n3'],
                'data' => ['course_node_id' => [$c('c2'), $c('c3')], 'fullname' => 'Node 2']],
            ['id' => 'n3', 'type' => 'custom', 'parentCourse' => ['n2'], 'childCourse' => [],
                'data' => ['course_node_id' => [$c('c1')], 'fullname' => 'Node 3']],
        ];
    }

    /**
     * Embed the path in the host course.
     *
     * @param int $groupmode NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS.
     * @param array $options Further fields (userlist, livemarkers, livewindow, groupingid).
     * @return \stdClass The activity (cmid in ->cmid).
     */
    protected function embed(int $groupmode, array $options = []): \stdClass {
        return $this->getDataGenerator()->create_module('adele', $options + [
            'course' => $this->host->id,
            'learningpathid' => $this->lpid,
            'view' => 1,
            'userlist' => 1,
            'participantslist' => '0',
            'groupmode' => $groupmode,
        ]);
    }

    /**
     * A learner of the host course with a snapshot completing some nodes.
     *
     * @param string $lastname
     * @param array $completed Node ids completed.
     * @param array $groups Groups to join.
     * @return \stdClass
     */
    protected function learner(string $lastname, array $completed = [], array $groups = []): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_and_enrol($this->host, 'student', ['lastname' => $lastname, 'firstname' => 'L']);
        $relation = [];
        foreach (['n1', 'n2', 'n3'] as $id) {
            $relation[$id] = ['completionnode' => ['valid' => in_array($id, $completed, true)]];
        }
        $json = json_encode(['tree' => ['nodes' => $this->nodes(), 'edges' => []], 'user_path_relation' => $relation]);
        $existing = $DB->get_record('local_adele_path_user', ['user_id' => $user->id, 'learning_path_id' => $this->lpid]);
        if ($existing) {
            $DB->update_record('local_adele_path_user', (object) ['id' => $existing->id, 'json' => $json, 'status' => 'active']);
        } else {
            $DB->insert_record('local_adele_path_user', (object) [
                'user_id' => $user->id, 'course_id' => $this->host->id, 'learning_path_id' => $this->lpid,
                'status' => 'active', 'json' => $json, 'createdby' => $user->id,
            ]);
        }
        foreach ($groups as $group) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        return $user;
    }

    /**
     * A group of the host course.
     *
     * @param string $name
     * @return \stdClass
     */
    protected function group(string $name): \stdClass {
        return $this->getDataGenerator()->create_group(['courseid' => $this->host->id, 'name' => $name]);
    }

    /**
     * A teacher of the host course, optionally in groups.
     *
     * @param string $role editingteacher or teacher.
     * @param array $groups
     * @return \stdClass
     */
    protected function teacher(string $role = 'editingteacher', array $groups = []): \stdClass {
        $user = $this->getDataGenerator()->create_and_enrol($this->host, $role);
        foreach ($groups as $group) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        return $user;
    }

    /**
     * The module context id of an activity.
     *
     * @param \stdClass $activity
     * @return int
     */
    protected function ctx(\stdClass $activity): int {
        return (int) \context_module::instance($activity->cmid)->id;
    }

    /**
     * Record that a person was seen in a course at a time.
     *
     * @param \stdClass $user
     * @param string $course c1, c2, c3.
     * @param int $time
     * @return void
     */
    protected function seen(\stdClass $user, string $course, int $time): void {
        global $DB;
        $courseid = (int) $this->courses[$course]->id;
        $row = $DB->get_record('local_adele_activity', ['userid' => $user->id, 'courseid' => $courseid]);
        if ($row) {
            $DB->set_field('local_adele_activity', 'timeseen', $time, ['id' => $row->id]);
        } else {
            $DB->insert_record('local_adele_activity', (object) [
                'userid' => $user->id,
                'courseid' => $courseid,
                'timeseen' => $time,
            ]);
        }
    }
}
