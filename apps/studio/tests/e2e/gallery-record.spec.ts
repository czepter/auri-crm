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

// Fix round 3: `await navigator.clipboard?.writeText(url)` awaited undefined
// outright when the Clipboard API was absent — the optional chain
// short-circuited the write, but `await` sat outside it, so execution
// reached "copied" regardless and reported success on a link that then
// existed nowhere at all. Only a real browser without the Clipboard API
// can reproduce that; removing it is what this init script does.
test('reports a failed copy, and shows the link, when the Clipboard API is unavailable', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true })
  })

  await signIn(page)

  await page.goto('/galerien')
  await page.getByRole('link', { name: /E2E Galerie/ }).first().click()
  await page.getByRole('button', { name: 'Mehr' }).click()
  // The mint POST queues behind the page's thumbnail requests on the single
  // PHP pool, and on CI those reach for an S3 that is not there — so it can
  // outlast expect's five seconds. Wait for the response, not the clock.
  const minted = page.waitForResponse((response) => response.url().endsWith('/vorschau-link'))
  await page.getByRole('menuitem', { name: 'Vorschaulink erzeugen' }).click()
  expect((await minted).status()).toBe(200)

  await expect(page.getByText('Fehlgeschlagen')).toBeVisible()
  await expect(page.locator('input[readonly]')).toHaveValue(/\/e2e-buchung\/e2e-galerie/)
})
