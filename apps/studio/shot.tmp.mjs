import { chromium } from '@playwright/test'
const b = await chromium.launch()
const p = await (await b.newContext()).newPage()
await p.setViewportSize({ width: 390, height: 844 })
await p.goto(process.env.LINK, { waitUntil: 'networkidle' })
await p.goto('http://127.0.0.1:8001/nachrichten', { waitUntil: 'networkidle' })
await p.waitForTimeout(800)
await p.screenshot({ path: '/tmp/mb-m390.png', fullPage: true })
console.log(JSON.stringify(await p.evaluate(() => ({
  docScroll: document.documentElement.scrollHeight - innerHeight,
  scrollers: [...document.querySelectorAll('.overflow-y-auto')].map(e => ({ h: e.clientHeight, sh: e.scrollHeight })),
}))))
await b.close()
