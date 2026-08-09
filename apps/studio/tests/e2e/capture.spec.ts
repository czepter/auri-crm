import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('an inquiry typed into one form lands on the board', async ({ page }) => {
  await signIn(page)

  await page.goto('/anfragen/neu')

  // Search first, then type. The name fields do not exist until the studio has
  // seen the existing matches and chosen to create a new contact — that
  // ordering is the anti-duplicate mechanism, so the journey exercises it
  // rather than routing around it.
  await page.getByRole('searchbox').fill('Annika Steffens')
  await page.getByRole('button', { name: /als neuen Kontakt anlegen/ }).click()

  await page.getByLabel('Partner/in').fill('Ruben Falk')
  await page.getByLabel('Telefon').fill('0171 3948820')
  await page.getByLabel('Haupttermin').fill('2026-06-14')

  await page.getByRole('button', { name: 'Anfrage anlegen' }).click()

  await expect(page).toHaveURL('/anfragen')
  await expect(page.getByRole('heading', { name: 'Annika Steffens & Ruben Falk' })).toBeVisible()
})

test('a taken date warns without blocking the save', async ({ page }) => {
  await signIn(page)

  await page.goto('/anfragen/neu')

  await page.getByRole('searchbox').fill('Testpaar')
  await page.getByRole('button', { name: /als neuen Kontakt anlegen/ }).click()

  // Seeded by DemoCrmSeeder: Gut Basthorst is already taken on this date.
  await page.getByLabel('Haupttermin').fill('2026-06-14')

  // Names the seeded project, so the assertion says which collision it means.
  await expect(page.getByText(/^Belegt — Meier & Kowalski/)).toBeVisible()

  // The point of the screen: the warning does not stop the save.
  await page.getByRole('button', { name: 'Anfrage anlegen' }).click()
  await expect(page).toHaveURL('/anfragen')
  await expect(page.getByRole('heading', { name: 'Testpaar' })).toBeVisible()
})
