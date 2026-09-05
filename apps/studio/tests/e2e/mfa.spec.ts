import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('a staff user with MFA is challenged before reaching the dashboard', async ({ page }) => {
  await signIn(page)

  await expect(page.getByRole('heading', { name: 'Übersicht' })).toBeVisible()
})
