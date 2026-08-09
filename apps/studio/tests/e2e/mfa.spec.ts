import { expect, test } from '@playwright/test'
import { ACCOUNTS, signIn, submitLogin } from './helpers'

test('a staff user with MFA is challenged before reaching the dashboard', async ({ page }) => {
  await signIn(page)

  await expect(page.getByRole('heading', { name: 'Übersicht' })).toBeVisible()
})

test('a staff user without MFA is forced through enrollment', async ({ page }) => {
  await submitLogin(page, ACCOUNTS.basic)

  await expect(page).toHaveURL('/auth/mfa/enroll')
  await expect(
    page.getByRole('heading', { name: 'Zwei-Faktor-Authentisierung einrichten' }),
  ).toBeVisible()
})
