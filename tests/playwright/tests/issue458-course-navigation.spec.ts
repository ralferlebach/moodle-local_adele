import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';

/**
 * ADELE-PW-458-A/B — no learning path editor in the course navigation for
 * teaching roles.
 *
 * Normative expectation for this suite: NEITHER a teacher with editing rights
 * NOR one without may find "Lernpfade" in the primary course navigation. That
 * is asserted regardless of what the historical issue discussion proposed.
 *
 * Honest scope note: at the time of writing, local_adele adds no such entry to
 * the course navigation at all, so both variants pass trivially. They are
 * written anyway — this is the kind of entry that gets added later "because it
 * is convenient", and then nobody remembers it was ruled out.
 *
 * Deliberately NOT covered here: direct URL access to /local/adele/. That is a
 * capability question, not a navigation one, and mixing them would let a
 * passing navigation test suggest an authorisation guarantee it never made.
 */

/**
 * The entry, looked for only inside the primary course navigation.
 *
 * Scoped on purpose: the same word appears in page content, footers and
 * administration menus, and a page-wide text search would report those as
 * hits — turning a real regression into a green test, or a harmless mention
 * into a red one.
 *
 * @param page The page to search.
 * @returns A locator for "Lernpfade" entries in the primary navigation.
 */
function learningPathNavEntry(page: Page) {
  const nav = page.locator('.secondary-navigation, nav.moremenu').first();
  return nav.getByRole('link', { name: 'Lernpfade', exact: true });
}

test.describe('ADELE-PW-458 — course navigation', () => {
  test('A: a teacher with editing rights sees no "Lernpfade" entry', async ({ page }) => {
    await loginAs(page, env.collaboratorUsername, env.fixturePassword);
    await page.goto(env.navCourseUrl);

    // The course itself must open normally; asserting the absence of
    // something on a page that failed to load would prove nothing.
    await expect(page.getByRole('heading', { name: 'PW Kurs Navigation 458' })).toBeVisible();
    await expect(page.locator('.secondary-navigation, nav.moremenu').first()).toBeVisible();

    await expect(learningPathNavEntry(page)).toHaveCount(0);
  });

  test('B: a teacher without editing rights sees no "Lernpfade" entry', async ({ page }) => {
    await loginAs(page, env.t0Username, env.fixturePassword);
    await page.goto(env.navCourseUrl);

    await expect(page.getByRole('heading', { name: 'PW Kurs Navigation 458' })).toBeVisible();
    await expect(page.locator('.secondary-navigation, nav.moremenu').first()).toBeVisible();

    await expect(learningPathNavEntry(page)).toHaveCount(0);
  });
});
