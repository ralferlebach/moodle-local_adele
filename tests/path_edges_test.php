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

use advanced_testcase;

/**
 * One edge per connection in stored, loaded and newly saved learning paths
 * (local_adele #603).
 *
 * @package    local_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_adele\path_edges
 * @covers     \local_adele\learning_paths::save_learning_path
 * @covers     \local_adele\learning_paths::update_learning_path
 * @covers     \local_adele\learning_paths::duplicate_learning_path
 * @covers     \local_adele\learning_paths::get_learning_path
 * @covers     \local_adele\learning_paths::addnodemanualcondition
 */
final class path_edges_test extends advanced_testcase {
    /**
     * The four reference paths of the E2E fixtures, by name.
     *
     * @return array name => json string
     */
    private function fixtures(): array {
        $file = __DIR__ . '/playwright/fixtures/learningpaths/local_adele_learning_paths.json';
        $rows = json_decode(file_get_contents($file), true);
        return array_column($rows, 'json', 'name');
    }

    /**
     * An edge as the editor makes it.
     *
     * @param string $id
     * @param string $source
     * @param string $target
     * @param array $more
     * @return array
     */
    private function edge(string $id, string $source, string $target, array $more = []): array {
        return $more + ['id' => $id, 'source' => $source, 'target' => $target, 'sourceHandle' => 'source',
            'targetHandle' => 'target'];
    }

    /**
     * The ids of a json's tree edges.
     *
     * @param string $json
     * @return string[]
     */
    private function ids(string $json): array {
        return array_map(fn($e) => $e['id'], json_decode($json, true)['tree']['edges']);
    }

    /**
     * (a) The two real fixtures: the four copies go, every id once, nothing
     * else in the path changes; the clean fixtures stay byte for byte.
     *
     * @return void
     */
    public function test_the_reference_fixtures(): void {
        $expected = [
            'Verzweigt, gestapelt B1' => [7, 5, ['dndnode_3dndnode_1', 'dndnode_5dndnode_4']],
            'Äquivalenzumformung (neu)' => [14, 12, ['dndnode_4dndnode_3', 'dndnode_11dndnode_6']],
        ];
        foreach ($this->fixtures() as $name => $json) {
            [$out, $report] = path_edges::normalize_json($json);
            if (!isset($expected[$name])) {
                $this->assertSame($json, $out, "$name is clean and stays untouched");
                $this->assertFalse($report['changed']);
                continue;
            }
            [$before, $after, $doubled] = $expected[$name];
            $this->assertCount($before, $this->ids($json));
            foreach ($doubled as $id) {
                $this->assertSame(2, count(array_keys($this->ids($json), $id)), "$name: $id twice before");
                $this->assertSame(1, count(array_keys($this->ids($out), $id)), "$name: $id once after");
            }
            $this->assertCount($after, $this->ids($out));
            $this->assertSame(array_values(array_unique($this->ids($out))), $this->ids($out), 'No id twice.');
            $this->assertSame(['removed' => 2, 'renamed' => 0, 'conflicts' => [], 'changed' => true], $report);
            // Everything but the edges is as it was; the first edge of each
            // connection is the one kept (VueFlow's, with type and data).
            $old = json_decode($json, true);
            $new = json_decode($out, true);
            $this->assertSame($old['tree']['nodes'], $new['tree']['nodes']);
            unset($old['tree']['edges'], $new['tree']['edges']);
            $this->assertSame($old, $new);
            $kept = array_values(array_filter(
                json_decode($out, true)['tree']['edges'],
                fn($e) => $e['id'] === $doubled[0]
            ));
            $this->assertSame('default', $kept[0]['type']);
            // Idempotent.
            [$again, $againreport] = path_edges::normalize_json($out);
            $this->assertSame([$out, false], [$again, $againreport['changed']]);
        }
    }

