import { expect, test } from '@playwright/test'
import { signIn } from './helpers'

test('a template written in blocks previews with sample data and survives a reload', async ({ page }) => {
  await signIn(page)

  await page.goto('/dokumentvorlagen/neu')

  // T-16: the screen opens on a choice of starters rather than on an empty
  // canvas. This test is about the editor, so it takes the blank one — the
  // same door it used before the catalog existed. C-16 (#252) made the cards
  // links, so the blank one is the last card rather than a button.
  await page.getByRole('link', { name: /Leere Vorlage/ }).click()

  await page.getByLabel('Name').fill('Fotovertrag Hochzeit')

  // Editor.js focuses its first empty paragraph on mount.
  const canvas = page.locator('.codex-editor [contenteditable="true"]').first()
  await canvas.click()
  const placeholder = 'Platzhalter'
  await page.keyboard.type(`Vertrag mit ${placeholder}`)

  // Select the placeholder word character-by-character (a double-click risks
  // landing on the wrong word and is flaky across engines) — a non-collapsed
  // selection is what makes Editor.js raise its inline toolbar. This is the
  // real seam under test: the merge-field inline tool (mergeFieldTool.ts)
  // itself, not just typed braces that MergeFieldResolver would treat
  // identically either way.
  for (let i = 0; i < placeholder.length; i++) {
    await page.keyboard.press('Shift+ArrowLeft')
  }
  await page.locator('.ce-inline-toolbar .ce-inline-tool', { hasText: '{ }' }).click()

  const picker = page.locator('.doc-mf-picker')
  await picker.locator('input').fill('contact.name')
  await picker.locator('.doc-mf-option', { hasText: 'contact.name' }).click()

  // The chip shows the sample value while editing — this is the seam between
  // the editor and MergeFieldResolver: what serializes back out is
  // `{{ contact.name }}`, resolved server-side to the same sample.
  await expect(page.locator('.codex-editor .doc-mf')).toHaveText('Lena Meier')

  // The server preview is debounced by 400ms and rendered into a sandboxed
  // iframe, so assert against the frame rather than the page.
  const preview = page.frameLocator('iframe')
  await expect(preview.getByText('Lena Meier')).toBeVisible({ timeout: 5000 })

  // The content assertion above passes on the instant local paint alone: the
  // client fills the same sample values the server would. This is the only
  // signal that separates "painted locally" from "the server rendered it" —
  // stale flips to false solely on a successful /vorschau response.
  await expect(page.getByText('aktuell')).toBeVisible({ timeout: 10000 })

  await page.getByRole('button', { name: 'Speichern' }).click()
  await expect(page).toHaveURL('/vorlagen?typ=dokumente')

  await page.getByRole('link', { name: 'Fotovertrag Hochzeit' }).click()
  await expect(page.locator('.codex-editor')).toContainText('Vertrag mit')
  await expect(page.locator('.codex-editor .doc-mf')).toHaveText('Lena Meier')
})
