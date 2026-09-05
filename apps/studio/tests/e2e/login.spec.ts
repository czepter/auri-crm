import { expect, test } from '@playwright/test'
import { ACCOUNTS, submitLogin } from './helpers'

test('a staff user without MFA signs in straight to the dashboard', async ({ page }) => {
  await submitLogin(page, ACCOUNTS.basic)

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Übersicht' })).toBeVisible()
})

test('a wrong password shows an error and stays on the login page', async ({ page }) => {
  await submitLogin(page, { ...ACCOUNTS.basic, password: 'definitely-wrong' })

  await expect(
    page.getByText('Diese Zugangsdaten stimmen nicht mit unseren Daten überein.'),
  ).toBeVisible()
  await expect(page).toHaveURL('/login')
})
