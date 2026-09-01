import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend } from 'k6/metrics';

/**
 * Minimal read-endpoint load plan for local_adele.
 *
 * Scope on purpose: it drives the three pages a learner and a teacher hit
 * most, as an authenticated session, and asserts that they answer 200 and
 * are not Moodle error pages. It is here to prove the load pipeline works
 * end to end. Node-level operations, the editor and the web service layer
 * come later.
 *
 * Required: BASE_URL, COURSEID, ADMIN_USER, ADMIN_PASSWORD.
 * Optional: VUS, DURATION.
 */

const BASE_URL = __ENV.BASE_URL;
const COURSEID = __ENV.COURSEID;
const ADMIN_USER = __ENV.ADMIN_USER;
const ADMIN_PASSWORD = __ENV.ADMIN_PASSWORD;

const overview = new Trend('adele_overview_duration', true);

export const options = {
  vus: Number(__ENV.VUS || 10),
  duration: __ENV.DURATION || '30s',
  thresholds: {
    // Deliberately loose. A first run establishes what the numbers are; a
    // threshold tightened before that would only encode a guess.
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<5000'],
  },
};

/**
 * Log in once per virtual user and keep the session for the whole iteration
 * set. Moodle protects the login form with a token bound to the session, so
 * the token has to be read from the form that the same cookie jar received.
 */
export function setup() {
  if (!BASE_URL || !COURSEID || !ADMIN_USER || !ADMIN_PASSWORD) {
    throw new Error('BASE_URL, COURSEID, ADMIN_USER and ADMIN_PASSWORD are all required.');
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

  jar.clear(`${data.baseUrl}/`);
  sleep(1);
}
