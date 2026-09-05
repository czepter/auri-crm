import { chromium } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { ADMIN, settle, visit } from './lib.mjs'

const REPO = '/Users/christian/www/client-management-portal'
const tinker = (php) =>
  execFileSync('docker', ['compose', 'exec', '-T', '-e', 'APP_SURFACE=admin', 'php', 'php', 'artisan', 'tinker', '--execute', php], {
    cwd: REPO,
    encoding: 'utf8',
  }).trim()

const browser = await chromium.launch()

/** One clean attempt: reset the flag, open the wizard, press Weiter, read the errors. */
async function attempt(label, prepare) {
  tinker(
    "App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => null" +
      (prepare === 'emptyBank' ? ", 'bank_account_holder' => null, 'bank_iban' => null, 'bank_bic' => null" : '') +
      (prepare === 'emptyStrings' ? ", 'bank_account_holder' => '', 'bank_iban' => '', 'bank_bic' => ''" : '') +
      "]); Cache::store('redis')->flush(); echo 'ok';",
  )
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })
  const page = await ctx.newPage()
  await visit(page, `${ADMIN}/login`)
  await page.getByRole('button', { name: 'Dev-Login' }).click()
  await settle(page, 800)
  await visit(page, `${ADMIN}/einrichtung/studio`)

  const bankBefore = await page.locator('#p-kontoinhaber').inputValue()
  await page.getByRole('button', { name: 'Weiter' }).click()
  await settle(page, 1000)

  const heading = (await page.locator('h1').first().innerText()).trim()
  const errors = await page.locator('.err:visible').allInnerTexts()
  console.log(`${label.padEnd(22)} kontoinhaber="${bankBefore}" → ${heading} · ${JSON.stringify(errors)}`)
  await ctx.close()
}

await attempt('bank = null', 'emptyBank')
await attempt('bank = ""', 'emptyStrings')
await attempt('bank unverändert', null)

tinker("App\\Settings\\Models\\StudioSettings::query()->update(['onboarding_completed_at' => now()]); Cache::store('redis')->flush(); echo 'wieder zu';")
await browser.close()
