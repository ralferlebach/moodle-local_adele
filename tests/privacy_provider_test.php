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

use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use local_adele\privacy\provider;

/**
 * Privacy provider of local_adele: export and deletion for creators,
 * participants and editors (mod_adele #33; decision 2026-10-17).
 *
 * A deletion removes the person's own data and nothing else: their
 * participations (with ADELE's enrolments that hung on them) and editor
 * rights go; learning paths they created stay and pass to the most senior
 * remaining editor (#571), otherwise they are left without an owner;
 * participations of others they created stay, anonymised. No reference is
 * left dangling afterwards.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\privacy\provider
 * @group      adele_release
 */
final class privacy_provider_test extends provider_testcase {
    /** @var \stdClass[] People: creator, editor, learner, other (a second learner). */
    private array $people = [];

    /** @var int The learning path. */
    private int $lpid = 0;

    /** @var \stdClass The host course. */
    private \stdClass $host;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        foreach (['creator', 'editor', 'learner', 'other'] as $key) {
            $this->people[$key] = $gen->create_user(['username' => "priv$key"]);
        }
        $node = $gen->create_course();
        $this->host = $gen->create_course();
        $nodes = [['id' => 'dndnode_1', 'type' => 'custom', 'parentCourse' => ['starting_node'],
            'data' => ['course_node_id' => [(int) $node->id]]]];
        $this->lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Privacy path', 'description' => '', 'timecreated' => time(), 'timemodified' => time(),
            'createdby' => $this->people['creator']->id,
            'json' => json_encode(['tree' => ['nodes' => $nodes, 'edges' => []]]),
        ]);
        foreach (['creator', 'editor'] as $key) {
            $DB->insert_record('local_adele_lp_editors', (object) [
                'learningpathid' => $this->lpid, 'userid' => $this->people[$key]->id,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        foreach (['learner', 'other'] as $key) {
            $gen->enrol_user($this->people[$key]->id, $node->id, 'student');
        }
        // The creator saves the embedding: the subscriptions it makes are
        // created by them.
        $this->setUser($this->people['creator']);
        $gen->create_module('adele', [
            'course' => $this->host->id, 'learningpathid' => $this->lpid, 'participantslist' => [3],
            'hostenrolmentmode' => 'visible',
        ]);
        $this->runAdhocTasks();
        $this->setAdminUser();
    }

    /**
     * Precondition, shared by the deletion tests.
     *
     * @return void
     */
    private function assert_precondition(): void {
        global $DB;
        foreach (['learner', 'other'] as $key) {
            $this->assertTrue($DB->record_exists('local_adele_path_user', [
                'user_id' => $this->people[$key]->id, 'learning_path_id' => $this->lpid,
                'createdby' => $this->people['creator']->id,
            ]), "$key subscribed, the record created by the creator");
            $this->assertTrue($this->host_enrolled($key), "$key in the host course through ADELE");
        }
    }

    /**
     * Whether a person has an ADELE enrolment in the host course.
     *
     * @param string $key
     * @return bool
     */
    private function host_enrolled(string $key): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :c AND e.enrol = 'adele' AND ue.userid = :u",
            ['c' => $this->host->id, 'u' => $this->people[$key]->id]
        );
    }

    /**
     * No dangling reference: every ADELE enrolment has a participation, every
     * activity a path, every editor row a path.
     *
     * @return void
     */
    private function assert_nothing_dangles(): void {
        global $DB;
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele' AND NOT EXISTS (
                    SELECT 1 FROM {local_adele_path_user} pu
                     WHERE pu.user_id = ue.userid AND pu.learning_path_id = e.customint1)"
        ), 'An ADELE enrolment without a participation.');
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {adele} a
              WHERE a.learningpathid > 0 AND NOT EXISTS (
                    SELECT 1 FROM {local_adele_learning_paths} lp WHERE lp.id = a.learningpathid)"
        ), 'An activity pointing at a missing path.');
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_adele_lp_editors} e
              WHERE NOT EXISTS (SELECT 1 FROM {local_adele_learning_paths} lp WHERE lp.id = e.learningpathid)"
        ), 'An editor row of a missing path.');
    }

    /**
     * The three tables are declared.
     *
     * @return void
     */
    public function test_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_adele'));
        $tables = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        sort($tables);
        $this->assertSame(
            ['local_adele_activity', 'local_adele_learning_paths', 'local_adele_lp_editors', 'local_adele_path_user'],
            $tables
        );
    }

    /**
     * Everybody with data has the system context; the user list names them.
     *
     * @return void
     */
    public function test_contexts_and_users(): void {
        $system = context_system::instance();
        foreach ($this->people as $key => $person) {
            $this->assertSame([(int) $system->id], array_map('intval', provider::get_contexts_for_userid((int) $person->id)
                ->get_contextids()), $key);
        }
        $nobody = $this->getDataGenerator()->create_user();
        $this->assertSame([], provider::get_contexts_for_userid((int) $nobody->id)->get_contextids());

        $list = new userlist($system, 'local_adele');
        provider::get_users_in_context($list);
        $ids = array_map('intval', $list->get_userids());
        foreach ($this->people as $key => $person) {
            $this->assertContains((int) $person->id, $ids, $key);
        }
        $this->assertNotContains((int) $nobody->id, $ids);
    }

    /**
     * Export: the creator their path and the participations they created,
     * the learner their own participation, the editor their right.
     *
     * @return void
     */
    public function test_export(): void {
        $system = context_system::instance();
        $expected = [
            'creator' => ['learning_paths', 'learning_path_assignments_created', 'learning_path_editor_permissions'],
            'learner' => ['learning_path_assignments'],
            'editor' => ['learning_path_editor_permissions'],
        ];
        foreach ($expected as $key => $folders) {
            writer::reset();
            $this->export_context_data_for_user((int) $this->people[$key]->id, $system, 'local_adele');
            $writer = writer::with_context($system);
            $this->assertTrue($writer->has_any_data(), $key);
            foreach ($folders as $folder) {
                $this->assertNotEmpty($writer->get_data([$folder]), "$key: $folder");
            }
            foreach (array_diff(array_merge(...array_values($expected)), $folders) as $folder) {
                $this->assertEmpty($writer->get_data([$folder]), "$key: no $folder");
            }
        }
        writer::reset();
        $this->export_context_data_for_user((int) $this->people['learner']->id, $system, 'local_adele');
        $data = writer::with_context($system)->get_data(['learning_path_assignments']);
        $this->assertCount(1, $data->assignments);
        $this->assertEquals($this->lpid, $data->assignments[0]->learning_path_id);
    }

    /**
     * Deleting the creator: the path stays and passes to the editor; the
     * participations they created stay, anonymised; the learners keep their
     * enrolments.
     *
     * @return void
     */
    public function test_deleting_the_creator_keeps_the_path_and_the_learners(): void {
        global $DB;
        $this->assert_precondition();
        $this->setUser($this->people['creator']);
        provider::delete_data_for_user(new approved_contextlist(
            $this->people['creator'],
            'local_adele',
            [context_system::instance()->id]
        ));
        $this->setAdminUser();

        $path = $DB->get_record('local_adele_learning_paths', ['id' => $this->lpid], '*', MUST_EXIST);
        $this->assertEquals($this->people['editor']->id, $path->createdby, 'The editor took over.');
        $this->assertFalse($DB->record_exists('local_adele_lp_editors', ['userid' => $this->people['creator']->id]));
        $this->assertTrue($DB->record_exists('local_adele_lp_editors', [
            'userid' => $this->people['editor']->id, 'learningpathid' => $this->lpid,
        ]));
        foreach (['learner', 'other'] as $key) {
            $this->assertTrue($DB->record_exists('local_adele_path_user', [
                'user_id' => $this->people[$key]->id, 'learning_path_id' => $this->lpid, 'createdby' => 0,
            ]), "$key keeps the participation, anonymised");
            $this->assertTrue($this->host_enrolled($key), "$key keeps the host enrolment");
        }
        $this->assertSame(0, $DB->count_records('local_adele_path_user', ['createdby' => $this->people['creator']->id]));
        $this->assertSame([], provider::get_contexts_for_userid((int) $this->people['creator']->id)->get_contextids());
        $this->assert_nothing_dangles();
    }

    /**
     * Deleting the creator with no other editor: the path is left without
     * an owner.
     *
     * @return void
     */
    public function test_without_a_successor_the_path_has_no_owner(): void {
        global $DB;
        $DB->delete_records('local_adele_lp_editors', ['userid' => $this->people['editor']->id]);
        provider::delete_data_for_user(new approved_contextlist(
            $this->people['creator'],
            'local_adele',
            [context_system::instance()->id]
        ));
        $this->assertEquals(0, $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $this->lpid]));
        $this->assertSame(2, $DB->count_records('local_adele_path_user', ['learning_path_id' => $this->lpid]));
        $this->assert_nothing_dangles();
    }

    /**
     * Deleting a learner: their participation and ADELE enrolments go; the
     * other learner's stay.
     *
     * @return void
     */
    public function test_deleting_a_learner_takes_their_enrolments(): void {
        global $DB;
        $this->assert_precondition();
        provider::delete_data_for_user(new approved_contextlist(
            $this->people['learner'],
            'local_adele',
            [context_system::instance()->id]
        ));
        $this->assertFalse($DB->record_exists('local_adele_path_user', ['user_id' => $this->people['learner']->id]));
        $this->assertFalse($this->host_enrolled('learner'), 'The ADELE host enrolment went with it.');
        $this->assertTrue($this->host_enrolled('other'));
        $this->assertTrue($DB->record_exists('local_adele_learning_paths', ['id' => $this->lpid]));
        $this->assert_nothing_dangles();
    }

    /**
     * A user list: creator and learner at once.
     *
     * @return void
     */
    public function test_delete_for_users(): void {
        global $DB;
        $system = context_system::instance();
        provider::delete_data_for_users(new approved_userlist(
            $system,
            'local_adele',
            [(int) $this->people['creator']->id, (int) $this->people['learner']->id]
        ));
        $owner = $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $this->lpid]);
        $this->assertEquals($this->people['editor']->id, $owner);
        $this->assertFalse($DB->record_exists('local_adele_path_user', ['user_id' => $this->people['learner']->id]));
        $this->assertTrue($DB->record_exists('local_adele_path_user', [
            'user_id' => $this->people['other']->id, 'createdby' => 0,
        ]));
        $this->assert_nothing_dangles();

        // Another context deletes nothing.
        $course = \context_course::instance($this->host->id);
        provider::delete_data_for_users(new approved_userlist($course, 'local_adele', [(int) $this->people['other']->id]));
        $this->assertTrue($DB->record_exists('local_adele_path_user', ['user_id' => $this->people['other']->id]));
    }

    /**
     * Everybody in the system context: participations and editor rights go,
     * the paths stay without an owner; nothing dangles.
     *
     * @return void
     */
    public function test_delete_all_users_keeps_the_paths(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertSame(0, $DB->count_records('local_adele_path_user'));
        $this->assertSame(0, $DB->count_records('local_adele_lp_editors'));
        $this->assertEquals(0, $DB->get_field('local_adele_learning_paths', 'createdby', ['id' => $this->lpid]));
        $this->assertFalse($this->host_enrolled('learner'));
        $this->assertFalse($this->host_enrolled('other'));
        $this->assert_nothing_dangles();
    }

    /**
     * Several learning paths and role assignments (re-audit TP-06): the
     * learner and the creator on a second path too, deleted in bulk - every
     * participation of the learner goes on both paths, with every ADELE
     * enrolment and the role assignments enrol_adele made; the other
     * learner's participations on both paths stay; a manual enrolment of the
     * learner in a host course stays; both paths stay; nothing dangles.
     *
     * @return void
     */
    public function test_several_paths_in_bulk(): void {
        global $DB;
        $gen = $this->getDataGenerator();
        $node2 = $gen->create_course();
        $host2 = $gen->create_course();
        $nodes = [['id' => 'dndnode_1', 'type' => 'custom', 'parentCourse' => ['starting_node'],
            'data' => ['course_node_id' => [(int) $node2->id]]]];
        $lp2 = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Second path', 'description' => '', 'timecreated' => time(), 'timemodified' => time(),
            'createdby' => $this->people['creator']->id,
            'json' => json_encode(['tree' => ['nodes' => $nodes, 'edges' => []]]),
        ]);
        foreach (['learner', 'other'] as $key) {
            $gen->enrol_user($this->people[$key]->id, $node2->id, 'student');
        }
        // An enrolment of the learner's own in the host course of path 1.
        $gen->enrol_user($this->people['learner']->id, $this->host->id, 'student', 'manual');
        $this->setUser($this->people['creator']);
        $gen->create_module('adele', [
            'course' => $host2->id, 'learningpathid' => $lp2, 'participantslist' => [3], 'hostenrolmentmode' => 'visible',
        ]);
        $this->runAdhocTasks();
        $this->setAdminUser();
        $adeleroles = fn(int $userid) => $DB->count_records(
            'role_assignments',
            ['userid' => $userid, 'component' => 'enrol_adele']
        );
        $paths = fn(int $userid) => $DB->get_fieldset_select('local_adele_path_user', 'learning_path_id', 'user_id = ?', [$userid]);
        foreach (['learner', 'other'] as $key) {
            $ids = array_map('intval', $paths((int) $this->people[$key]->id));
            sort($ids);
            $this->assertSame([$this->lpid, $lp2], $ids, "$key on both paths");
            $this->assertGreaterThan(0, $adeleroles((int) $this->people[$key]->id), "$key holds ADELE roles");
        }

        provider::delete_data_for_users(new approved_userlist(
            context_system::instance(),
            'local_adele',
            [(int) $this->people['creator']->id, (int) $this->people['learner']->id]
        ));

        $learner = (int) $this->people['learner']->id;
        $this->assertSame([], $paths($learner), 'No participation of the learner left.');
        $this->assertSame(0, $adeleroles($learner), 'No role assignment of enrol_adele left for the learner.');
        $this->assertSame(0, $DB->count_records_sql(
            "SELECT COUNT(1) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele' AND ue.userid = :u",
            ['u' => $learner]
        ), 'No ADELE enrolment of the learner left.');
        $this->assertTrue(is_enrolled(\context_course::instance($this->host->id), $learner), 'The own enrolment stays.');
        $other = (int) $this->people['other']->id;
        $this->assertCount(2, $paths($other), 'The other learner stays on both paths.');
        $this->assertGreaterThan(0, $adeleroles($other));
        foreach ([$this->lpid, $lp2] as $lpid) {
            $this->assertTrue($DB->record_exists('local_adele_learning_paths', ['id' => $lpid]), 'Both paths stay.');
        }
        $this->assertSame(0, $DB->count_records('local_adele_path_user', ['createdby' => $this->people['creator']->id]));
        $this->assert_nothing_dangles();
    }
}
