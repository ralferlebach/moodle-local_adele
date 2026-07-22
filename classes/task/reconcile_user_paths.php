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
 * Ad-hoc task that reconciles all active user learning paths.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele\task;

use local_adele\learning_path_update;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc task that re-evaluates every active user learning path exactly once.
 *
 * This is required after the course_completed observer fix
 * (completion::completed used $event->userid - the acting user - instead of
 * $event->relateduserid - the affected student), because course_completed does
 * not fire again for courses that were already completed
 * (course_completions.timecompleted is already set). Existing learning paths can
 * therefore still carry a stale node status until they are recomputed once.
 *
 * The task deliberately reuses the central update service
 * (learning_path_update::trigger_user_path_update) instead of duplicating the
 * evaluation logic. It is idempotent (a recompute is deterministic), processes
 * the records in bounded batches, and logs progress and per-record errors.
 *
 * @package    local_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_user_paths extends \core\task\adhoc_task {
    /**
     * Number of records processed per batch.
     */
    const BATCHSIZE = 200;

    /**
     * Human readable name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_reconcile_user_paths', 'local_adele');
    }

    /**
     * Re-evaluate every active user learning path in bounded batches.
     *
     * The custom data may carry a "lastid" cursor so that the task can be
     * re-queued to continue where it left off; this keeps the run repeatable and
     * resilient on large installations.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $taskdata = $this->get_custom_data();
        $lastid = (int) ($taskdata->lastid ?? 0);

        $processed = 0;
        $failed = 0;

        // Fetch one bounded batch of active snapshots ordered by id, so the
        // cursor ("lastid") makes the run resumable and idempotent.
        $records = $DB->get_records_select(
            'local_adele_path_user',
            'status = :status AND id > :lastid',
            ['status' => 'active', 'lastid' => $lastid],
            'id ASC',
            '*',
            0,
            self::BATCHSIZE
        );

        if (empty($records)) {
            mtrace('local_adele: reconcile_user_paths - nothing left to reconcile.');
            return;
        }

        foreach ($records as $record) {
            $lastid = (int) $record->id;
            try {
                $record->json = json_decode($record->json, true);

                // Skip structurally broken snapshots instead of aborting the run.
                if (!is_array($record->json) || empty($record->json['tree']['nodes'])) {
                    mtrace("local_adele: reconcile_user_paths - skipping path_user #{$record->id} (invalid JSON).");
                    continue;
                }

                learning_path_update::trigger_user_path_update($record);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                mtrace(
                    "local_adele: reconcile_user_paths - error on path_user #{$record->id}: " . $e->getMessage()
                );
            }
        }

        mtrace(
            "local_adele: reconcile_user_paths - batch done (processed: {$processed}, failed: {$failed}, lastid: {$lastid})."
        );

        // If the batch was full there may be more records: queue a follow-up run
        // that continues past the current cursor.
        if (count($records) >= self::BATCHSIZE) {
            $next = new self();
            $next->set_custom_data(['lastid' => $lastid]);
            \core\task\manager::queue_adhoc_task($next, true);
            mtrace('local_adele: reconcile_user_paths - queued follow-up batch from id ' . $lastid . '.');
        } else {
            mtrace('local_adele: reconcile_user_paths - reconciliation complete.');
        }
    }
}
