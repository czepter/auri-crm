import { describe, expect, it, vi } from 'vitest'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import AdminLayout from './AdminLayout.vue'
import { usePageCommands } from '@/admin/lib/paletteCommands'

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

  return {
    ...actual,
    usePage: () => ({ props: { auth: { user: { name: 'Team Nordlicht' }, can: [] }, nav: {}, brand: 'Atelier Nordlicht', recent: [] } }),
  }
})

vi.stubGlobal('window', { location: new URL('http://localhost/') })

describe('page commands', () => {
  it('reach the palette from a page that renders AdminLayout itself', async () => {
    const Page = {
      setup() {
        usePageCommands(() => [{ id: 'probe', title: 'Probe-Befehl', href: '/probe' }])

        return () => h(AdminLayout, { active: 'projekte' }, () => h('p', 'Inhalt'))
      },
    }
    const app = createSSRApp(Page)
    app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

    expect(await renderToString(app)).toContain('Probe-Befehl')
  })
})
