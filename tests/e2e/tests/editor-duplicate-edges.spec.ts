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
 * editor-duplicate-edges - one edge per connection (local_adele #603).
 *
 *     a manager connects two nodes that are connected already
 *     -> the graph keeps one edge, the saved path one connection
 *     a stored path that holds connections twice (the reference path
 *     "Verzweigt, gestapelt B1", seeded as exported)
 *     -> the editor draws each connection once, no DOM id twice
 *
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { execFileSync } from 'node:child_process';
import { test, expect, Page, Locator } from '@playwright/test';
import { env, loginAs } from '../support/env';
import { fixture, reseedFixtures, runLabel, saveLearningPath, waitForEditorLoaded } from '../support/fixtures';
import { VIEWPORT, dropCourseAt, dropFirstCourse } from '../support/editor';

/** The Vue application's mount point. */
const app = (page: Page): Locator => page.locator('[id^="local-adele-app"]');

/** The overview card of a path, found by its title. */
const cardTitled = (page: Page, title: string): Locator =>
  app(page).locator('[data-testid^="learningpath-row-"]')
    .filter({ has: page.locator('h5', { hasText: title }) });

/**
 * The edges of a stored path, as [id, source, sourceHandle, target, targetHandle, type].
 *
 * @param title The path.
 */
function storedEdges(title: string): string[][] {
  const script = `
    define('CLI_SCRIPT', true);
    require('config.php');
    $json = $DB->get_field('local_adele_learning_paths', 'json', ['name' => $argv[1]], MUST_EXIST);
    echo json_encode(array_map(fn($e) => [$e['id'], $e['source'], $e['sourceHandle'] ?? '', $e['target'],
        $e['targetHandle'] ?? '', $e['type'] ?? 'default'], json_decode($json, true)['tree']['edges']));`;
  const out = execFileSync('php', ['-r', script, '--', title], { cwd: fixture('ADELE_MOODLE_ROOT'), encoding: 'utf8' });
  return JSON.parse(out.trim().split('\n').pop() || '[]');
}

/**
 * The DOM ids that appear more than once in the graph - nodes, edges and
 * everything inside the canvas. (Outside it the editor's dialogs repeat ids
 * of their own - a finding apart from #603: issue draft
 * local_adele-issue-duplicate-modal-ids.md.)
 *
 * @param page The page.
 */
const duplicateIds = (page: Page) => page.evaluate(() => {
  const seen = new Map<string, number>();
  document.querySelectorAll('.vue-flow [id]').forEach((e) => seen.set(e.id, (seen.get(e.id) || 0) + 1));
  return [...seen].filter(([, n]) => n > 1).map(([id]) => id);
});

/**
 * The ids the graph's edges carry on their path elements.
 *
 * @param page The page.
 */
const edgePathIds = (page: Page) => page.locator('.vue-flow__edges [id]').evaluateAll((els) => els.map((e) => e.id));

/**
 * Drag a connection from one node's source handle to another node's target
 * handle, as a person does with the mouse.
 *
 * @param page The page.
 * @param from The node the connection starts at.
 * @param to The node it ends at.
 * @param reverse Drag from the target handle to the source handle.
 */
async function connect(page: Page, from: string, to: string, reverse = false): Promise<void> {
  const source = page.locator(`.vue-flow__node[data-id="${from}"] .vue-flow__handle[data-handleid="source"]`);
  const target = page.locator(`.vue-flow__node[data-id="${to}"] .vue-flow__handle[data-handleid="target"]`);
  // Reverse: the drag starts at the target handle and ends at the source
  // handle - the same connection, drawn the other way round. Without the
  // check in handleConnect() this added a second edge (measured 2026-10-18).
  const a = await (reverse ? target : source).boundingBox();
  const b = await (reverse ? source : target).boundingBox();
  expect(a && b, 'both handles are on the canvas').toBeTruthy();
  await page.mouse.move(a!.x + a!.width / 2, a!.y + a!.height / 2);
  await page.mouse.down();
  await page.mouse.move(b!.x + b!.width / 2, b!.y + b!.height / 2, { steps: 15 });
  await page.mouse.up();
  await page.waitForTimeout(500);
}

test.beforeAll(() => reseedFixtures());

test.describe('ADELE-E2E-E1 — one edge per connection (#603)', () => {
  test.use({ viewport: VIEWPORT });

  test('connecting two connected nodes again adds no edge; saved, the path holds the connection once',
    { tag: ['@story:E1'] }, async ({ page }) => {
      const title = runLabel('E603 Verbindung');
      page.on('dialog', (dialog) => dialog.accept());
      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      await app(page).locator('[data-testid="learningpath-create"]').click();
      await waitForEditorLoaded(page);
      await app(page).locator('[data-testid="learningpath-title"]').fill(title);
      await app(page).locator('[data-testid="learningpath-description"]').fill('E2E #603');
      await dropFirstCourse(page, 'T01');
      await dropCourseAt(page, 'T02', 'dndnode_1', 'child');
      const edges = page.locator('.vue-flow__edge');
      await expect(edges).toHaveCount(1);

      for (let i = 0; i < 2; i++) {
        await connect(page, 'dndnode_1', 'dndnode_2');
      }
      await expect(edges, 'still one edge in the graph').toHaveCount(1);
      expect(await duplicateIds(page)).toEqual([]);
      expect(await edgePathIds(page)).toEqual(['dndnode_2dndnode_1']);

      await saveLearningPath(page, app(page).locator('[data-testid="learningpath-save"]'));
      await expect(app(page).locator('[data-testid="learningpath-create"]')).toBeVisible({ timeout: 30_000 });
      const stored = storedEdges(title);
      expect(stored.map((e) => e[0]), 'one connection stored').toEqual(['dndnode_2dndnode_1']);

      await test.step('the manager removes the path again', async () => {
        const card = cardTitled(page, title);
        await card.getByRole('button', { name: /^(Delete|Löschen):/ }).click();
        await card.locator('.deletealert .btn-danger').click();
        await expect(card).toHaveCount(0, { timeout: 30_000 });
      });
    });

  test('a stored path holding connections twice is drawn with each connection once, no DOM id twice',
    { tag: ['@story:E1'] }, async ({ page }) => {
      const name = 'Verzweigt, gestapelt B1';
      const raw = storedEdges(name);
      expect(raw, 'precondition: the path is stored as exported, with copies').toHaveLength(7);
      const connections = new Set(raw.map((e) => JSON.stringify(e.slice(1))));
      expect(connections.size).toBe(5);

      await loginAs(page, env.adminUser, env.adminPassword);
      await page.goto('/local/adele/index.php');
      const card = cardTitled(page, name);
      await expect(card).toHaveCount(1, { timeout: 30_000 });
      await card.getByRole('button', { name: /^(Edit|Bearbeiten):/ }).click();
      await expect(app(page).locator('[data-testid^="learningpath-node-"]')).toHaveCount(5, { timeout: 30_000 });
      const ids = await page.locator('.vue-flow__edge').evaluateAll((els) => els.map((e) => e.getAttribute('data-id')));
      expect(ids, 'each connection drawn once').toHaveLength(5);
      expect(new Set(ids).size).toBe(5);
      expect(await duplicateIds(page)).toEqual([]);
      const pathids = await edgePathIds(page);
      expect(pathids, 'one path element per connection').toHaveLength(5);
      expect(new Set(pathids).size).toBe(5);
      expect(storedEdges(name), 'looking changes nothing stored').toHaveLength(7);
    });
});
