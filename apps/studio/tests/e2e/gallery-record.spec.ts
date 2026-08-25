import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

// R36 (fix round 1, Critical 2): DropdownMenuContent's force-mount (added
// so Record.test.ts could SSR-assert the More menu's contents) reached the
// Portal's own Teleport gate too, which made reka-ui run useHideOthers() at
// mount instead of at open — with DropdownMenuRoot's modal defaulting to
// true, that aria-hid #app for the whole record screen from page load,
// menu closed or not. Only a real browser sees the DOM mutation
// useHideOthers() performs; no SSR string render can pin this.
test('the record screen stays visible to assistive tech while the More menu is closed', async ({ page }) => {
  await signIn(page)

  await page.goto('/galerien')
  await page.getByRole('link', { name: /E2E Galerie/ }).first().click()
  await expect(page.getByRole('button', { name: 'Mehr' })).toBeVisible()

  await expect(page.locator('#app')).not.toHaveAttribute('aria-hidden', 'true')
})
