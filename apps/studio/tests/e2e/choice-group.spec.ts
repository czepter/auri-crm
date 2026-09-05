import { expect, test } from '@playwright/test'
import { arrive } from './helpers'

/*
 * Three choice groups stand on this screen. A radio without a name belongs to
 * every other nameless radio in the document, so an arrow key pressed in the
 * first group lands in the second — which is why ChoiceGroup hands each set a
 * useId(). The first assertion is the one that catches its removal: drop the
 * name and every group answers to the same empty string.
 */
test('an arrow key stays inside its own choice group', async ({ page }) => {
  await arrive(page)
  await page.goto('/anfragen/neu')
  await page.locator('[data-slot="choice-group"] input[type=radio]').first().waitFor({ state: 'attached' })

  const names = await page.evaluate(() =>
    [...document.querySelectorAll<HTMLInputElement>('[data-slot="choice-group"] input[type=radio]')].map((radio) => radio.name),
  )
  expect(new Set(names).size).toBeGreaterThan(1)

  const group = await page.evaluate(() => {
    const first = document.querySelector<HTMLInputElement>('[data-slot="choice-group"] input[type=radio]')!
    first.focus()
    return first.name
  })

  await page.keyboard.press('ArrowDown')

  expect(await page.evaluate(() => (document.activeElement as HTMLInputElement).name)).toBe(group)
})
