import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Show from './Show.vue'
import meta, { Leer, Voll } from './Show.stories'
import {
    conflicts,
    lead,
    leadHeader,
    leadPeople,
    leadShoots,
    leadTasks,
    leadThread,
    packages,
    presets,
    queue,
    sources,
    stages,
    types,
    week,
} from '../__stories__/fixtures'

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

// LeadQueue reads its filter from the URL on setup, Inertia's Link resolves
// hrefs against it.
vi.stubGlobal('window', { location: new URL('http://localhost/') })

async function render(over: object = {}) {
    const app = createSSRApp(Show, { ...(meta.args as object), ...over } as never)
    app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

    return renderToString(app)
}

describe('Anfrageübersicht', () => {
    it('names the next phase in the head and keeps the quote in its card', async () => {
        const html = await render({ lead: { ...lead, stage: 'new' } })

        expect(html).toMatch(/Weiter: Kontaktiert/)
        expect(html).toContain('/angebote/q-brandt')
        for (const title of ['Anfrage', 'Aufgaben', 'Notizen', 'Verlauf', 'Termine', 'Kontakte']) {
            expect(html).toContain(`>${title}<`)
        }
    })

    it('caps long lists and links to the full views', async () => {
        const html = await render(Voll.args)

        expect(html).toContain('Alle 6 ansehen')
        expect(html).toContain('Alle 5 ansehen')
        expect(html).toContain('Alle 4 ansehen')
        expect(html).toContain('href="/auftraege/p-brandt/notizen"')
    })

    it('lets notes be edited, deleted and dated', async () => {
        const html = await render(Voll.args)

        expect(html).toContain('>bearbeiten<')
        expect(html).toContain('>löschen<')
        expect(html).toContain('>Erweitert<')
    })

    it('swaps empty cards for the tile row and asks for the inquiry text', async () => {
        const html = await render(Leer.args)

        expect(html).not.toContain('Nächster Schritt')
        expect(html).toContain('Notiz</a>')
        expect(html).toContain('placeholder="Anfragetext"')
    })
})
