import { expect, test, type Page } from '@playwright/test'
import { signIn } from './helpers'

async function arrive(page: Page): Promise<void> {
  await page.goto('/login')
  const dev = page.getByRole('button', { name: 'Dev-Login' })
  const present = await dev.waitFor({ state: 'visible', timeout: 5000 }).then(() => true).catch(() => false)
  if (present) { await dev.click(); await expect(page).toHaveURL('/'); return }
  await signIn(page)
}

const PROPS = ['display','position','width','height','minHeight','flex','gridTemplateColumns','gap','rowGap','columnGap',
 'margin','padding','border','borderTop','borderRadius','background','backgroundColor','boxShadow','color','opacity',
 'font','fontSize','fontWeight','lineHeight','letterSpacing','textTransform','textAlign','textDecorationLine',
 'fontVariantNumeric','fontStyle','listStyleType','overflow','isolation','verticalAlign','alignItems','justifyContent','whiteSpace','cursor']

test('probe', async ({ page }) => {
  await arrive(page)
  await page.goto(process.env.PROBE_URL || '/auswertungen')
  await page.locator('h1').waitFor()
  const out = await page.evaluate((props) => {
    const seen = new Map<string, any>()
    for (const el of Array.from(document.querySelectorAll('[class]'))) {
      const key = (el as HTMLElement).className + '|' + el.tagName
      if (seen.has(key)) continue
      const c = getComputedStyle(el as Element)
      const o: Record<string, string> = {}
      for (const p of props) o[p] = (c as any)[p]
      seen.set(key, o)
    }
    // Pseudo separat
    const bar = document.querySelector('.bar')
    const before = bar ? Object.fromEntries(props.map(p => [p, (getComputedStyle(bar, '::before') as any)[p]])) : null
    return { els: Object.fromEntries(seen), before }
  }, PROPS)
  console.log('###' + JSON.stringify(out))
})