    /**
     * (b) The same connection twice - also once with and once without a
     * type (VueFlow's "default") - is one; (c) the same id for another
     * connection is not a copy: both stay, the later one gets a free id and
     * the conflict is reported; (d) another handle is another connection.
     *
     * @return void
     */
    public function test_identity_is_the_connection_not_the_id(): void {
        $a = $this->edge('n2n1', 'n1', 'n2', ['type' => 'default', 'data' => []]);
        $copy = $this->edge('other', 'n1', 'n2');
        $sameid = $this->edge('n2n1', 'n2', 'n1');
        $taken = $this->edge('n2n1_2', 'n3', 'n4');
        $otherhandle = $this->edge('n2n1x', 'n1', 'n2', ['sourceHandle' => 'source_b']);
        $result = path_edges::normalize([$a, $copy, $sameid, $taken, $otherhandle]);
        $this->assertSame(1, $result['removed'], 'Only the copy of the connection goes.');
        $this->assertSame(['n2n1', 'n2n1_2', 'n2n1_2_2', 'n2n1x'], array_column($result['edges'], 'id'));
        $this->assertSame(
            [['id' => 'n2n1', 'newid' => 'n2n1_2'], ['id' => 'n2n1_2', 'newid' => 'n2n1_2_2']],
            $result['conflicts']
        );
        $this->assertSame(
            ['n2', 'n1'],
            [$result['edges'][1]['source'], $result['edges'][1]['target']],
            'The connection itself is kept.'
        );
        $again = path_edges::normalize($result['edges']);
        $this->assertSame($result['edges'], $again['edges'], 'Idempotent.');
        $this->assertSame(0, $again['removed'] + $again['renamed']);
        // Without renaming (paths of people): copies go, ids stay.
        $kept = path_edges::normalize([$a, $copy, $sameid], false);
        $this->assertSame(['n2n1', 'n2n1'], array_column($kept['edges'], 'id'));
        // Objects work alike and are not changed in place.
        $object = (object) $sameid;
        $result = path_edges::normalize([(object) $a, $object]);
        $this->assertSame('n2n1', $object->id);
        $this->assertSame('n2n1_2', $result['edges'][1]->id);
        // Nothing to do with odd input.
        $this->assertSame([null, false], [path_edges::normalize_json(null)[0], path_edges::normalize_json(null)[1]['changed']]);
        $this->assertSame('{"tree":{}}', path_edges::normalize_json('{"tree":{}}')[0]);
        $this->assertSame('not json', path_edges::normalize_json('not json')[0]);
    }

