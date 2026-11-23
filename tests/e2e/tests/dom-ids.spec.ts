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
 * dom-ids - every DOM id once on the page, every reference to an id on its
 * own element (local_adele #18).
 *
 *     the overview, the editor with a stored path and all its dialogs
 *     mounted, and a course page that shows three ADELE applications (the
 *     same path twice, a second path)
 *     -> no DOM id twice on the whole page (Moodle, VueFlow and ADELE)
 *     -> every for / aria-labelledby / aria-describedby / aria-controls /
 *        list / data-target / marker reference inside an application names
 *        exactly one element, and that element is in the same application
 *     the node dialog and the tutorial dialog, opened from the keyboard
 *     -> named by their own title, focus inside, every label on a field of
 *        the same dialog, Escape closes and gives the focus back
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import AxeBuilder from '@axe-core/playwright';
import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture } from '../support/fixtures';
import { VIEWPORT } from '../support/editor';

/** The first Vue application on the page. */
const app = (page: Page): Locator => page.locator('[name="local-adele-app"]').first();

/**
 * The serious and critical WCAG 2.1 A/AA violations inside an element, once
 * every animation has ended (a fade-in measured mid-way gives false colours).
 *
 * @param page The page.
 * @param selector What to check.
 */
async function axeSerious(page: Page, selector: string): Promise<string[]> {
  await page.waitForFunction(() => document.getAnimations().every((a) => a.playState !== 'running'), null, { timeout: 10_000 })
    .catch(() => undefined);
  const results = await new AxeBuilder({ page })
    .include(selector)
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  return results.violations
    .filter((v) => v.impact === 'critical' || v.impact === 'serious')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

/** The reference path with stacks and branches (seeded as exported). */
const REFERENCE_PATH = 'Verzweigt, gestapelt B1';

/**
 * The DOM ids that appear more than once on the whole page.
 *
 * @param page The page.
 */
const duplicateIds = (page: Page): Promise<string[]> => page.evaluate(() => {
  const seen = new Map<string, number>();
  document.querySelectorAll('[id]').forEach((e) => seen.set(e.id, (seen.get(e.id) || 0) + 1));
  return [...seen].filter(([, n]) => n > 1).map(([id, n]) => `${id} x${n}`);
});

/**
 * The references to ids inside the ADELE applications that do not name
 * exactly one element of the same application.
 *
 * @param page The page.
 */
const brokenReferences = (page: Page): Promise<string[]> => page.evaluate(() => {
  const problems: string[] = [];
  const attrs = ['for', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'list', 'data-target', 'marker-end', 'marker-start'];
  const roots = [...document.querySelectorAll('[name="local-adele-app"]')];
  for (const root of roots) {
    for (const attr of attrs) {
      root.querySelectorAll(`[${attr}]`).forEach((el) => {
        const value = el.getAttribute(attr) || '';
        let ids: string[];
        if (attr === 'data-target') {
          ids = value.startsWith('#') ? [value.slice(1)] : [];
        } else if (attr.startsWith('marker-')) {
          const m = value.match(/url\(['"]?#([^'")]+)['"]?\)/);
          ids = m ? [m[1]] : [];
        } else {
          ids = value.split(/\s+/).filter(Boolean);
        }
        for (const id of ids) {
          const found = document.querySelectorAll(`[id="${CSS.escape(id)}"]`);
          const where = `${el.tagName.toLowerCase()}[${attr}=${id}]`;
          if (found.length !== 1) {
            problems.push(`${where}: ${found.length} elements`);
          } else if (!root.contains(found[0])) {
            problems.push(`${where}: element of another application`);
          }
        }
      });
    }
  }
  return problems;
});

/**
 * Open a dialog from its button with the keyboard and check what a screen
 * reader and a keyboard user get; close it with Escape.
 *
 * @param page The page.
 * @param opener The button that opens the dialog.
 */
async function checkDialog(page: Page, opener: Locator): Promise<void> {
  const controls = await opener.getAttribute('aria-controls');
  expect(controls, 'the button names the dialog it opens').toBeTruthy();
  expect(await opener.getAttribute('aria-haspopup')).toBe('dialog');
  expect(await opener.getAttribute('data-target')).toBe(`#${controls}`);
  const dialog = page.locator(`[id="${controls}"]`);
  await expect(dialog).toHaveCount(1);

  await opener.focus();
  await page.keyboard.press('Enter');
  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveClass(/\bshow\b/);

  const facts = await dialog.evaluate((d) => {
    const title = document.getElementById(d.getAttribute('aria-labelledby') || '');
    const labels = [...d.querySelectorAll('label[for]')].map((l) => {
      const field = document.getElementById(l.getAttribute('for') || '');
      return { for: l.getAttribute('for'), inside: !!field && d.contains(field) };
    });
    return {
      titleInside: !!title && d.contains(title),
      title: title ? (title.textContent || '').trim() : '',
      labels,
      closeName: (d.querySelector('.close') as HTMLElement | null)?.getAttribute('aria-label') || '',
    };
  });
  expect(facts.titleInside, 'the dialog is named by its own title').toBe(true);
  expect(facts.title).not.toBe('');
  for (const label of facts.labels) {
    expect(label.inside, `label for ${label.for} names a field of this dialog`).toBe(true);
  }
  // Bootstrap moves the focus once the dialog has faded in.
  await expect.poll(() => dialog.evaluate((d) => d.contains(document.activeElement)),
    { message: 'the focus is inside the open dialog' }).toBe(true);
  expect(facts.closeName, 'the close button has a name').not.toBe('');
  expect(await axeSerious(page, `[id="${controls}"]`), 'accessibility of the open dialog').toEqual([]);

  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(opener, 'Escape gives the focus back to the button').toBeFocused();
}

test.describe('ADELE-E2E-A18 — DOM ids once per page, references on their own element (#18)', () => {
  test.use({ viewport: VIEWPORT });

  test('overview and tutorial dialog: no id twice, the dialog is named, keyboard and focus work',
    { tag: ['@story:A18'] }, async ({ page }) => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      await expect(app(page).locator('[data-testid^="learningpath-row-"]').first()).toBeVisible({ timeout: 30_000 });
      expect(await duplicateIds(page)).toEqual([]);
      expect(await brokenReferences(page)).toEqual([]);
      await checkDialog(page, app(page).locator('button[aria-controls$="-helpingSlider"]'));
    });

  // What ADELE-SR-01 (tests/a11y-screenreader, NVDA on Windows, manual
  // workflow) expects NVDA to read out must first be in the accessibility
  // tree. Checked here on every E2E run, so the manual NVDA run is not the
  // first place a lost name shows up. Whether NVDA SPEAKS it stays SR-01's job.
  test('overview: the controls ADELE-SR-01 listens for carry their names and state in the accessibility tree',
    { tag: ['@story:A18'] }, async ({ page }) => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      const row = app(page).locator('[data-testid^="learningpath-row-"]').first();
      await expect(row).toBeVisible({ timeout: 30_000 });
      const toggle = row.getByRole('button', { name: /^Visible to users: / });
      await expect(toggle, 'the visibility toggle is a named button').toHaveCount(1);
      await expect(toggle, 'and has a pressed state').toHaveAttribute('aria-pressed', /^(true|false)$/);
      for (const control of ['Edit', 'Delete', 'Duplicate']) {
        await expect(row.getByRole('button', { name: new RegExp(`^${control}: `) }),
          `the ${control} control is named`).toHaveCount(1);
      }
      await expect(app(page).getByRole('button', { name: /Add a new learning path/i }),
        'the create control is reachable and named').toHaveCount(1);
    });

  test('editor with a stored path: no id twice on the whole page, every reference resolves, the node dialog works',
    { tag: ['@story:A18'] }, async ({ page }) => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      const card = app(page).locator('[data-testid^="learningpath-row-"]')
        .filter({ has: page.locator('h5', { hasText: REFERENCE_PATH }) });
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
      await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(5, { timeout: 30_000 });

      // Every dialog of the editor is mounted now, closed.
      for (const name of ['nodeModal', 'courseModal']) {
        await expect(app(page).locator(`[id$="-${name}"][role="dialog"]`), `${name} is mounted`).toHaveCount(1);
      }
      expect(await duplicateIds(page)).toEqual([]);
      expect(await brokenReferences(page)).toEqual([]);
      // #18 AK4/DoD3: the navbar's ADELE link (on every page of a manager)
      // carries no id; "dropdownMenuButton" - set by it and, in the second
      // source, by local_catquiz (installed in CI) - is on the page no more.
      await expect(page.locator('[id="dropdownMenuButton"]')).toHaveCount(0);
      const navlink = page.locator('a[href$="/local/adele/index.php#/learningpaths"]').filter({ hasNot: page.locator('[id]') });
      await expect(navlink.first(), 'the ADELE link in the navigation is there').toBeAttached();
      expect(await navlink.first().getAttribute('id'), 'and has no id').toBeNull();
      // Behat finds these fields by id, name or label. The ids carry the
      // application's prefix now, so the names must stay as Behat knows them
      // (CI 2026-11-20: 'Field matching locator "goalsubjectplaceholder" not found').
      for (const name of ['goalnameplaceholder', 'goalsubjectplaceholder']) {
        await expect(app(page).locator(`[name="${name}"]`), `field named ${name}`).toHaveCount(1);
      }

      const opener = app(page).locator('.vue-flow__node button[aria-controls$="-nodeModal"]').first();
      await checkDialog(page, opener);
      // The node dialog's fields carry the node's data: a field of the
      // course dialog no longer answers to the same id.
      const dialog = page.locator(`[id="${await opener.getAttribute('aria-controls')}"]`);
      await opener.click();
      await expect(dialog).toBeVisible();
      const fullname = dialog.locator('input[id$="-nodeModal-fullname"]');
      await expect(fullname).toBeVisible();
      const label = (await dialog.locator(`label[for="${await fullname.getAttribute('id')}"]`).innerText()).trim();
      await expect(fullname, 'the field is named by its own label').toHaveAccessibleName(label);
      // #18 AK5: the label puts the focus on its own field.
      await dialog.locator(`label[for="${await fullname.getAttribute('id')}"]`).click();
      await expect(fullname, 'a click on the label focuses the field').toBeFocused();
      expect(await fullname.inputValue()).not.toBe('');
      // Escape reaches the dialog once Bootstrap has moved the focus into it.
      await expect.poll(() => dialog.evaluate((d) => d.contains(document.activeElement))).toBe(true);
      await page.keyboard.press('Escape');
      await expect(dialog).toBeHidden();
      expect(await duplicateIds(page), 'after the dialog was used').toEqual([]);
    });

  test('three ADELE applications on one course page: no id twice, each reference stays in its application',
    { tag: ['@story:A18'] }, async ({ page }) => {
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto(`/course/view.php?id=${fixture('ADELE_FIXTURE_M18_HOST')}`);
      const apps = page.locator('[name="local-adele-app"]');
      await expect(apps).toHaveCount(3, { timeout: 30_000 });
      for (let i = 0; i < 3; i++) {
        await expect(apps.nth(i).locator('.vue-flow__node').first()).toBeVisible({ timeout: 30_000 });
      }
      const mountIds = await apps.evaluateAll((els) => els.map((e) => e.id));
      expect(new Set(mountIds).size, 'each application has its own mount id').toBe(3);

      // Each application prefixes its ids differently.
      const prefixes = await apps.evaluateAll((els) => els.map((root) => {
        const own = [...root.querySelectorAll('[id^="adele"]')].map((e) => e.id.split('-')[0]);
        return [...new Set(own)];
      }));
      for (const p of prefixes) {
        expect(p, 'one prefix per application').toHaveLength(1);
      }
      expect(new Set(prefixes.map((p) => p[0])).size).toBe(3);

      expect(await duplicateIds(page)).toEqual([]);
      expect(await brokenReferences(page)).toEqual([]);

      await test.step('the participants list of the second application folds and unfolds its own table only', async () => {
        const toggle = apps.nth(1).locator('[data-testid="adele-userlist-toggle"]');
        const tables = [0, 1, 2].map((i) => apps.nth(i).locator('[data-testid="adele-userlist-table"]'));
        for (const table of tables) {
          await expect(table).toBeVisible();
        }
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(tables[1]).toHaveCount(0);
        await expect(tables[0], 'the first application keeps its table').toBeVisible();
        await expect(tables[2], 'the third application keeps its table').toBeVisible();
        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        const controlled = await toggle.getAttribute('aria-controls');
        await expect(apps.nth(1).locator(`[id="${controlled}"]`)).toBeVisible();
        expect(await duplicateIds(page)).toEqual([]);
        expect(await brokenReferences(page)).toEqual([]);
      });

      await test.step('#18 AK4: the same page in the Classic theme - no id twice, no "dropdownMenuButton", '
        + 'the navigation link works', async () => {
        const course = fixture('ADELE_FIXTURE_M18_HOST');
        setCourseTheme(course, 'classic');
        try {
          await page.goto(`/course/view.php?id=${course}`);
          await expect(page.locator('body')).toHaveClass(/theme-classic|pagelayout-course/);
          expect(await page.evaluate(() => document.querySelector('link[href*="/theme/styles.php/classic/"]') !== null),
            'Classic is the theme of the page').toBe(true);
          await expect(apps).toHaveCount(3, { timeout: 30_000 });
          await expect(apps.nth(2).locator('.vue-flow__node').first()).toBeVisible({ timeout: 30_000 });
          expect(await duplicateIds(page)).toEqual([]);
          expect(await brokenReferences(page)).toEqual([]);
          await expect(page.locator('[id="dropdownMenuButton"]')).toHaveCount(0);
          const navlink = page.locator('a[href$="/local/adele/index.php#/learningpaths"]').first();
          await expect(navlink, 'the ADELE link in the navigation').toBeVisible();
          expect(await navlink.getAttribute('id')).toBeNull();
          await navlink.click();
          await expect(page).toHaveURL(/\/local\/adele\/index\.php/);
          await expect(app(page).locator('[data-testid^="learningpath-row-"]').first()).toBeVisible({ timeout: 30_000 });
        } finally {
          setCourseTheme(course, '');
        }
      });
    });
});

/**
 * Give a course a theme of its own (allowing course themes), or take it away.
 *
 * @param courseid The course.
 * @param theme The theme, '' for the site's.
 */
function setCourseTheme(courseid: string, theme: string): void {
  execFileSync('php', ['-r', `
    define('CLI_SCRIPT', true);
    require('config.php');
    set_config('allowcoursethemes', $argv[2] === '' ? 0 : 1);
    $DB->set_field('course', 'theme', $argv[2], ['id' => (int) $argv[1]]);
    rebuild_course_cache((int) $argv[1], true);
    theme_reset_all_caches();`, '--', courseid, theme], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
}
