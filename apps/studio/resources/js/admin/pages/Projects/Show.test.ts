import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Show from './Show.vue'
import meta, { Leer, Voll } from './Show.stories'

vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>()

    return {
        ...actual,
        usePage: () => ({
            props: {
                auth: { user: { name: 'Team Nordlicht' }, can: [] },
                nav: {},
                brand: 'Atelier Nordlicht',
                recent: [],
            },
        }),
    }
})

vi.stubGlobal('window', { location: new URL('http://localhost/') })

async function render(args: unknown = meta.args, over: object = {}) {
    const app = createSSRApp(Show, { ...(args as object), ...over } as never)
    app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

    return renderToString(app)
}

describe('Projektübersicht', () => {
    it('leads with the overdue task and shows the shoot status one click away', async () => {
        const html = await render()

        for (const title of ['Nächster Schritt', 'Termine', 'Aufgaben', 'Zahlungen', 'Kunde', 'Angebot']) {
            expect(html).toContain(title)
        }
        expect(html).toMatch(/<button[^>]*>(<!--[^>]*-->)*Fotografiert(<!--[^>]*-->)*<\/button>/)
    })

    it('carries the portal switch in the head', async () => {
        expect(await render()).toContain('id="portal-visible"')
    })

    it('caps long lists and names the rest', async () => {
        const html = await render(meta.args as never, Voll.args)

        expect(html).toContain('Alle 14 ansehen')
        expect(html).toContain('Alle anzeigen')
        expect(html.match(/Termin \d+ mit/g)).toHaveLength(4)
    })

    it('edits contact roles and notes on the overview', async () => {
        const html = await render()

        expect(html).toContain('Bearbeiten')
        expect(html).toContain('>Erweitert<')
    })

    it('swaps empty cards for the tile row and asks for the inquiry text', async () => {
        const html = await render(meta.args as never, Leer.args)

        expect(html).not.toContain('Nächster Schritt')
        expect(html).toContain('Fragebogen</a>')
        expect(html).toContain('placeholder="Anfragetext"')
    })
})
