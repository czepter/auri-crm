import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('the project screen shows its key figures and an activity trace', async ({ page }) => {
  await signIn(page)

  // C-02 (#236) gave Projects/* the list it never had, so the way in is the
  // list rather than the board — the board leads to the inquiry now (C-08).
  // The booking, not the seeder's inquiry: ProjectController::index lists
  // booked projects only, and an inquiry belongs on the board at /anfragen.
  await page.goto('/projekte')
  await page.getByRole('link', { name: /E2E Buchung/ }).first().click()

  // C-11 (#247): key figures across, each with its proof — not a phase tile.
  await expect(page.getByText('Haupttermin')).toBeVisible()

  // The seeded project carries no audit history of its own, so the trace
  // starts empty. Portal visibility is the one key value that is a decision
  // rather than a derivation, so it is the one with a switch — and toggling
  // it writes a project.portal_shown/hidden row through AuditLogger, which is
  // enough real activity to prove the trace renders from the prop rather than
  // being empty by construction.
  await page.getByRole('button', { name: /Portal-Sichtbarkeit/ }).click()

  await expect(page.getByText(/Portal (freigegeben|verborgen)/)).toBeVisible()
})
