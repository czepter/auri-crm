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
  it('zeigt sich nicht ohne Artikel für den Screen', async () => {
    const html = await render([])

    expect(html).not.toContain(de.help.open)
  })

  it('zeigt sich mit Artikel, zu wie offen', async () => {
    const eintrag = [{ slug: 'a', titel: 'A', art: 'aufgabe' }]

    expect(await render(eintrag)).toContain(de.help.open)

    helpPanel.oeffnen()

    expect(await render(eintrag)).toContain('aria-expanded="true"')
  })
})
