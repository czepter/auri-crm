import { expect, test, type Page } from '@playwright/test'
import { NOW, signIn } from './helpers'

// The PORTAL_URL the admin redirects a preview to. On 127.0.0.1 the portal
// would overwrite the admin's XSRF-TOKEN cookie: cookies ignore the port.
const PORTAL = 'http://localhost:8001'

test.use({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })

test.beforeEach(async ({ page }) => {
  await page.clock.setFixedTime(new Date(NOW))
  await signIn(page)
})

async function screen(page: Page, name: string): Promise<void> {
  await page.waitForLoadState('networkidle')
  await page.evaluate(() => document.fonts.ready)
  await expect(page).toHaveScreenshot(`${name}.png`, { fullPage: true })
}

async function booking(page: Page): Promise<void> {
  await page.goto('/projekte')
  await page.getByRole('link', { name: /E2E Buchung/ }).first().click()
  await expect(page.getByText('Haupttermin')).toBeVisible()
}

test('Anfragen-Board', async ({ page }) => {
  await page.goto('/anfragen?ansicht=board')
  await screen(page, 'board')
})

test('Auftrag', async ({ page }) => {
  await booking(page)
  await screen(page, 'auftrag')
})

test('Angebot', async ({ page }) => {
  await booking(page)
  const card = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Angebot', exact: true }) })
  await card.getByRole('link', { name: 'Ändern' }).click()
  await page.waitForURL(/\/angebote\//)
  await screen(page, 'angebot')
})

test('Portal-Startseite', async ({ page }) => {
  await page.goto('/kontakte')
  await page.getByRole('link', { name: /E2E Kundin/ }).first().click()
  await page.getByRole('button', { name: 'Portal', exact: true }).click()
  const [portal] = await Promise.all([
    page.context().waitForEvent('page'),
    page.getByRole('button', { name: 'Vorschau als Kunde' }).click(),
  ])
  await portal.waitForURL(`${PORTAL}/`)
  await screen(portal, 'portal-startseite')
})
