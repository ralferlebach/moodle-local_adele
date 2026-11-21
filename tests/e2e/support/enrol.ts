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
 * Moodle's own "Enrol users" dialog on the participants page, used where a
 * story is about that very action.
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { expect, Page } from '@playwright/test';

/**
 * Enrol somebody through the dialog.
 *
 * The picker is a Moodle autocomplete. A click on its suggestion can be lost
 * while the autocomplete is still being set up or redraws its list (CI
 * 2026-11-21: the form was sent without a user - "0 enrolled users" - and the
 * test only noticed later). So the choice is confirmed in the selection area
 * and made again - by keyboard - until it holds, and the enrolment itself is
 * confirmed by Moodle's answer: exactly one person enrolled.
 *
 * @param page The page, logged in as administrator, on the participants page.
 * @param name The person's full name.
 * @param role The role's name in the dialog, e.g. "Teacher".
 */
export async function enrolInDialog(page: Page, name: string, role: string): Promise<void> {
  await page.getByRole('button', { name: /Enrol users|Nutzer.*einschreiben/i }).first().click();
  const dialog = page.locator('.modal.show').filter({ has: page.locator('form') }).last();
  await expect(dialog).toBeVisible({ timeout: 15_000 });
  const picker = dialog.getByRole('combobox', { name: /Select users|Nutzer.*auswählen/i }).first();
  // The selection area exists once the autocomplete is set up.
  const selection = dialog.locator('.form-autocomplete-selection').first();
  await expect(selection).toBeAttached({ timeout: 15_000 });
  const pattern = new RegExp(name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
  let attempt = 0;
  await expect(async () => {
    if (!pattern.test(await selection.innerText())) {
      attempt++;
      await picker.fill('');
      await picker.fill(name);
      const option = dialog.getByRole('option', { name: pattern }).first();
      await option.waitFor({ state: 'visible', timeout: 10_000 });
      if (attempt === 1) {
        await option.click();
      } else {
        // The way a keyboard user chooses: the first suggestion, then Enter.
        await picker.press('ArrowDown');
        await picker.press('Enter');
      }
    }
    await expect(selection).toContainText(pattern, { timeout: 3_000 });
  }, `${name} chosen in the enrolment dialog`).toPass({ timeout: 60_000 });
  await dialog.getByRole('combobox', { name: /Assign role|Rolle zuweisen/i }).selectOption({ label: role });
  const answer = page.waitForResponse((r) => r.url().includes('/enrol/manual/ajax.php') && r.url().includes('action=enrol'));
  await dialog.getByRole('button', { name: /^Enrol users|Nutzer einschreiben$/i }).click();
  const body = await (await answer).json();
  expect(body, `Moodle enrols ${name} as ${role}`).toMatchObject({ success: true, count: 1 });
  await page.waitForLoadState('networkidle');
}
