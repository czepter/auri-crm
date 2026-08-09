import { expect, test } from '@playwright/test'
import { ACCOUNTS, submitLogin } from './helpers'

test('a staff user signs in and is sent to MFA enrollment', async ({ page }) => {
  await submitLogin(page, ACCOUNTS.basic)

  await expect(page).toHaveURL('/auth/mfa/enroll')
  await expect(
    page.getByRole('heading', { name: 'Zwei-Faktor-Authentisierung einrichten' }),
  ).toBeVisible()
})

test('a wrong password shows an error and stays on the login page', async ({ page }) => {
  await submitLogin(page, { ...ACCOUNTS.basic, password: 'definitely-wrong' })

  await expect(
    page.getByText('Diese Zugangsdaten stimmen nicht mit unseren Daten überein.'),
  ).toBeVisible()
  await expect(page).toHaveURL('/login')
})
