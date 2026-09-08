import { describe, expect, it } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import HelpButton from './HelpButton.vue'

function render(props: Record<string, unknown>) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(HelpButton, props)
  app.use(i18n)
  return renderToString(app)
}

describe('HelpButton', () => {
  it('zeigt nichts, wenn es für den Screen keinen Artikel gibt', async () => {
    expect(await render({ eintraege: [], mitRail: false })).not.toContain('button')
  })
})
