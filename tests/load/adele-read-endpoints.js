import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Trend } from 'k6/metrics';

/**
 * Minimal read-endpoint load plan for local_adele.
 *
 * Scope on purpose: it drives the three pages a learner and a teacher hit
 * most, as an authenticated session, and asserts that they answer 200 and
 * are not Moodle error pages. It is here to prove the load pipeline works
 * end to end. Node-level operations, the editor and the web service layer
 * come later.
 *
 * Live data (local_adele #14 DoD2-L5, #15 DoD3.5; re-audit 2026-11-22):
 * with CONTEXTID - the module context of the ADELE activity seed_large.php
 * creates when mod_adele is installed - every iteration also asks, as the
 * teacher view does, for the live snapshot, the group overview and the names
 * behind the "completed" counter of node dndnode_1, through Moodle's AJAX
 * web service endpoint. Each gets its own duration trend and threshold.
 *
 * The live part is REQUIRED unless LIVE=off (re-audit 2026-11-26: a run
 * without CONTEXTID used to pass as a "pages only" run and looked like a
 * load test of the live data). Without CONTEXTID the run stops in setup();
 * every answered live call counts in adele_live_calls, and a run that
 * answered none fails its threshold - so a green run has measured the three
 * live endpoints.
 *
 * Required: BASE_URL, COURSEID, ADMIN_USER, ADMIN_PASSWORD, CONTEXTID.
 * Optional: VUS, DURATION, LIVE=off (pages only, on purpose).
 */

const BASE_URL = __ENV.BASE_URL;
const COURSEID = __ENV.COURSEID;
const ADMIN_USER = __ENV.ADMIN_USER;
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD;

const overview = new Trend('adele_overview_duration', true);
const CONTEXTID = Number(__ENV.CONTEXTID || 0);
const LIVE = (__ENV.LIVE || 'required') !== 'off';
const livecalls = new Counter('adele_live_calls');
const live = {
  local_adele_get_live_snapshot: new Trend('adele_live_snapshot_duration', true),
  local_adele_get_group_overview: new Trend('adele_group_overview_duration', true),
  local_adele_get_node_people: new Trend('adele_node_people_duration', true),
};

export const options = {
  vus: Number(__ENV.VUS || 10),
  duration: __ENV.DURATION || '30s',
  thresholds: {
    // Deliberately loose. A first run establishes what the numbers are; a
    // threshold tightened before that would only encode a guess.
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<5000'],
    // The live calls are polled every 30 s by every open teacher view: they
    // have to stay well below the page budget. Loose as well until a first
    // run has measured them.
    ...(LIVE ? {
      // Each of the three answered at least once - not just one of them.
      'adele_live_calls{method:local_adele_get_live_snapshot}': ['count>0'],
      'adele_live_calls{method:local_adele_get_group_overview}': ['count>0'],
      'adele_live_calls{method:local_adele_get_node_people}': ['count>0'],
      adele_live_snapshot_duration: ['p(95)<3000'],
      adele_group_overview_duration: ['p(95)<3000'],
      adele_node_people_duration: ['p(95)<3000'],
      checks: ['rate>0.95'],
    } : {}),
  },
};

/**
 * Call one web service as the page's own JavaScript does (core/ajax).
 *
 * @param {string} baseUrl
 * @param {string} sesskey
 * @param {string} methodname
 * @param {object} args
 */
function callService(baseUrl, sesskey, methodname, args) {
  const res = http.post(
    `${baseUrl}/lib/ajax/service.php?sesskey=${sesskey}&info=${methodname}`,
    JSON.stringify([{ index: 0, methodname, args }]),
    { headers: { 'Content-Type': 'application/json' }, tags: { name: methodname } }
  );
  live[methodname].add(res.timings.duration);
  let answer = null;
  try {
    answer = JSON.parse(res.body)[0];
  } catch (e) {
    answer = null;
  }
  if (res.status === 200 && answer && answer.error === false) {
    livecalls.add(1, { method: methodname });
  }
  check(res, {
    [`${methodname}: HTTP 200`]: (r) => r.status === 200,
    [`${methodname}: no error`]: () => !!answer && answer.error === false,
  });
  return answer ? answer.data : null;
}

/**
 * Log in once per virtual user and keep the session for the whole iteration
 * set. Moodle protects the login form with a token bound to the session, so
 * the token has to be read from the form that the same cookie jar received.
 */
export function setup() {
  if (!BASE_URL || !COURSEID || !ADMIN_USER || !ADMIN_PASSWORD) {
    throw new Error('BASE_URL, COURSEID, ADMIN_USER and ADMIN_PASSWORD are all required.');
  }
  if (LIVE && !CONTEXTID) {
    throw new Error('CONTEXTID is required: the module context of an ADELE activity with live markers. '
      + 'LIVE=off runs the pages only, on purpose.');
  }
  return { baseUrl: BASE_URL, courseid: COURSEID };
}

export default function (data) {
  const jar = http.cookieJar();
  const loginPage = http.get(`${data.baseUrl}/login/index.php`);
  const token = (loginPage.body.match(/name="logintoken" value="([^"]+)"/) || [])[1];
  check(token, { 'login token found': (t) => !!t });

  const loggedIn = http.post(`${data.baseUrl}/login/index.php`, {
    anchor: '',
    logintoken: token,
    username: ADMIN_USER,
    password: ADMIN_PASSWORD,
  });
  check(loggedIn, {
    'logged in': (r) => r.status === 200 && !r.url.includes('/login/index.php'),
  });

  const pages = [
    { name: 'learning path overview', url: `${data.baseUrl}/local/adele/index.php` },
    { name: 'course view', url: `${data.baseUrl}/course/view.php?id=${data.courseid}` },
    { name: 'participants', url: `${data.baseUrl}/user/index.php?id=${data.courseid}` },
  ];

  for (const page of pages) {
    const res = http.get(page.url);
    if (page.name === 'learning path overview') {
      overview.add(res.timings.duration);
    }
    check(res, {
      [`${page.name}: HTTP 200`]: (r) => r.status === 200,
      // A Moodle exception is served WITH status 200, so the status alone
      // proves nothing; the error box is what distinguishes them.
      [`${page.name}: no Moodle error page`]: (r) => !r.body.includes('class="errorbox'),
    });
  }

  if (LIVE) {
    // The session key from a page of the same session, as core/ajax has it.
    const page = http.get(`${data.baseUrl}/course/view.php?id=${data.courseid}`);
    const match = /"sesskey":"([^"]+)"/.exec(page.body || '');
    check(page, { 'session key found': () => !!match });
    if (match) {
      const snapshot = callService(data.baseUrl, match[1], 'local_adele_get_live_snapshot', { contextid: CONTEXTID, groupid: 0 });
      check(snapshot, {
        'live snapshot: enabled, with markers': (s) => !!s && s.enabled === true
          && Array.isArray(s.nodes) && s.nodes.some((n) => (n.markers || []).length > 0),
      });
      callService(data.baseUrl, match[1], 'local_adele_get_group_overview', { contextid: CONTEXTID, groupid: 0 });
      const people = callService(data.baseUrl, match[1], 'local_adele_get_node_people',
        { contextid: CONTEXTID, groupid: 0, nodeid: 'dndnode_1', kind: 'active', page: 0 });
      check(people, { 'names behind the active counter': (p) => !!p && p.total > 0 });
    }
  }

  jar.clear(`${data.baseUrl}/`);
  sleep(1);
}
