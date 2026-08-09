import { expect, test } from '@playwright/test'
import { authenticator } from 'otplib'

const MFA_SECRET = 'JBSWY3DPEHPK3PXP'

test('a staff user with MFA is challenged before reaching the dashboard', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-mfa@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/auth/mfa')

  await page.getByLabel('Authentication code').fill(authenticator.generate(MFA_SECRET))
  await page.getByRole('button', { name: 'Verify' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
})

test('a staff user without MFA is forced through enrollment', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/auth/mfa/enroll')
  await expect(page.getByRole('heading', { name: 'Set up two-factor authentication' })).toBeVisible()
})
