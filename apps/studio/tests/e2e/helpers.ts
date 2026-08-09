import { expect, type Page } from '@playwright/test'
import { authenticator } from 'otplib'

/** Matches the e2e-mfa account seeded by database/seeders/E2eSeeder.php. */
export const MFA_SECRET = 'JBSWY3DPEHPK3PXP'

export const ACCOUNTS = {
  /** Enrolled in MFA — reaches the dashboard. */
  mfa: { email: 'e2e-mfa@studio.test', password: 'correct-horse' },
  /** Not enrolled — every sign-in lands on the enrollment page. */
  basic: { email: 'e2e-basic@studio.test', password: 'correct-horse' },
} as const

/** Fills the login form and submits it. Asserts nothing about where it lands. */
export async function submitLogin(
  page: Page,
  account: { email: string; password: string },
): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('E-Mail').fill(account.email)
  await page.getByLabel('Passwort').fill(account.password)
  await page.getByRole('button', { name: 'Anmelden' }).click()
}

/**
 * Signs in all the way to the dashboard, through the MFA challenge. Every spec
 * that needs an authenticated page starts here rather than repeating the flow —
 * three copies of it is how the German retrofit would have missed one.
 */
export async function signIn(page: Page): Promise<void> {
  await submitLogin(page, ACCOUNTS.mfa)

  await expect(page).toHaveURL('/auth/mfa')
  await page.getByLabel('6-stelliger Code').fill(authenticator.generate(MFA_SECRET))
  await page.getByRole('button', { name: 'Bestätigen' }).click()

  await expect(page).toHaveURL('/')
}
