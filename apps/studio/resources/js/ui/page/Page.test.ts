import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createSSRApp, h, computed } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import { helpPanel } from '@/lib/helpPanel'

// environment: 'node' (see vitest.config.ts) has no window, so useMediaQuery
// (@vueuse/core) silently returns false — real browser widths can't run
// here, and stubbing window.matchMedia globally (the vitest.setup.ts
// MemoryStorage precedent) turned out not to transfer: @vue/runtime-dom does
// `document.createElement('template')` at module scope purely from
// `typeof document !== 'undefined'`, so any global `document` stub short of
// a real DOM breaks it (confirmed: 8 unrelated suites failed with
// "doc.createElement is not a function"). This mocks useMediaQuery itself
// instead — matching against simulatedWidth using the exact query text Page
// asks for, so the real 1080/1536 literals in Page.vue still drive the
// result and a swap of either the numbers or which ref backs which branch
// fails the assertions below.
const simulatedWidth = vi.hoisted(() => ({ value: 1920 }))

vi.mock('@vueuse/core', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@vueuse/core')>()

  return {
    ...actual,
    useMediaQuery: (query: string) => computed(() => {
      const min = query.match(/min-width:\s*(\d+)px/)
      return min ? simulatedWidth.value >= Number(min[1]) : false
    }),
  }
})

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

  return {
    ...actual,
    usePage: () => ({ props: { help: [] } }),
  }
})

const { default: Page } = await import('./Page.vue')

async function render(mitRail: boolean) {
  const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
  const app = createSSRApp({
    render: () => h(Page, null, mitRail ? { right: () => h('div') } : {}),
  })
  app.use(i18n)
  await renderToString(app)
}

beforeEach(() => {
  helpPanel.zuruecksetzen()
})

describe('Page hilfeDockt', () => {
  it('dockt ohne Seiten-Rail schon ab 1080px', async () => {
    simulatedWidth.value = 1200

    await render(false)

    expect(helpPanel.dockt.value).toBe(true)
  })

  it('dockt mit Seiten-Rail bei 1200px noch nicht — erst ab 1536px', async () => {
    simulatedWidth.value = 1200

    await render(true)

    expect(helpPanel.dockt.value).toBe(false)
  })

  it('dockt mit Seiten-Rail ab 1536px', async () => {
    simulatedWidth.value = 1600

    await render(true)

    expect(helpPanel.dockt.value).toBe(true)
  })
})
