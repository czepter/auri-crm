import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('the project screen shows its cards and an activity trace', async ({ page }) => {
  await signIn(page)

  // C-02 (#236) gave Projects/* the list it never had, so the way in is the
  // list rather than the board — the board leads to the inquiry now (C-08).
  // The booking, not the seeder's inquiry: CaseController::index lists
  // booked projects only, and an inquiry belongs on the board at /anfragen.
  await page.goto('/projekte')
  await page.getByRole('link', { name: /E2E Buchung/ }).first().click()

  await expect(page.getByRole('heading', { name: 'Termine' })).toBeVisible()

  // The seeded project carries no audit history of its own, so the trace
  // starts empty. Toggling the portal switch in the head writes a
  // project.portal_shown/hidden row through AuditLogger, which is enough real
  // activity to prove the trace renders from the prop rather than
  // being empty by construction.
  await page.getByRole('switch', { name: 'Portal' }).click()

  await page.getByRole('link', { name: 'Info', exact: true }).click()

  await expect(page.getByText(/Portal (freigegeben|verborgen)/)).toBeVisible()
})
