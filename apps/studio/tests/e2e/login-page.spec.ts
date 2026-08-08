import { expect, test } from '@playwright/test'

test('the login page renders its fields', async ({ page }) => {
  await page.goto('/login')

  await expect(page.getByLabel('Email')).toBeVisible()
  await expect(page.getByLabel('Password')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Sign in' })).toBeVisible()
})
