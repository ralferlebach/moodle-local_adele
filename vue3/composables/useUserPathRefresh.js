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
 * Refresh of an open learning path (mod_adele #26).
 *
 * The component loads the path once; this composable keeps it current by the
 * site's mode:
 *   0  nothing after the first load;
 *   1  a refresh when the page gets the focus back (visibilitychange to
 *      visible, window focus, pageshow from the back/forward cache);
 *   2  that, and polling while the page is visible and focused - paused when
 *      it loses the focus, resumed by the next focus refresh;
 *   3  that, and polling in the background too (browsers may throttle it).
 * Focus events closer together than the debounce window cause one refresh.
 * A refresh never starts while another is in flight; polling waits for the
 * previous answer before it schedules the next (no parallel requests).
 * Failures are logged, back off (interval x2, x4, x8, at most 5 minutes) and
 * are shown once after three in a row; a success resets that. A request
 * without an answer counts as failed after requestTimeout(); an answer that
 * arrives after that, or after stop(), is ignored, so an older state never
 * replaces a newer one. stop() removes every listener and timer.
 *
 * What a refresh does is the caller's fetch: it reads the stored state only
 * (local_adele_get_user_path_relation), never a recalculation.
 *
 * @module     local_adele/useUserPathRefresh
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { POLLING_ACTIVE, POLLING_ALWAYS, POLLING_OFF } from './flowHelper/pathViewSettings';

/** Failures in a row before the person is told once. */
export const FAILURES_BEFORE_NOTICE = 3;

/** The longest wait between two polls after failures (ms). */
export const MAX_BACKOFF = 300000;

/**
 * How long one request may take before it counts as failed (ms): three
 * intervals, at least 10 s, at most 60 s. A request that never answers must
 * not hold the refresh up for good; its late answer is ignored.
 *
 * @param {number} interval
 * @returns {number}
 */
export const requestTimeout = (interval) => Math.min(60000, Math.max(10000, 3 * interval));

/**
 * Create the refresh for one mounted view.
 *
 * @param {object} options
 * @param {Function} options.fetch async () => result: read the stored state.
 * @param {Function} options.onResult async (result, reason) => void: show it; reason 'focus' or 'poll'.
 * @param {object} options.settings pollingmode, pollinginterval, pollingdebounce.
 * @param {Function} [options.isBusy] () => bool: skip a poll while the person interacts.
 * @param {Function} [options.onPersistentError] () => void: shown once after repeated failures.
 * @param {Function} [options.log] (message, error) => void: debug output.
 * @param {Window} [options.win] The window (tests).
 * @param {Document} [options.doc] The document (tests).
 * @returns {{start: Function, stop: Function, refresh: Function, state: object}}
 */
export function useUserPathRefresh(options) {
  const {
    fetch,
    onResult,
    settings,
    isBusy = () => false,
    onPersistentError = () => {},
    log = () => {},
    win = window,
    doc = document,
  } = options;
  const mode = settings.pollingmode;
  const state = {
    running: false,
    inFlight: false,
    paused: false,
    failures: 0,
    noticeShown: false,
    requests: 0,
    // Each request has a number; only the answer of the latest one counts.
    generation: 0,
  };
  let debounceTimer = null;
  let pollTimer = null;

  const active = () => doc.visibilityState === 'visible' && (typeof doc.hasFocus !== 'function' || doc.hasFocus());

  /**
   * One refresh, unless one is in flight.
   *
   * @param {string} reason 'focus' (the page came back) or 'poll'.
   * @returns {Promise<boolean>} Whether it ran.
   */
  const run = async (reason = 'focus') => {
    if (!state.running || state.inFlight) {
      return false;
    }
    state.inFlight = true;
    state.requests++;
    const generation = ++state.generation;
    let timer = null;
    const timeout = new Promise((resolve, reject) => {
      timer = setTimeout(() => reject(new Error('timeout')), requestTimeout(settings.pollinginterval));
    });
    try {
      const result = await Promise.race([fetch(), timeout]);
      if (!state.running || generation !== state.generation) {
        // Stopped meanwhile, or overtaken: an older state must not come back.
        return true;
      }
      state.failures = 0;
      state.noticeShown = false;
      await onResult(result, reason);
    } catch (error) {
      state.failures++;
      log('local_adele: refreshing the learning path failed', error);
      if (state.failures >= FAILURES_BEFORE_NOTICE && !state.noticeShown) {
        state.noticeShown = true;
        onPersistentError();
      }
    } finally {
      clearTimeout(timer);
      if (generation === state.generation) {
        state.inFlight = false;
      }
    }
    return true;
  };

  const clearPoll = () => {
    clearTimeout(pollTimer);
    pollTimer = null;
  };

  /**
   * Schedule the next poll after the interval (longer after failures).
   */
  const schedulePoll = () => {
    clearPoll();
    if (!state.running || mode < POLLING_ACTIVE) {
      return;
    }
    if (mode === POLLING_ACTIVE && !active()) {
      state.paused = true;
      return;
    }
    state.paused = false;
    const factor = 2 ** Math.min(state.failures, 3);
    const delay = Math.min(settings.pollinginterval * factor, MAX_BACKOFF);
    pollTimer = setTimeout(tick, delay);
  };

  const tick = async () => {
    pollTimer = null;
    if (!state.running) {
      return;
    }
    if (mode === POLLING_ACTIVE && !active()) {
      state.paused = true;
      return;
    }
    if (!isBusy()) {
      await run('poll');
    }
    schedulePoll();
  };

  /**
   * A focus-type event: one refresh after the debounce window, then polling
   * resumes (mode 2).
   */
  const request = () => {
    if (!state.running) {
      return;
    }
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(async () => {
      debounceTimer = null;
      clearPoll();
      await run('focus');
      schedulePoll();
    }, settings.pollingdebounce);
  };

  const onVisibility = () => {
    if (doc.visibilityState === 'visible') {
      request();
    } else if (mode === POLLING_ACTIVE) {
      clearPoll();
      state.paused = true;
    }
  };
  const onFocus = () => request();
  const onBlur = () => {
    if (mode === POLLING_ACTIVE) {
      clearPoll();
      state.paused = true;
    }
  };
  // The first load fires pageshow too; only a page restored from the
  // back/forward cache is a return.
  const onPageShow = (event) => {
    if (event && event.persisted) {
      request();
    }
  };

  /**
   * Begin: listeners for modes 1-3, polling for modes 2 and 3. Does not
   * fetch - the first load is the component's own.
   */
  const start = () => {
    if (state.running || mode === POLLING_OFF) {
      return;
    }
    state.running = true;
    doc.addEventListener('visibilitychange', onVisibility);
    win.addEventListener('focus', onFocus);
    win.addEventListener('blur', onBlur);
    win.addEventListener('pageshow', onPageShow);
    if (mode === POLLING_ACTIVE || mode === POLLING_ALWAYS) {
      schedulePoll();
    }
  };

  /**
   * End: no listener and no timer is left.
   */
  const stop = () => {
    state.running = false;
    // An answer still on its way belongs to nobody any more.
    state.generation++;
    state.inFlight = false;
    clearTimeout(debounceTimer);
    debounceTimer = null;
    clearPoll();
    doc.removeEventListener('visibilitychange', onVisibility);
    win.removeEventListener('focus', onFocus);
    win.removeEventListener('blur', onBlur);
    win.removeEventListener('pageshow', onPageShow);
  };

  return { start, stop, refresh: run, state };
}
