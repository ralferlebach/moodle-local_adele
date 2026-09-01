import { test, expect, Page } from '@playwright/test';
import { env, loginAs } from '../support/env';

/**
 * ADELE-PW-472-A/B — an Adele assistant sees every VISIBLE learning path,
 * and no invisible one, whether or not they are also listed as an editor.
 *
 * The two variants exist because the regression was specifically about the
 * editor relation leaking into the visibility filter. A single test without
 * an editor assignment would pass even if that leak came back, so testing
 * only one of them is worth very little.
 *
 * In both variants the assistant is NOT the owner of either path — the seed
 * creates them under the manager. An assistant who owns a path sees it for a
 * different reason, which would make the assertion say nothing about
 * visibility.
 */

/**
 * Learning path titles rendered in the overview, scoped to the application.
 *
 * Scoped to the Vue mount point rather than the page: the title of a path
 * also appears in filter controls and dialogs, and an unscoped text match
 * counts those too.
 *
 * @param page The page showing the overview.
 * @param title The exact title to look for.
 * @returns A locator for that title inside the overview.
 */
function pathInOverview(page: Page, title: string) {
  return page.locator('[id^="local-adele-app"]').getByText(title, { exact: true });
}

/**
 * Open the overview and wait for the application to have rendered.
 *
 * Waits for a business-level condition — the visible path being on screen —
 * rather than for a fixed time. Without it, an assertion that something is
 * ABSENT would pass while the application was merely still loading, which is
 * the classic way an absence test becomes worthless.
 *
 * @param page The page to use.
 */
async function openOverview(page: Page): Promise<void> {
  await page.goto('/local/adele/index.php');
  await expect(page.locator('[id^="local-adele-app"]')).toBeVisible();
  await expect(pathInOverview(page, env.visiblePathTitle).first()).toBeVisible({ timeout: 20_000 });
}

test.describe('ADELE-PW-472 — visible learning paths for an assistant', () => {
  test('A: without an editor assignment', async ({ page }) => {
    await loginAs(page, env.assistantUsername, env.fixturePassword);
    await openOverview(page);

    await expect(pathInOverview(page, env.visiblePathTitle)).toHaveCount(1);
    await expect(pathInOverview(page, env.invisiblePathTitle)).toHaveCount(0);
  });

  test('B: with an editor assignment on both paths', async ({ page }) => {
    // A second pair of paths, identical to variant A's except that the seed
    // registered the assistant as an editor of both through
    // learning_path_editors::create_editors(). Separate fixtures, not the same
    // pair mutated in between: variant A has to be provably free of an editor
    // assignment, and sharing them would make the two tests order-dependent.
    await loginAs(page, env.assistantUsername, env.fixturePassword);
    await openOverview(page);

    await expect(pathInOverview(page, env.visiblePathBTitle)).toHaveCount(1);
    await expect(pathInOverview(page, env.invisiblePathBTitle)).toHaveCount(0);
  });
});
