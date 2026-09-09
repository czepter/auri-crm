import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import { helpPanel } from '@/lib/helpPanel'

// HelpButton reads page.props.help via usePage() directly, not a component
// prop — same reason HelpRail.test.ts mocks Inertia.
const pageProps = vi.hoisted(() => ({ help: [] as { slug: string; titel: string; art: string }[] }))

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

  return {
    ...actual,
    usePage: () => ({ props: pageProps }),
  }
})

const { default: HelpButton } = await import('./HelpButton.vue')

async function render(help: typeof pageProps.help) {
  pageProps.help = help
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(HelpButton)
  app.use(i18n)
  return renderToString(app)
}

beforeEach(() => {
  helpPanel.zuruecksetzen()
})

describe('HelpButton', () => {
  it('zeigt nichts, wenn es für den Screen keinen Artikel gibt', async () => {
    helpPanel.dockt.value = true
    expect(await render([])).not.toContain(de.help.open)
  })

  it('öffnet die Spalte per Button, wenn sie andocken würde', async () => {
    helpPanel.dockt.value = true

    const html = await render([{ slug: 'a', titel: 'A', art: 'aufgabe' }])

    expect(html).toContain('<button')
    expect(html).not.toContain('<a ')
  })

  it('verlinkt auf den Artikel, wenn sie unterhalb der Schwelle nicht andocken würde', async () => {
    helpPanel.dockt.value = false

    const html = await render([{ slug: 'lead-anlegen', titel: 'A', art: 'aufgabe' }])

    expect(html).toContain('href="/hilfe/lead-anlegen"')
    expect(html).not.toContain('<button')
  })

  it.each([true, false])('bleibt sichtbar, wenn das Panel offen=%s ist', async (offenValue) => {
    if (offenValue) helpPanel.oeffnen()
    else helpPanel.schliessen()
    helpPanel.dockt.value = true

    const html = await render([{ slug: 'a', titel: 'A', art: 'aufgabe' }])

    expect(html).toContain(de.help.open)
  })
})
