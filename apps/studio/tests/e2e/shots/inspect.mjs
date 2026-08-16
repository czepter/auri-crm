import { chromium } from '@playwright/test'
import { ADMIN, settle, visit } from './lib.mjs'

const browser = await chromium.launch()
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE' })).newPage()
await visit(page, `${ADMIN}/login`)
await page.getByRole('button', { name: 'Dev-Login' }).click()
await settle(page, 900)

for (const url of process.argv.slice(2)) {
  await visit(page, ADMIN + url)
  const buttons = await page.locator('button:visible, a[href]:visible').evaluateAll((els) =>
    els.map((e) => `${e.tagName.toLowerCase()}[${e.getAttribute('href') ?? ''}] ${e.innerText.replace(/\s+/g, ' ').trim()}`),
  )
  const fields = await page.locator('input:visible, textarea:visible, select:visible').evaluateAll((els) =>
    els.map((e) => `${e.tagName.toLowerCase()}#${e.id} type=${e.getAttribute('type')} name=${e.getAttribute('name')} ph=${e.getAttribute('placeholder')}`),
  )
  console.log(`\n=== ${url} ===`)
  console.log('BUTTONS:', JSON.stringify(buttons, null, 0))
  console.log('FIELDS:', JSON.stringify(fields, null, 0))
}
await browser.close()
