import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('the project screen shows tiles and an activity feed', async ({ page }) => {
  await signIn(page)

  // No project index route exists; the lead board (E2eSeeder's project still
  // has an open lead) is the way in, same as LeadCard.vue's own comment says.
  await page.goto('/anfragen')
  await page.getByRole('link', { name: /Hochzeit/ }).first().click()

  await expect(page.locator('.fact').first()).toBeVisible()

  // The seeded project carries no audit history of its own, so the feed
  // starts empty. Toggling portal visibility is already on this screen and
  // untouched by Task 10 — confirming it writes a project.portal_shown/hidden
  // row via AuditLogger, which is enough real activity to prove the feed
  // renders from the prop rather than being empty by construction.
  await page.getByRole('button', { name: /Portal/ }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: /Portal/ }).click()

  await expect(page.locator('.feed .ev').first()).toBeVisible()
})
