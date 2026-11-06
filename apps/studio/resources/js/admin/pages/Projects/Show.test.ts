import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Show from './Show.vue'
import meta from './Show.stories'

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

async function render(over: Record<string, unknown> = {}) {
    const app = createSSRApp(Show, { ...meta.args, ...over } as never)
    app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

    return renderToString(app)
}

describe('Projektübersicht', () => {
    it('holds the cards of the inquiry, with the shoot status one click away', async () => {
        const shoots = meta.args!.shoots!.map((shoot, i) => (i ? shoot : { ...shoot, dueStatus: { value: 'shot', label: 'Fotografiert' } }))
        const html = await render({ shoots })

        for (const title of ['Anfrage', 'Termine', 'Angebot', 'Aufgaben']) {
            expect(html).toContain(`>${title}<`)
        }
        expect(html).toMatch(/<button[^>]*>(<!--[^>]*-->)*Fotografiert(<!--[^>]*-->)*<\/button>/)
    })

    it('calls the primary contact from the head when a payment is late', async () => {
        const header = meta.args!.header!
        const plan = { installments: [{ id: 'i1', amount: 100, dueOn: '2026-10-01', isLate: true }] }
        const html = await render({ header: { ...header, paymentPlan: plan } })

        expect(html).toMatch(/<a [^>]*href="tel:\d+"[^>]*>(<!--[^>]*-->)*Anrufen/)
    })

    it('lists the contacts in the rail with tel and mailto links', async () => {
        const html = await render()

        expect(html).toContain('href="tel:0176 2841 9930"')
        expect(html).toContain('href="mailto:jana.rahn@posteo.de"')
    })

    it('draws no rail when nothing is left to show', async () => {
        const header = meta.args!.header!
        const html = await render({ header: { ...header, people: [], paymentPlan: null, galleries: [], history: [] } })

        expect(html).not.toContain('<aside')
    })

    it('carries the portal switch in the head', async () => {
        expect(await render()).toContain('id="portal-visible"')
    })
})
