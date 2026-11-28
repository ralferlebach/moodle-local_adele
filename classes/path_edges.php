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
 * One identity per connection of a learning path (local_adele #603).
 *
 * Two edges of a path's tree are the same connection when they agree in
 * type (no type: VueFlow's "default"), source, source handle, target and
 * target handle - not because their ids are equal: the editor builds an id
 * as "source + target", which may collide. A learning path knows no
 * parallel connections; between two handles of two nodes there is one
 * relation or none.
 *
 * normalize() keeps the first edge of each connection in its place, drops
 * later copies and gives an edge whose id another connection already holds
 * a free id ("<id>_2", "<id>_3", ...). Deterministic and idempotent; every
 * other edge stays exactly as it was. The frontend applies the same rule
 * (vue3/composables/flowHelper/normalizeEdges.js).
 *
 * normalize_json() works on the stored json string and leaves it untouched,
 * byte for byte, when there is nothing to correct.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class path_edges {
    /** @var string VueFlow's type of an edge without one. */
    const DEFAULT_TYPE = 'default';

    /**
     * The identity of a connection.
     *
     * @param array|object $edge
     * @return string
     */
    public static function key($edge): string {
        $e = (array) $edge;
        $type = isset($e['type']) && $e['type'] !== '' ? (string) $e['type'] : self::DEFAULT_TYPE;
        return json_encode([
            $type,
            (string) ($e['source'] ?? ''),
            (string) ($e['sourceHandle'] ?? ''),
            (string) ($e['target'] ?? ''),
            (string) ($e['targetHandle'] ?? ''),
        ]);
    }

    /**
     * One edge per connection, one connection per id.
     *
     * @param array $edges Edges as arrays or objects.
     * @param bool $renameconflicts Give an edge with a taken id a free one;
     *     false keeps the id (only copies of a connection are dropped).
     * @return array ['edges' => array, 'removed' => int, 'renamed' => int,
     *     'conflicts' => array of ['id' => old, 'newid' => new]]
     */
    public static function normalize(array $edges, bool $renameconflicts = true): array {
        $out = [];
        $keys = [];
        $ids = [];
        $conflicts = [];
        $removed = 0;
        foreach ($edges as $edge) {
            if (!is_array($edge) && !is_object($edge)) {
                continue;
            }
            $key = self::key($edge);
            if (isset($keys[$key])) {
                $removed++;
                continue;
            }
            $keys[$key] = true;
            $id = (string) (((array) $edge)['id'] ?? '');
            if ($renameconflicts && isset($ids[$id])) {
                $n = 2;
                while (isset($ids[$id . '_' . $n])) {
                    $n++;
                }
                $newid = $id . '_' . $n;
                $edge = is_object($edge) ? clone $edge : $edge;
                if (is_object($edge)) {
                    $edge->id = $newid;
                } else {
                    $edge['id'] = $newid;
                }
                $conflicts[] = ['id' => $id, 'newid' => $newid];
                $id = $newid;
            }
            $ids[$id] = true;
            $out[] = $edge;
        }
        return ['edges' => $out, 'removed' => $removed, 'renamed' => count($conflicts), 'conflicts' => $conflicts];
    }

    /**
     * The json of a path (or of a person's path) with its tree's edges
     * normalized. Unchanged input is returned as it came.
     *
     * @param string|null $json
     * @param bool $renameconflicts See normalize().
     * @return array [string|null $json, array $report] report as normalize()
     *     without 'edges'; 'changed' => bool.
     */
    public static function normalize_json(?string $json, bool $renameconflicts = true): array {
        $none = ['removed' => 0, 'renamed' => 0, 'conflicts' => [], 'changed' => false];
        if ($json === null || $json === '') {
            return [$json, $none];
        }
        $decoded = json_decode($json);
        $edges = is_object($decoded) && isset($decoded->tree) && is_object($decoded->tree)
            ? ($decoded->tree->edges ?? null)
            : null;
        if (!is_array($edges)) {
            return [$json, $none];
        }
        $result = self::normalize($decoded->tree->edges, $renameconflicts);
        $report = ['removed' => $result['removed'], 'renamed' => $result['renamed'], 'conflicts' => $result['conflicts']];
        if (!$result['removed'] && !$result['renamed']) {
            return [$json, $report + ['changed' => false]];
        }
        $decoded->tree->edges = $result['edges'];
        // As the editor writes it (JSON.stringify): no escaped slashes or
        // characters; objects stay objects.
        return [json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $report + ['changed' => true]];
    }

    /**
     * Normalize the stored learning paths - and, only by dropping copies of
     * a connection, the paths of the people on them - and say what was done
     * (upgrade step and CLI, local_adele #603). Each record in a transaction
     * of its own; running it again changes nothing.
     *
     * @param bool $dryrun Count only, write nothing.
     * @param callable|null $trace function(string $line) for a report.
     * @return array ['paths' => n changed, 'pathedges' => removed, 'renamed' => n,
     *     'snapshots' => n changed, 'snapshotedges' => removed, 'snapshotconflicts' => n]
     */
    public static function repair_stored(bool $dryrun = false, ?callable $trace = null): array {
        global $DB;
        $trace = $trace ?? function (string $line): void {
        };
        $totals = ['paths' => 0, 'pathedges' => 0, 'renamed' => 0, 'snapshots' => 0, 'snapshotedges' => 0,
            'snapshotconflicts' => 0];

        $paths = $DB->get_recordset('local_adele_learning_paths', null, 'id ASC', 'id, name, json');
        foreach ($paths as $path) {
            [$json, $report] = self::normalize_json($path->json, true);
            if (!$report['changed']) {
                continue;
            }
            $totals['paths']++;
            $totals['pathedges'] += $report['removed'];
            $totals['renamed'] += $report['renamed'];
            $trace("learning path {$path->id} ({$path->name}): {$report['removed']} copies removed, "
                . "{$report['renamed']} ids changed");
            if (!$dryrun) {
                $transaction = $DB->start_delegated_transaction();
                $DB->set_field('local_adele_learning_paths', 'json', $json, ['id' => $path->id]);
                $transaction->allow_commit();
            }
        }
        $paths->close();

        // A person's path carries the tree of its path at the last save, plus
        // their progress. Only copies of a connection go; an id conflict is
        // reported and left - the view gives it a free id when drawing, and
        // the next save of the path brings the corrected tree.
        $snapshots = $DB->get_recordset('local_adele_path_user', null, 'id ASC', 'id, json');
        foreach ($snapshots as $snapshot) {
            [$json, $report] = self::normalize_json($snapshot->json, false);
            $conflicting = self::normalize_json($snapshot->json, true)[1]['renamed'];
            $totals['snapshotconflicts'] += $conflicting;
            if (!$report['changed']) {
                continue;
            }
            $totals['snapshots']++;
            $totals['snapshotedges'] += $report['removed'];
            if (!$dryrun) {
                $transaction = $DB->start_delegated_transaction();
                $DB->set_field('local_adele_path_user', 'json', $json, ['id' => $snapshot->id]);
                $transaction->allow_commit();
            }
        }
        $snapshots->close();
        $trace("learning paths changed: {$totals['paths']} ({$totals['pathedges']} copies removed, "
            . "{$totals['renamed']} ids changed); paths of people changed: {$totals['snapshots']} "
            . "({$totals['snapshotedges']} copies removed, {$totals['snapshotconflicts']} id conflicts left)"
            . ($dryrun ? ' - dry run, nothing written' : ''));
        return $totals;
    }
}
