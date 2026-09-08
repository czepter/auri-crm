import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import { helpPanel } from '@/lib/helpPanel'

// HelpRail reads page.props.help via usePage() directly, not a component
// prop — same reason Record.test.ts mocks Inertia for AdminLayout.
const pageProps = vi.hoisted(() => ({ help: [] as { slug: string; titel: string; art: string }[] }))

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

  return {
    ...actual,
    usePage: () => ({ props: pageProps }),
  }
})

const { default: HelpRail } = await import('./HelpRail.vue')

async function render(help: typeof pageProps.help) {
  pageProps.help = help
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp(HelpRail)
  app.use(i18n)
  return renderToString(app)
}

beforeEach(() => {
  localStorage.clear()
  helpPanel.zuruecksetzen()
})

describe('HelpRail', () => {
  it('renders nothing when there are no help entries', async () => {
    const html = await render([])

    expect(html).not.toContain(de.help.close)
  })

  it('renders nothing once the panel is closed, even with entries', async () => {
    helpPanel.schliessen()

    const html = await render([{ slug: 'a', titel: 'A', art: 'aufgabe' }])

    expect(html).not.toContain(de.help.close)
  })

  it('renders the switcher and close control while open with entries', async () => {
    const html = await render([{ slug: 'a', titel: 'A', art: 'aufgabe' }])

    expect(html).toContain(de.help.close)
  })

  // Vue's SSR renderer produces a static HTML string and never runs event
  // listeners — there is no DOM, so a MouseEvent can't be dispatched at
  // `aufLink` this way, and this workspace has no jsdom or @vue/test-utils
  // to mount one (every other *.test.ts here renders through the same
  // SSR-string idiom). The modifier-click guard from finding 2 is exercised
  // manually instead: see task-7-report.md.
})
