import { expect, type Page } from '@playwright/test'
import { authenticator } from 'otplib'

/** The server's clock for the whole suite, and the browser's in visual.spec.ts. */
export const NOW = '2026-09-15T10:00:00Z'

/** Matches the e2e-mfa account seeded by database/seeders/E2eSeeder.php. */
export const MFA_SECRET = 'JBSWY3DPEHPK3PXP'

export const ACCOUNTS = {
  /** Enrolled in MFA — reaches the dashboard. */
  mfa: { email: 'e2e-mfa@studio.test', password: 'correct-horse' },
  /** Not enrolled — signs straight in to the dashboard; MFA is opt-in. */
  basic: { email: 'e2e-basic@studio.test', password: 'correct-horse' },
} as const

/** Fills the login form and submits it. Asserts nothing about where it lands. */
export async function submitLogin(
  page: Page,
  account: { email: string; password: string },
): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('E-Mail').fill(account.email)
  await page.getByLabel('Passwort', { exact: true }).fill(account.password)
  await page.getByRole('button', { name: 'Anmelden', exact: true }).click()
}

/**
 * Signs in all the way to the dashboard, through the MFA challenge. Every spec
 * that needs an authenticated page starts here rather than repeating the flow —
 * three copies of it is how the German retrofit would have missed one.
 */
export async function signIn(page: Page): Promise<void> {
  await submitLogin(page, ACCOUNTS.mfa)

  await expect(page).toHaveURL('/auth/mfa')

  // Sechs Felder, eines je Ziffer. Jedes trägt sein eigenes aria-label
  // ("pin input 1 of 6"), also greift die Beschriftung darüber nicht; #code
  // sitzt auf dem ersten. Der Fokus rückt je Tastendruck weiter, deshalb
  // tippen statt füllen — die letzte Ziffer schickt ab.
  await page.locator('#code').click()
  await page.keyboard.type(authenticator.generate(MFA_SECRET))

  await expect(page).toHaveURL('/')
}

/*
 * Dev-Login where it exists, the MFA round trip otherwise. Locally the suite
 * runs against a developer's own database — E2eSeeder would write two accounts
 * and a cast of fixtures into it for no gain, since a baseline only ever
 * compares a screen against itself.
 */
export async function arrive(page: Page): Promise<void> {
  await page.goto('/login')
  const dev = page.getByRole('button', { name: 'Dev-Login' })
  // waitFor, not isVisible: the button is rendered by Inertia after hydration,
  // and isVisible() answers from the DOM as it stands rather than waiting.
  const present = await dev
    .waitFor({ state: 'visible', timeout: 5000 })
    .then(() => true)
    .catch(() => false)
  if (present) {
    await dev.click()
    await expect(page).toHaveURL('/')
    return
  }
  await signIn(page)
}
