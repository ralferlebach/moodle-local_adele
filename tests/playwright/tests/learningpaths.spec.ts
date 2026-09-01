import { test, expect } from '@playwright/test';
import { env, loginAsAdmin } from '../support/env';

/**
 * Smoke coverage for the learning path overview.
 *
 * local_adele renders its interface as a Vue 3 application mounted into a
 * Mustache template, so PHPUnit can prove the data is right but never that
 * anything reaches the screen. That gap is what this suite closes: it checks
 * that the page loads, that the AMD build is actually present and mounts, and
 * that the seeded learning path arrives in the browser.
 *
 * Deliberately minimal — editing paths, drag and drop and the node dialogs
 * come later.
 */
test.describe('Learning path overview', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('the page loads and mounts the Vue application', async ({ page }) => {
    await page.goto('/local/adele/index.php');

    // The mount point is rendered by initview.mustache. Its presence proves
    // the PHP side ran; its content proves the AMD bundle was found and
    // executed. A missing amd/build is the classic cause of an empty one.
    const mount = page.locator('[id^="local-adele-app"]');
    await expect(mount).toBeVisible();
    await expect(mount).not.toBeEmpty();
  });

  test('the seeded learning path is shown', async ({ page }) => {
    await page.goto('/local/adele/index.php');

    // first(): the Vue overview renders the name in more than one place (card
    // title and its tooltip), and this test is about the name arriving in the
    // browser at all, not about how often it is repeated.
    await expect(page.getByText(env.learningPathName).first()).toBeVisible({ timeout: 20_000 });
  });

  test('the page reports no PHP or JavaScript error', async ({ page }) => {
    const consoleErrors: string[] = [];
    page.on('pageerror', (error) => consoleErrors.push(error.message));

    await page.goto('/local/adele/index.php');
    await expect(page.locator('[id^="local-adele-app"]')).toBeVisible();

    // Moodle renders exceptions into a .errorbox; an uncaught JS exception
    // never reaches the DOM at all, so both have to be checked separately.
    await expect(page.locator('.errorbox')).toHaveCount(0);
    expect(consoleErrors, `Uncaught JavaScript errors: ${consoleErrors.join(' | ')}`).toEqual([]);
  });
});
