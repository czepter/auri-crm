import { expect, test } from '@playwright/test'

test('a staff user without MFA signs in and reaches the dashboard', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('correct-horse')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page).toHaveURL('/')
  await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible()
})

test('a wrong password shows an error and stays on the login page', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Email').fill('e2e-basic@studio.test')
  await page.getByLabel('Password').fill('definitely-wrong')
  await page.getByRole('button', { name: 'Sign in' }).click()

  await expect(page.getByText('These credentials do not match our records.')).toBeVisible()
  await expect(page).toHaveURL('/login')
})
