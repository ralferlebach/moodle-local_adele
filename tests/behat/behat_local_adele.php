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
 * Behat steps for local_adele.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use local_adele\task\update_user_path;
use local_adele\relation_update;
use local_adele\event\user_path_updated;

/**
 * Steps definitions for Adele.
 */
class behat_local_adele extends behat_base {
    /**
     * Create a mod_adele activity in a course, linked to a named learning path.
     *
     * Creates the activity with participantslist = [1] ("all participants of this
     * course"), so the course_module_created event fires mod_adele_observer::saved_module,
     * which subscribes every enrolled user to the learning path (creating their
     * local_adele_path_user snapshot) - the same chain the PHPUnit harness drives.
     *
     * @Given /^a learning path activity "(?P<name>[^"]+)" for "(?P<lpname>[^"]+)" exists in course "(?P<shortname>[^"]+)"$/
     *
     * @param string $name      The activity name.
     * @param string $lpname    The learning path name (from the learningpaths generator).
     * @param string $shortname The course shortname.
     */
    public function a_learning_path_activity_exists_in_course(string $name, string $lpname, string $shortname): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/testing/generator/lib.php');

        $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $lpid = $DB->get_field('local_adele_learning_paths', 'id', ['name' => $lpname], MUST_EXIST);

        $generator = \testing_util::get_data_generator();
        $generator->get_plugin_generator('mod_adele')->create_instance([
            'course'           => $courseid,
            'name'             => $name,
            'participantslist' => [1],
            'learningpathid'   => $lpid,
            // View = 1 (top-level path view) is required for view.php to render the
            // SPA; it defaults to 0 in the table, which would bail out before output.
            'view'             => 1,
            'userlist'         => 1,
        ]);
    }

    /**
     * Open the timed restriction window for a node in every active learner snapshot.
     *
     * Mirrors the PHPUnit harness' set_window(): it rewrites the node's `timed`
     * restriction condition (data.label == 'timed') in each local_adele_path_user
     * row so the window is now open (start in the past, end in the future) WITHOUT
     * recomputing - exactly as the world reaching the scheduled boundary would.
     * The subsequent "I run the scheduled learning path update tasks" step then
     * recomputes and unlocks/enrols.
     *
     * @Given /^the timed restriction window for node "(?P<node>[^"]+)" is now open$/
     *
     * @param string $node The node id (e.g. dndnode_2).
     */
    public function the_timed_window_for_node_is_now_open(string $node): void {
        global $DB;
        $start = date('Y-m-d\TH:i', strtotime('-1 hour'));
        $end   = date('Y-m-d\TH:i', strtotime('+7 days'));
        $updated = 0;
        foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $record) {
            $json = json_decode($record->json, true);
            if (!is_array($json) || empty($json['tree']['nodes'])) {
                continue;
            }
            $changed = false;
            foreach ($json['tree']['nodes'] as &$tn) {
                if (($tn['id'] ?? '') !== $node || empty($tn['restriction']['nodes'])) {
                    continue;
                }
                // NB: iterate the real array (no "?? []" - that returns a by-value
                // copy and silently defeats the &$cn reference, so the write is lost).
                foreach ($tn['restriction']['nodes'] as &$cn) {
                    if (($cn['data']['label'] ?? '') === 'timed') {
                        $cn['data']['value']['start'] = $start;
                        $cn['data']['value']['end']   = $end;
                        $changed = true;
                    }
                }
                unset($cn);
            }
            unset($tn);
            if ($changed) {
                $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
                $updated++;
            }
        }
        if ($updated === 0) {
            throw new \RuntimeException(
                "No active learner snapshot carried a timed restriction on node '{$node}'."
            );
        }
    }

    /**
     * Assert that a timed node scheduled a per-user adhoc update task (#438).
     *
     * Proves the SCHEDULING half of the timed-restriction rework: subscribing a
     * learner to a future-windowed timed node must queue an
     * \local_adele\task\update_user_path adhoc task for the boundary.
     *
     * @Then /^a learning path update task should be scheduled$/
     */
    public function a_learning_path_update_task_should_be_scheduled(): void {
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . update_user_path::class);
        if (empty($tasks)) {
            throw new \RuntimeException(
                'Expected a scheduled \\local_adele\\task\\update_user_path adhoc task, but none were queued.'
            );
        }
    }

    /**
     * Force-run every queued update_user_path adhoc task.
     *
     * The core "I run all adhoc tasks" step only fires tasks whose next-run-time
     * has already passed; a timed boundary task is scheduled in the future, so it
     * would be skipped. This step executes the queued update_user_path tasks
     * directly (as the PHPUnit harness does), simulating cron reaching the
     * boundary, so the real task drives the recompute -> unlock -> enrol.
     *
     * @When /^I run the scheduled learning path update tasks$/
     */
    public function i_run_the_scheduled_learning_path_update_tasks(): void {
        global $DB;
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . update_user_path::class);
        foreach ($tasks as $task) {
            ob_start();
            try {
                $task->execute();
            } finally {
                ob_end_clean();
            }
            // The task ran to completion; remove its queue record (mirrors a
            // successful cron run) so re-running this step is idempotent. We delete
            // the record directly rather than via adhoc_task_complete(), which
            // expects a lock acquired through get_next_adhoc_task().
            $DB->delete_records('task_adhoc', ['id' => $task->get_id()]);
        }
    }
    /**
     * Drag and drop with HTML5 data transfer, optionally targeting a dropzone.
     *
     * @When /^I drag and drop HTML5 from "(?P<source>[^"]+)" to "(?P<target>[^"]+)"$/
     * @When /^I drag and drop HTML5 from "(?P<source>[^"]+)" to "(?P<target>[^"]+)" as "(?P<dropzone>[^"]+)"$/
     *
     * @param string $source CSS selector for the draggable element.
     * @param string $target CSS selector for the drop target.
     * @param string|null $dropzone Optional CSS selector for a specific dropzone.
     */
    public function i_drag_and_drop_html5_from_to(
        string $source,
        string $target,
        ?string $dropzone = null
    ): void {
        $sourcesel = json_encode($source, JSON_UNESCAPED_SLASHES);
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $dropzonesel = $dropzone !== null ? json_encode($dropzone, JSON_UNESCAPED_SLASHES) : 'null';
        $script = <<<JS
            (function() {
              window.__adele_drag_done = false;
              window.__adele_drag_error = null;

              const source = document.querySelector($sourcesel);
              const target = document.querySelector($targetsel);
              if (!source) {
                window.__adele_drag_error = 'HTML5 drag source not found: ' + $sourcesel;
                window.__adele_drag_done = true;
                return;
              }
              if (!target) {
                window.__adele_drag_error = 'HTML5 drop target not found: ' + $targetsel;
                window.__adele_drag_done = true;
                return;
              }

              source.scrollIntoView({block: 'center', inline: 'center'});
              target.scrollIntoView({block: 'center', inline: 'center'});

              const dataTransfer = new DataTransfer();
              const dragOverTarget = document.querySelector('.learning-path-flow') || target;
              const dropContainer = target.closest('.dndflow') || target;
              const dropzoneSelector = $dropzonesel;
              const timeoutMs = 7000;
              const targetId = target.getAttribute('data-id') || '';
              const shouldWaitForDropzone = !!dropzoneSelector || (targetId && targetId !== 'starting_node');

              const getCenter = (element) => {
                const rect = element.getBoundingClientRect();
                return {
                  x: rect.left + rect.width / 2,
                  y: rect.top + rect.height / 2,
                };
              };

              const buildEvent = (type, coords, extra = {}) => new DragEvent(type, {
                bubbles: true,
                cancelable: true,
                clientX: coords.x,
                clientY: coords.y,
                dataTransfer,
                ...extra,
              });

              const targetCenter = getCenter(target);
              source.dispatchEvent(buildEvent('dragstart', targetCenter));

              if (!shouldWaitForDropzone) {
                source.dispatchEvent(buildEvent('drag', targetCenter));
                document.dispatchEvent(buildEvent('dragover', targetCenter));
                dragOverTarget.dispatchEvent(buildEvent('dragover', targetCenter));
                target.dispatchEvent(buildEvent('dragenter', targetCenter));
                target.dispatchEvent(buildEvent('dragover', targetCenter));
                target.dispatchEvent(buildEvent('drop', targetCenter));
                source.dispatchEvent(buildEvent('dragend', targetCenter));
                window.__adele_drag_done = true;
                return;
              }

              const start = Date.now();
              const intervalId = window.setInterval(() => {
                const elapsed = Date.now() - start;
                if (elapsed > timeoutMs) {
                  window.clearInterval(intervalId);
                  window.__adele_drag_error = 'Timed out waiting for dropzone to activate.';
                  window.__adele_drag_done = true;
                  return;
                }

                source.dispatchEvent(buildEvent('drag', targetCenter));
                document.dispatchEvent(buildEvent('dragover', targetCenter));
                dragOverTarget.dispatchEvent(buildEvent('dragover', targetCenter));

                const dropTarget = dropzoneSelector
                  ? dropContainer.querySelector(dropzoneSelector) || document.querySelector(dropzoneSelector)
                  : dropContainer.querySelector('[data-id^="dropzone_"]');

                if (!dropTarget) {
                  return;
                }

                const dropCenter = getCenter(dropTarget);
                source.dispatchEvent(buildEvent('drag', dropCenter));
                document.dispatchEvent(buildEvent('dragover', dropCenter));
                dragOverTarget.dispatchEvent(buildEvent('dragover', dropCenter));
                dropTarget.dispatchEvent(buildEvent('dragenter', dropCenter));
                dropTarget.dispatchEvent(buildEvent('dragover', dropCenter));

                const customNode = dropTarget.querySelector('.custom-node') || dropTarget;
                const bgColor = window.getComputedStyle(customNode).backgroundColor;
                const isReady = bgColor === 'chartreuse' || bgColor === 'rgb(127, 255, 0)';
                if (!isReady) {
                  return;
                }

                dropTarget.dispatchEvent(buildEvent('drop', dropCenter));
                source.dispatchEvent(buildEvent('dragend', dropCenter));
                window.clearInterval(intervalId);
                window.__adele_drag_done = true;
              }, 50);
            })();
            JS;
        $this->getSession()->executeScript($script);
        $this->getSession()->wait(10000, 'window.__adele_drag_done === true');
        $error = $this->getSession()->evaluateScript('window.__adele_drag_error');
        if (!empty($error)) {
            throw new \RuntimeException($error);
        }
    }

    /**
     * Zoom the Vue Flow viewport to a specific percentage (e.g. 100, 150).
     *
     * @When /^I zoom vue flow to "(?P<percent>[\d.]+)" percent$/
     *
     * @param string $percent Zoom percentage (e.g. 100 for 1.0).
     */
    public function i_zoom_vue_flow_to_percent(string $percent): void {
        $scale = ((float) $percent) / 100.0;
        $scalejson = json_encode($scale);
        $script = <<<JS
            (function() {
              const pane = document.querySelector('.vue-flow__transformationpane');
              if (!pane) {
                throw new Error('Vue Flow transformation pane not found.');
              }
              const transform = pane.style.transform || '';
              const match = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)\\s*scale\\(([-\\d.]+)\\)/.exec(transform);
              const translateX = match ? parseFloat(match[1]) : 0;
              const translateY = match ? parseFloat(match[2]) : 0;
              pane.style.transform = `translate(\${translateX}px, \${translateY}px) scale($scalejson)`;
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Pan the Vue Flow viewport so the element is centered.
     *
     * @When /^I pan vue flow to "(?P<target>[^"]+)"$/
     *
     * @param string $target CSS selector for the element to center.
     */
    public function i_pan_vue_flow_to(string $target): void {
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const pane = document.querySelector('.vue-flow__transformationpane');
              const viewport = document.querySelector('.vue-flow__viewport');
              const target = document.querySelector($targetsel);
              if (!pane || !viewport) {
                throw new Error('Vue Flow viewport elements not found.');
              }
              if (!target) {
                throw new Error('Vue Flow pan target not found: ' + $targetsel);
              }
              const transform = pane.style.transform || '';
              const match = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)\\s*scale\\(([-\\d.]+)\\)/.exec(transform);
              const translateX = match ? parseFloat(match[1]) : 0;
              const translateY = match ? parseFloat(match[2]) : 0;
              const scale = match ? parseFloat(match[3]) : 1;

              const viewportRect = viewport.getBoundingClientRect();
              const viewportCenterX = viewportRect.left + viewportRect.width / 2;
              const viewportCenterY = viewportRect.top + viewportRect.height / 2;

              const nodeTransform = target.style.transform || '';
              const nodeMatch = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)/.exec(nodeTransform);
              const nodeX = nodeMatch ? parseFloat(nodeMatch[1]) : 0;
              const nodeY = nodeMatch ? parseFloat(nodeMatch[2]) : 0;

              const targetRect = target.getBoundingClientRect();
              const nodeWidth = targetRect.width / scale;
              const nodeHeight = targetRect.height / scale;
              const nodeCenterX = nodeX + nodeWidth / 2;
              const nodeCenterY = nodeY + nodeHeight / 2;

              const newTranslateX = viewportCenterX - viewportRect.left - nodeCenterX * scale;
              const newTranslateY = viewportCenterY - viewportRect.top - nodeCenterY * scale;

              pane.style.transform = `translate(\${newTranslateX}px, \${newTranslateY}px) scale(\${scale})`;
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Click a Vue Flow node reliably. A native Mink click on a node flakes with
     * "element not interactable" while the node is mid-transform right after a drag/drop;
     * this dispatches a full pointer+click gesture in the browser (same JS approach as the
     * pan/connect steps), which cannot hit that state.
     *
     * @When /^I click vue flow node "(?P<selector>[^"]+)"$/
     *
     * @param string $selector CSS selector of the node (e.g. [data-id='starting_node']).
     */
    public function i_click_vue_flow_node(string $selector): void {
        $sel = json_encode($selector, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const el = document.querySelector($sel);
              if (!el) {
                throw new Error('Vue Flow node not found: ' + $sel);
              }
              el.scrollIntoView({block: 'center', inline: 'center'});
              const rect = el.getBoundingClientRect();
              const opts = {
                bubbles: true, cancelable: true, view: window,
                clientX: rect.left + rect.width / 2,
                clientY: rect.top + rect.height / 2,
              };
              el.dispatchEvent(new PointerEvent('pointerdown', opts));
              el.dispatchEvent(new MouseEvent('mousedown', opts));
              el.dispatchEvent(new PointerEvent('pointerup', opts));
              el.dispatchEvent(new MouseEvent('mouseup', opts));
              el.dispatchEvent(new MouseEvent('click', opts));
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Connect two Vue Flow nodes by dragging from source handle to target handle.
     *
     * @When /^I connect vue flow node "(?P<source>[^"]+)" to "(?P<target>[^"]+)"$/
     *
     * @param string $source CSS selector for the source node element.
     * @param string $target CSS selector for the target node element.
     */
    public function i_connect_vue_flow_node_to(string $source, string $target): void {
        $sourcesel = json_encode($source, JSON_UNESCAPED_SLASHES);
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const sourceNode = document.querySelector($sourcesel);
              const targetNode = document.querySelector($targetsel);
              if (!sourceNode) {
                throw new Error('Vue Flow source node not found: ' + $sourcesel);
              }
              if (!targetNode) {
                throw new Error('Vue Flow target node not found: ' + $targetsel);
              }

              const sourceHandle = sourceNode.querySelector('.vue-flow__handle.source')
                  || sourceNode.querySelector('.vue-flow__handle');
              const targetHandle = targetNode.querySelector('.vue-flow__handle.target')
                  || targetNode.querySelector('.vue-flow__handle');
              if (!sourceHandle) {
                throw new Error('Vue Flow source handle not found for: ' + $sourcesel);
              }
              if (!targetHandle) {
                throw new Error('Vue Flow target handle not found for: ' + $targetsel);
              }

              const sourceRect = sourceHandle.getBoundingClientRect();
              const targetRect = targetHandle.getBoundingClientRect();
              const startX = sourceRect.left + sourceRect.width / 2;
              const startY = sourceRect.top + sourceRect.height / 2;
              const endX = targetRect.left + targetRect.width / 2;
              const endY = targetRect.top + targetRect.height / 2;

              const buildPointer = (type, x, y) => new PointerEvent(type, {
                bubbles: true,
                cancelable: true,
                clientX: x,
                clientY: y,
                button: 0,
              });

              sourceHandle.dispatchEvent(buildPointer('pointerdown', startX, startY));
              sourceHandle.dispatchEvent(
                new MouseEvent('mousedown', {bubbles: true, cancelable: true, clientX: startX, clientY: startY, button: 0})
              );
              document.dispatchEvent(buildPointer('pointermove', endX, endY));
              document.dispatchEvent(
                new MouseEvent('mousemove', {bubbles: true, cancelable: true, clientX: endX, clientY: endY, button: 0})
              );
              targetHandle.dispatchEvent(buildPointer('pointerup', endX, endY));
              targetHandle.dispatchEvent(
                new MouseEvent('mouseup', {bubbles: true, cancelable: true, clientX: endX, clientY: endY, button: 0})
              );
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Set a field value using JavaScript and dispatch input/change events.
     *
     * @When /^I set the field "(?P<field>[^"]+)" to "(?P<value>[^"]+)" using javascript$/
     *
     * @param string $field Field name or ID.
     * @param string $value Value to set.
     */
    public function i_set_field_value_using_javascript(string $field, string $value): void {
        $fieldjson = json_encode($field, JSON_UNESCAPED_SLASHES);
        $valuejson = json_encode($value, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const selector = `[name=\${$fieldjson}]`;
              const element = document.getElementById($fieldjson)
                || document.querySelector(selector)
                || document.querySelector($fieldjson);
              if (!element) {
                throw new Error('Field not found for: ' + $fieldjson);
              }
              element.value = $valuejson;
              element.dispatchEvent(new Event('input', { bubbles: true }));
              element.dispatchEvent(new Event('change', { bubbles: true }));
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Mark a course complete for every subscribed learner and recompute their paths.
     *
     * Mirrors the PHPUnit completion harness (uc03_parent_courses_restriction):
     *  1. Insert/update a course_completions row for the course (found by shortname)
     *     for every user that has an active local_adele_path_user snapshot, and purge
     *     the coursecompletion MUC entry - so completion_completion::is_complete()
     *     reads the fresh row.
     *  2. Fire a fresh \local_adele\event\user_path_updated per stored path record so
     *     relation_update::updated_single re-evaluates: the completing node's
     *     completion feedback flips to 'completed', and any dependent node whose
     *     parent_courses restriction references it then unlocks (and enrols the user).
     *
     * This is the event-driven chain \core\event\course_completed would trigger via
     * local_adele\completion::completed, exercised deterministically without relying
     * on Moodle's completion cron aggregation.
     *
     * @Given /^the course "(?P<shortname>[^"]+)" is completed for every subscribed learner$/
     *
     * @param string $shortname The course shortname whose completion should be recorded.
     */
    public function the_course_is_completed_for_every_subscribed_learner(string $shortname): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);

        $records = $DB->get_records('local_adele_path_user', ['status' => 'active']);
        if (empty($records)) {
            throw new \RuntimeException('No active learner snapshots found to complete a course for.');
        }

        $cache = \cache::make('core', 'coursecompletion');
        foreach ($records as $record) {
            $userid = (int) $record->user_id;
            if (!$DB->record_exists('course_completions', ['course' => $courseid, 'userid' => $userid])) {
                $DB->insert_record('course_completions', (object) [
                    'course'        => $courseid,
                    'userid'        => $userid,
                    'timeenrolled'  => time(),
                    'timestarted'   => time(),
                    'timecompleted' => time(),
                    'reaggregate'   => 0,
                ]);
            } else {
                $DB->set_field(
                    'course_completions',
                    'timecompleted',
                    time(),
                    ['course' => $courseid, 'userid' => $userid]
                );
            }
            $cache->delete($userid . '_' . $courseid);
        }

        // Re-evaluate each stored path from the current DB state so the restriction
        // that references the now-completed parent node unlocks. Two passes: the first
        // stamps the parent node's completion feedback as 'completed', the second lets
        // the dependent node's parent_courses restriction observe it.
        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($DB->get_records('local_adele_path_user', ['status' => 'active']) as $fresh) {
                $fresh->json = json_decode($fresh->json, true);
                $event = user_path_updated::create([
                    'objectid' => $fresh->id,
                    'context'  => \context_system::instance(),
                    'other'    => ['userpath' => $fresh],
                ]);
                ob_start();
                try {
                    relation_update::updated_single($event);
                } finally {
                    ob_end_clean();
                }
            }
        }
    }

    /**
     * Complete a course for every subscribed learner and let the REAL
     * \core\event\course_completed observer chain recompute the paths.
     *
     * Unlike "is completed for every subscribed learner" (which fires
     * user_path_updated directly), this step exercises the genuine event chain:
     * it records the course completion and then triggers
     * \core\event\course_completed with the SYSTEM/admin as the acting user
     * (event->userid) while the affected learner is carried in
     * event->relateduserid - exactly as Moodle's completion aggregation cron
     * does. It therefore guards the fix where the observer must resolve the
     * learner via relateduserid rather than the acting user.
     *
     * @Given /^the course "(?P<shortname>[^"]+)" is completed and aggregated by the system$/
     *
     * @param string $shortname The course shortname whose completion should be recorded.
     */
    public function the_course_is_completed_and_aggregated_by_the_system(string $shortname): void {
        global $DB, $USER;

        $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
        $courseid = (int) $course->id;

        $records = $DB->get_records('local_adele_path_user', ['status' => 'active']);
        if (empty($records)) {
            throw new \RuntimeException('No active learner snapshots found to complete a course for.');
        }

        // Act as the system/admin user, so the triggered course_completed event
        // carries userid = admin (the acting process) and NOT the learner.
        $olduser = $USER;
        \core\session\manager::set_user(get_admin());

        try {
            $cache = \cache::make('core', 'coursecompletion');
            $coursecontext = \context_course::instance($courseid);

            foreach ($records as $record) {
                $userid = (int) $record->user_id;

                if (!$DB->record_exists('course_completions', ['course' => $courseid, 'userid' => $userid])) {
                    $ccid = $DB->insert_record('course_completions', (object) [
                        'course'        => $courseid,
                        'userid'        => $userid,
                        'timeenrolled'  => time(),
                        'timestarted'   => time(),
                        'timecompleted' => time(),
                        'reaggregate'   => 0,
                    ]);
                } else {
                    $DB->set_field(
                        'course_completions',
                        'timecompleted',
                        time(),
                        ['course' => $courseid, 'userid' => $userid]
                    );
                    $ccid = (int) $DB->get_field(
                        'course_completions',
                        'id',
                        ['course' => $courseid, 'userid' => $userid],
                        MUST_EXIST
                    );
                }
                $cache->delete($userid . '_' . $courseid);

                // Fire the genuine core event: the affected learner is the
                // relateduserid; the acting user is the admin set above.
                $event = \core\event\course_completed::create([
                    'objectid'      => $ccid,
                    'relateduserid' => $userid,
                    'context'       => $coursecontext,
                    'courseid'      => $courseid,
                    'other'         => ['relateduserid' => $userid],
                ]);
                ob_start();
                try {
                    $event->trigger();
                } finally {
                    ob_end_clean();
                }
            }
        } finally {
            \core\session\manager::set_user($olduser);
        }
    }

    /**
     * Complete a course for a single learner and fire the REAL \core\event\course_completed
     * as a DIFFERENT actor (e.g. a teacher who graded them). The event then carries the
     * actor in userid and the learner in relateduserid - the exact #495 condition. Unlike
     * "the course ... is completed for every subscribed learner", this drives the genuine
     * event -> observer chain (db/events.php -> completion::completed), so it exercises the
     * routing that must use relateduserid rather than the actor.
     *
     * @Given /^"(?P<actor>[^"]+)" completes the course "(?P<shortname>[^"]+)" for "(?P<learner>[^"]+)"$/
     *
     * @param string $actor username of the user triggering completion (the event actor).
     * @param string $shortname course shortname to complete.
     * @param string $learner username of the learner whose course is completed.
     */
    public function actor_completes_course_for_learner(string $actor, string $shortname, string $learner): void {
        global $DB;
        $courseid = (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
        $actoruser = $DB->get_record('user', ['username' => $actor], '*', MUST_EXIST);
        $learnerid = (int) $DB->get_field('user', 'id', ['username' => $learner], MUST_EXIST);

        // Record the course completion for the learner (idempotent).
        $key = ['course' => $courseid, 'userid' => $learnerid];
        if (!$DB->record_exists('course_completions', $key)) {
            $DB->insert_record('course_completions', (object) [
                'course'        => $courseid,
                'userid'        => $learnerid,
                'timeenrolled'  => time(),
                'timestarted'   => time(),
                'timecompleted' => time(),
                'reaggregate'   => 0,
            ]);
        } else {
            $DB->set_field('course_completions', 'timecompleted', time(), $key);
        }
        \cache::make('core', 'coursecompletion')->delete($learnerid . '_' . $courseid);
        $completion = $DB->get_record('course_completions', $key, '*', MUST_EXIST);

        // Fire the real event AS THE ACTOR: create_from_completion sets relateduserid to the
        // learner and lets userid auto-fill from the current user, so we become the actor first.
        \core\session\manager::set_user($actoruser);
        try {
            \core\event\course_completed::create_from_completion($completion)->trigger();
        } finally {
            \core\session\manager::set_user(get_admin());
        }
    }

    /**
     * Click a button only when it is currently present (conditional UI flows,
     * e.g. a discard-confirmation prompt that may or may not appear).
     *
     * @When /^I click on "(?P<button>[^"]+)" "button" if it exists$/
     *
     * @param string $button Button label.
     */
    public function i_click_on_button_if_it_exists(string $button): void {
        try {
            $node = $this->find_button($button);
        } catch (\Exception $e) {
            return;
        }
        if ($node->isVisible()) {
            $node->click();
        }
    }

    /**
     * Click an element by CSS selector only when it is currently present.
     *
     * @When /^I click on "(?P<selector>[^"]+)" "css_element" if it exists$/
     *
     * @param string $selector CSS selector.
     */
    public function i_click_on_css_if_it_exists(string $selector): void {
        $node = $this->getSession()->getPage()->find('css', $selector);
        if ($node && $node->isVisible()) {
            $node->click();
        }
    }

    /**
     * Assert exactly one element matches a CSS selector (guards against stale
     * duplicate DOM from an incompletely unmounted editor session).
     *
     * @Then /^exactly one "(?P<selector>[^"]+)" element should exist$/
     *
     * @param string $selector CSS selector.
     */
    public function exactly_one_element_should_exist(string $selector): void {
        $count = count($this->getSession()->getPage()->findAll('css', $selector));
        if ($count !== 1) {
            throw new \RuntimeException("Expected exactly 1 element for '{$selector}', found {$count}.");
        }
    }

    /**
     * Assert a stored restriction-condition value of a SAVED learning path.
     *
     * @Then /^learning path "([^"]+)" node "([^"]+)" restriction "([^"]+)" value "([^"]+)" should be "([^"]*)"$/
     *
     * @param string $lpname Learning path name.
     * @param string $nodeid Tree node id.
     * @param string $label Condition label (e.g. timed_duration).
     * @param string $field Value field name (e.g. selectedDuration).
     * @param string $expected Expected stored value.
     */
    public function learning_path_restriction_value_should_be(
        string $lpname,
        string $nodeid,
        string $label,
        string $field,
        string $expected
    ): void {
        global $DB;
        $lp = $DB->get_record('local_adele_learning_paths', ['name' => $lpname], '*', MUST_EXIST);
        $json = json_decode($lp->json, true);
        foreach (($json['tree']['nodes'] ?? []) as $node) {
            if (($node['id'] ?? '') !== $nodeid) {
                continue;
            }
            foreach (($node['restriction']['nodes'] ?? []) as $c) {
                if (($c['data']['label'] ?? '') !== $label) {
                    continue;
                }
                $actual = (string) ($c['data']['value'][$field] ?? '');
                if ($actual !== $expected) {
                    throw new \RuntimeException(
                        "Stored {$label}.{$field} of {$nodeid} is '{$actual}', expected '{$expected}'."
                    );
                }
                return;
            }
            throw new \RuntimeException("No {$label} condition stored on {$nodeid}.");
        }
        throw new \RuntimeException("Node {$nodeid} not found in \"{$lpname}\".");
    }

    /**
     * Assert a node of a SAVED learning path carries no restriction conditions.
     *
     * Guards the criteria editor's Close flow (#476/#494 retests): a criterion
     * added on the canvas and then abandoned via "Close" must not survive into
     * the persisted learning-path json.
     *
     * @Then /^learning path "(?P<lpname>[^"]+)" node "(?P<nodeid>[^"]+)" should have no restriction conditions$/
     *
     * @param string $lpname Learning path name.
     * @param string $nodeid Tree node id (e.g. dndnode_1).
     */
    public function learning_path_node_should_have_no_restriction(string $lpname, string $nodeid): void {
        global $DB;
        $lp = $DB->get_record('local_adele_learning_paths', ['name' => $lpname], '*', MUST_EXIST);
        $json = json_decode($lp->json, true);
        foreach (($json['tree']['nodes'] ?? []) as $node) {
            if (($node['id'] ?? '') !== $nodeid) {
                continue;
            }
            $conditions = $node['restriction']['nodes'] ?? [];
            $real = array_filter($conditions, static function ($c) {
                return ($c['type'] ?? '') !== 'feedback' && isset($c['data']['label']);
            });
            if ($real !== []) {
                throw new \RuntimeException(sprintf(
                    'Node %s of "%s" unexpectedly carries %d restriction condition(s): %s',
                    $nodeid,
                    $lpname,
                    count($real),
                    implode(', ', array_map(static fn($c) => $c['data']['label'], $real))
                ));
            }
            return;
        }
        throw new \RuntimeException("Node {$nodeid} not found in learning path \"{$lpname}\".");
    }

    /**
     * Assert the current page carries no Moodle exception / coding-error notice.
     *
     * Guards scenarios (e.g. #464 get_availablecourses $PAGE codingerror) where a
     * server-side exception would render as an errorbox/exception notification or a
     * debugging block rather than a hard 500 - the SPA would still shell out but the
     * web service feeding it would have failed.
     *
     * @Then /^I should not see a coding error notice$/
     */
    public function i_should_not_see_a_coding_error_notice(): void {
        $script = <<<'JS'
            (function() {
              const text = document.body ? document.body.innerText : '';
              const markers = ['Coding error detected', 'Exception - ', 'Debug info:',
                'Error reading from database', 'Uncaught Error', 'must be overridden'];
              for (const m of markers) {
                if (text.indexOf(m) !== -1) {
                  return m;
                }
              }
              // A rendered Moodle exception/errorbox element.
              if (document.querySelector('.errorbox, .alert-danger .stacktrace, .stacktrace')) {
                return 'errorbox';
              }
              return '';
            })();
            JS;
        $found = $this->getSession()->evaluateScript($script);
        if (!empty($found)) {
            throw new \RuntimeException("A coding-error / exception notice was rendered on the page: '{$found}'.");
        }
    }

    /**
     * Open a runtime node's feedback panel (the fa-comment toggle in UserInformation).
     *
     * The toggle-button lives inside a Vue-Flow-transformed node card that may be
     * partially off-screen or overlapped, so a native Behat click can be reported as
     * "element not interactable". This dispatches a real click on the toggle-button
     * directly, flipping showFeedbackarea so the v-html-bound feedback string renders.
     *
     * @When /^I open the runtime feedback panel for node "(?P<node>[^"]+)"$/
     *
     * @param string $node The node id (e.g. dndnode_2).
     */
    public function i_open_the_runtime_feedback_panel_for_node(string $node): void {
        $nodejson = json_encode($node, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const container = document.querySelector('.' + $nodejson + '_user_info_listener');
              if (!container) {
                throw new Error('Runtime info container not found for node: ' + $nodejson);
              }
              const toggle = container.querySelector('.toggle-button');
              if (!toggle) {
                throw new Error('Feedback toggle button not found for node: ' + $nodejson);
              }
              toggle.scrollIntoView({block: 'center', inline: 'center'});
              toggle.click();
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Assert an XSS payload name is rendered inert - present as text, never as a live element.
     *
     * Proves #464 H4/M5 end to end: a course/node name containing
     * "<img src=x onerror=...>" must never materialise as a real <img> element with an
     * onerror handler in the DOM (which would execute), even though its textual content
     * may legitimately appear (escaped) inside a v-html-bound feedback string.
     *
     * @Then /^no live "(?P<tag>[^"]+)" element with an "(?P<attr>[^"]+)" attribute should exist$/
     *
     * @param string $tag  The element tag name to look for (e.g. img).
     * @param string $attr The dangerous attribute (e.g. onerror).
     */
    public function no_live_element_with_attribute_should_exist(string $tag, string $attr): void {
        $tagjson = json_encode($tag, JSON_UNESCAPED_SLASHES);
        $attrjson = json_encode($attr, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const els = Array.from(document.querySelectorAll($tagjson));
              const live = els.filter(el => el.hasAttribute($attrjson));
              return live.length;
            })();
            JS;
        $count = (int) $this->getSession()->evaluateScript($script);
        if ($count > 0) {
            throw new \RuntimeException(
                "Found {$count} live <{$tag}> element(s) carrying the '{$attr}' attribute - the payload was not neutralised."
            );
        }
    }
}
