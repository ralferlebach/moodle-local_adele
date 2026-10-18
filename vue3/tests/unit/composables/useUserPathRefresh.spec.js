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
 * Refresh of an open learning path (mod_adele #26, #32): modes, focus,
 * debounce, polling, no parallel requests, failures, cleanup.
 *
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {
  FAILURES_BEFORE_NOTICE, MAX_BACKOFF, requestTimeout, useUserPathRefresh,
} from '../../../composables/useUserPathRefresh';

/**
 * A window and a document of our own: events, visibility, focus, and a
 * count of the listeners still attached.
 *
 * @returns {object}
 */
function browser() {
  const make = () => {
    const listeners = {};
    return {
      listeners,
      addEventListener: (type, fn) => {
        (listeners[type] = listeners[type] || new Set()).add(fn);
      },
      removeEventListener: (type, fn) => {
        if (listeners[type]) {
          listeners[type].delete(fn);
        }
      },
      fire: (type, event = {}) => [...(listeners[type] || [])].forEach((fn) => fn(event)),
      count: () => Object.values(listeners).reduce((n, set) => n + set.size, 0),
    };
  };
  const win = make();
  const doc = make();
  doc.visibilityState = 'visible';
  doc.focused = true;
  doc.hasFocus = () => doc.focused;
  return { win, doc };
}

/**
 * A fetch that answers when told to (a slow request), counting calls and
 * how many are open at once.
 *
 * @returns {object}
 */
function slowServer() {
  const server = { calls: 0, open: 0, maxOpen: 0, pending: [] };
  server.fetch = () => {
    server.calls++;
    server.open++;
    server.maxOpen = Math.max(server.maxOpen, server.open);
    return new Promise((resolve, reject) => {
      server.pending.push({
        resolve: (v) => {
          server.open--;
          resolve(v);
        },
        reject: (e) => {
          server.open--;
          reject(e);
        },
      });
    });
  };
  server.answer = async (value = { json: {} }) => {
    const p = server.pending.shift();
    p.resolve(value);
    await flush();
  };
  // Answer the request at position i of those still open (0 = oldest).
  server.answerAt = async (i, value) => {
    const p = server.pending.splice(i, 1)[0];
    p.resolve(value);
    await flush();
  };
  server.fail = async () => {
    const p = server.pending.shift();
    p.reject(new Error('down'));
    await flush();
  };
  return server;
}

const flush = async () => {
  for (let i = 0; i < 5; i++) {
    await Promise.resolve();
  }
};

const settings = (mode, interval = 3000, debounce = 300) => ({
  pollingmode: mode, pollinginterval: interval, pollingdebounce: debounce,
});

describe('useUserPathRefresh', () => {
  beforeEach(() => {
    jest.useFakeTimers();
  });
  afterEach(() => {
    jest.useRealTimers();
  });

  /**
   * Set up a refresh.
   *
   * @param {number} mode
   * @param {object} extra More options.
   * @returns {object}
   */
  const setup = (mode, extra = {}) => {
    const { win, doc } = browser();
    const server = extra.server || slowServer();
    const onResult = jest.fn();
    const r = useUserPathRefresh({
      fetch: server.fetch, onResult, settings: extra.settings || settings(mode), win, doc, ...extra.options,
    });
    return { win, doc, server, onResult, r };
  };

  it('mode 0: no listener, no timer, no request - ever', async () => {
    const { win, doc, server, r } = setup(0);
    r.start();
    expect(win.count() + doc.count()).toBe(0);
    win.fire('focus');
    doc.fire('visibilitychange');
    jest.advanceTimersByTime(60000);
    await flush();
    expect(server.calls).toBe(0);
  });

  it('start() fetches nothing itself; the first load stays the only one, also with lifecycle events at load', async () => {
    const { win, server, r } = setup(1);
    r.start();
    // The first page load fires pageshow (not persisted) - that is no return.
    win.fire('pageshow', { persisted: false });
    jest.advanceTimersByTime(10000);
    await flush();
    expect(server.calls).toBe(0);
  });

  it('mode 1: focus, visible and a page from the back/forward cache refresh; events within the debounce window count once', async () => {
    const { win, doc, server, onResult, r } = setup(1);
    r.start();
    win.fire('focus');
    doc.fire('visibilitychange');
    win.fire('pageshow', { persisted: true });
    jest.advanceTimersByTime(299);
    expect(server.calls).toBe(0);
    jest.advanceTimersByTime(1);
    await flush();
    expect(server.calls).toBe(1);
    await server.answer({ v: 1 });
    expect(onResult).toHaveBeenCalledWith({ v: 1 }, 'focus');
    jest.advanceTimersByTime(60000);
    await flush();
    expect(server.calls).toBe(1);
  });

  it('mode 1: no polling', async () => {
    const { server, r } = setup(1);
    r.start();
    jest.advanceTimersByTime(30000);
    await flush();
    expect(server.calls).toBe(0);
  });

  it('mode 2: polls at the interval while visible and focused', async () => {
    const { server, onResult, r } = setup(2);
    r.start();
    jest.advanceTimersByTime(2999);
    await flush();
    expect(server.calls).toBe(0);
    jest.advanceTimersByTime(1);
    await flush();
    expect(server.calls).toBe(1);
    await server.answer({ v: 2 });
    expect(onResult).toHaveBeenLastCalledWith({ v: 2 }, 'poll');
    jest.advanceTimersByTime(3000);
    await flush();
    expect(server.calls).toBe(2);
  });

  it('mode 2: pauses when the window loses focus or the tab is hidden; a focus refresh resumes it', async () => {
    const { win, doc, server, r } = setup(2);
    r.start();
    win.fire('blur');
    doc.focused = false;
    jest.advanceTimersByTime(30000);
    await flush();
    expect(server.calls).toBe(0);
    expect(r.state.paused).toBe(true);

    doc.focused = true;
    win.fire('focus');
    jest.advanceTimersByTime(300);
    await flush();
    expect(server.calls).toBe(1);
    await server.answer();
    jest.advanceTimersByTime(3000);
    await flush();
    expect(server.calls).toBe(2);
    await server.answer();

    doc.visibilityState = 'hidden';
    doc.fire('visibilitychange');
    jest.advanceTimersByTime(30000);
    await flush();
    expect(server.calls).toBe(2);
  });

  it('mode 3: keeps polling in the background', async () => {
    const { win, doc, server, r } = setup(3);
    r.start();
    win.fire('blur');
    doc.focused = false;
    doc.visibilityState = 'hidden';
    doc.fire('visibilitychange');
    jest.advanceTimersByTime(3000);
    await flush();
    expect(server.calls).toBe(1);
    await server.answer();
    jest.advanceTimersByTime(3000);
    await flush();
    expect(server.calls).toBe(2);
  });

  it('never two requests at once: a slow answer holds polls and focus refreshes back', async () => {
    const { win, server, r } = setup(2, { settings: settings(2, 1000, 0) });
    r.start();
    jest.advanceTimersByTime(1000);
    await flush();
    expect(server.calls).toBe(1);
    // The answer takes long (but less than the request timeout); focus
    // events and polls fall due meanwhile.
    for (let i = 0; i < 5; i++) {
      win.fire('focus');
      jest.advanceTimersByTime(1500);
      await flush();
    }
    expect(server.maxOpen).toBe(1);
    expect(server.calls).toBe(1);
    await server.answer();
    jest.advanceTimersByTime(1000);
    await flush();
    expect(server.calls).toBe(2);
    expect(server.maxOpen).toBe(1);
  });

  it('failures: logged, backing off, told once after three in a row; later attempts continue; a success resets', async () => {
    const log = jest.fn();
    const onPersistentError = jest.fn();
    const { server, r } = setup(2, { settings: settings(2, 1000, 0), options: { log, onPersistentError } });
    r.start();
    const waits = [1000, 2000, 4000, 8000, 8000];
    for (const wait of waits) {
      jest.advanceTimersByTime(wait - 1);
      await flush();
      const before = server.calls;
      jest.advanceTimersByTime(1);
      await flush();
      expect(server.calls).toBe(before + 1);
      await server.fail();
    }
    expect(log).toHaveBeenCalledTimes(5);
    expect(onPersistentError).toHaveBeenCalledTimes(1);
    jest.advanceTimersByTime(8000);
    await flush();
    await server.answer();
    expect(r.state.failures).toBe(0);
    jest.advanceTimersByTime(1000);
    await flush();
    expect(server.pending.length).toBe(1);
  });

  it('the back-off never waits longer than five minutes', async () => {
    const { server, r } = setup(2, { settings: settings(2, 300000, 0) });
    r.start();
    jest.advanceTimersByTime(300000);
    await flush();
    await server.fail();
    jest.advanceTimersByTime(MAX_BACKOFF);
    await flush();
    expect(server.calls).toBe(2);
    expect(FAILURES_BEFORE_NOTICE).toBe(3);
  });

  it('a poll waits while the person interacts', async () => {
    let busy = true;
    const { server, r } = setup(2, { settings: settings(2, 1000, 0), options: { isBusy: () => busy } });
    r.start();
    jest.advanceTimersByTime(5000);
    await flush();
    expect(server.calls).toBe(0);
    busy = false;
    jest.advanceTimersByTime(1000);
    await flush();
    expect(server.calls).toBe(1);
  });

  it('stop(): every listener and timer goes; an answer arriving later changes nothing', async () => {
    const { win, doc, server, onResult, r } = setup(2, { settings: settings(2, 1000, 0) });
    r.start();
    expect(win.count() + doc.count()).toBe(4);
    jest.advanceTimersByTime(1000);
    await flush();
    r.stop();
    expect(win.count() + doc.count()).toBe(0);
    await server.answer({ late: true });
    expect(onResult).not.toHaveBeenCalled();
    jest.advanceTimersByTime(60000);
    win.fire('focus');
    await flush();
    expect(server.calls).toBe(1);
    expect(jest.getTimerCount()).toBe(0);
  });

  it('start() twice registers once', () => {
    const { win, doc, r } = setup(1);
    r.start();
    r.start();
    expect(win.count() + doc.count()).toBe(4);
  });

  it('a request that never answers does not hold the refresh up: after the timeout it counts as failed, the next one starts', async () => {
    const log = jest.fn();
    const { server, onResult, r } = setup(2, { settings: settings(2, 1000, 0), options: { log } });
    r.start();
    jest.advanceTimersByTime(1000);
    await flush();
    expect(server.calls).toBe(1);
    expect(requestTimeout(1000)).toBe(10000);
    jest.advanceTimersByTime(10000);
    await flush();
    expect(log).toHaveBeenCalledTimes(1);
    expect(r.state.inFlight).toBe(false);
    // Backed off once (x2), then the next request.
    jest.advanceTimersByTime(2000);
    await flush();
    expect(server.calls).toBe(2);
    await server.answerAt(1, { v: 'new' });
    expect(onResult).toHaveBeenLastCalledWith({ v: 'new' }, 'poll');
    // The first, hung request answers at last, with an older state: ignored.
    await server.answerAt(0, { v: 'old' });
    expect(onResult).toHaveBeenCalledTimes(1);
    expect(onResult).not.toHaveBeenCalledWith({ v: 'old' }, expect.anything());
  });

  it('the timeout of a request: three intervals, at least 10 s, at most 60 s', () => {
    expect(requestTimeout(1000)).toBe(10000);
    expect(requestTimeout(5000)).toBe(15000);
    expect(requestTimeout(300000)).toBe(60000);
  });

  it('null or empty answers are passed on as they come; the refresh keeps going', async () => {
    const { server, onResult, r } = setup(2, { settings: settings(2, 1000, 0) });
    r.start();
    jest.advanceTimersByTime(1000);
    await flush();
    await server.answer(null);
    expect(onResult).toHaveBeenLastCalledWith(null, 'poll');
    jest.advanceTimersByTime(1000);
    await flush();
    await server.answer({});
    expect(onResult).toHaveBeenLastCalledWith({}, 'poll');
    expect(r.state.failures).toBe(0);
  });
});