    /**
     * (e) Saved again and again through the service, (f) duplicated, loaded
     * for the editor and for a person: one edge per connection; a clean path
     * is stored exactly as sent.
     *
     * @return void
     */
    public function test_save_duplicate_and_load(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $fixtures = $this->fixtures();
        // The courses of the nodes exist on this site (the editor's load
        // looks up their images).
        $course = $this->getDataGenerator()->create_course();
        foreach ($fixtures as $name => $json) {
            $decoded = json_decode($json, true);
            foreach ($decoded['tree']['nodes'] as &$node) {
                if (!empty($node['data']['course_node_id'])) {
                    $count = count((array) $node['data']['course_node_id']);
                    $node['data']['course_node_id'] = array_fill(0, $count, (string) $course->id);
                }
            }
            unset($node);
            $fixtures[$name] = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $params = ['learningpathid' => 0, 'name' => 'Doubled', 'description' => 'd', 'image' => '',
            'json' => $fixtures['Verzweigt, gestapelt B1'], 'userid' => 2];
        $id = (int) learning_paths::save_learning_path($params)->id;
        $stored = $DB->get_field('local_adele_learning_paths', 'json', ['id' => $id]);
        $this->assertCount(5, $this->ids($stored));
        for ($i = 0; $i < 2; $i++) {
            learning_paths::save_learning_path(
                ['learningpathid' => $id, 'json' => $fixtures['Verzweigt, gestapelt B1']] + $params
            );
            $this->assertSame(
                $stored,
                $DB->get_field('local_adele_learning_paths', 'json', ['id' => $id]),
                'Saved again: the same.'
            );
        }
        $clean = (int) learning_paths::save_learning_path(['name' => 'Clean', 'json' => $fixtures['Linear A1']] + $params)->id;
        $this->assertSame($fixtures['Linear A1'], $DB->get_field('local_adele_learning_paths', 'json', ['id' => $clean]));

        // Written around the save service, as before #603: duplicated, loaded.
        $DB->set_field('local_adele_learning_paths', 'json', $fixtures['Verzweigt, gestapelt B1'], ['id' => $id]);
        learning_paths::duplicate_learning_path(['learningpathid' => $id]);
        $copy = $DB->get_field('local_adele_learning_paths', 'json', ['name' => 'Doubled copy']);
        $this->assertCount(5, $this->ids($copy));
        $this->assertCount(5, $this->ids(learning_paths::get_learning_path(['learningpathid' => $id])['json']));
        $raw = $DB->get_field('local_adele_learning_paths', 'json', ['id' => $id]);
        $this->assertCount(7, $this->ids($raw), 'Loading writes nothing.');
        learning_paths::update_learning_path(['id' => $id, 'json' => $fixtures['Verzweigt, gestapelt B1']]);
        $this->assertCount(5, $this->ids($DB->get_field('local_adele_learning_paths', 'json', ['id' => $id])));

        // A person's path, drawn.
        $person = json_decode($fixtures['Verzweigt, gestapelt B1']);
        $person->user_path_relation = new \stdClass();
        foreach ($person->tree->nodes as $node) {
            $node->completion = $node->completion ?? (object) ['nodes' => []];
            $node->completion->nodes = $node->completion->nodes ?? [];
        }
        $drawn = json_decode(learning_paths::addnodemanualcondition(json_encode($person), 0), true);
        $ids = array_column($drawn['tree']['edges'], 'id');
        $this->assertCount(5, $ids);
        $this->assertSame(array_values(array_unique($ids)), $ids);
    }

    /**
     * (g) The upgrade repair: counted, dry run writes nothing, a second run
     * finds nothing; the paths of people lose copies only - their progress
     * stays, an id conflict is reported and left.
     *
     * @return void
     */
    public function test_repair_of_stored_data(): void {
        global $DB;
        $this->resetAfterTest();
        $fixtures = $this->fixtures();
        $now = time();
        $ids = [];
        foreach ($fixtures as $name => $json) {
            $ids[$name] = (int) $DB->insert_record('local_adele_learning_paths', (object) [
                'name' => $name, 'description' => '', 'timecreated' => $now, 'timemodified' => $now, 'createdby' => 2,
                'json' => $json,
            ]);
        }
        $user = $this->getDataGenerator()->create_user();
        $snapshot = json_decode($fixtures['Verzweigt, gestapelt B1'], true);
        $snapshot['user_path_relation'] = ['dndnode_1' => ['completed' => true]];
        $snapshot['tree']['edges'][] = $this->edge('dndnode_4dndnode_1', 'dndnode_9', 'dndnode_8');
        $snapshotid = (int) $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id, 'course_id' => 0, 'learning_path_id' => $ids['Verzweigt, gestapelt B1'],
            'status' => 'active', 'timecreated' => $now, 'timemodified' => $now, 'createdby' => 2,
            'json' => json_encode($snapshot),
        ]);
        $before = $DB->get_records_menu('local_adele_learning_paths', null, 'id', 'id, json');
        $lines = [];

        $dry = path_edges::repair_stored(true, function (string $line) use (&$lines): void {
            $lines[] = $line;
        });
        $this->assertSame(['paths' => 2, 'pathedges' => 4, 'renamed' => 0, 'snapshots' => 1, 'snapshotedges' => 2,
            'snapshotconflicts' => 1], $dry);
        $this->assertSame($before, $DB->get_records_menu('local_adele_learning_paths', null, 'id', 'id, json'), 'Dry run.');
        $this->assertStringContainsString('dry run', end($lines));

        $this->assertSame($dry, path_edges::repair_stored(false));
        $storedjson = fn(string $name) => $DB->get_field('local_adele_learning_paths', 'json', ['id' => $ids[$name]]);
        $this->assertCount(5, $this->ids($storedjson('Verzweigt, gestapelt B1')));
        $this->assertCount(12, $this->ids($storedjson('Äquivalenzumformung (neu)')));
        foreach (['Linear A1', 'Linear A2'] as $name) {
            $this->assertSame($before[$ids[$name]], $DB->get_field('local_adele_learning_paths', 'json', ['id' => $ids[$name]]));
        }
        $person = json_decode($DB->get_field('local_adele_path_user', 'json', ['id' => $snapshotid]), true);
        $this->assertSame(['dndnode_1' => ['completed' => true]], $person['user_path_relation'], 'Progress stays.');
        $this->assertCount(6, $person['tree']['edges'], 'Copies gone, the conflicting edge left as it was.');
        $this->assertSame(2, count(array_keys(array_column($person['tree']['edges'], 'id'), 'dndnode_4dndnode_1')));

        $second = path_edges::repair_stored(false);
        $this->assertSame(['paths' => 0, 'pathedges' => 0, 'renamed' => 0, 'snapshots' => 0, 'snapshotedges' => 0,
            'snapshotconflicts' => 1], $second, 'A second run changes nothing.');
    }
}
