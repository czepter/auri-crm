import { describe, expect, it, vi } from 'vitest'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Show from './Show.vue'
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

async function headAction(stage: string) {
    const app = createSSRApp(Show, {
        tab: 'overview',
        header: leadHeader,
        lead: { ...lead, stage },
        queue,
        people: leadPeople,
        shoots: leadShoots,
        quote: null,
        supersededQuotes: [],
        packages,
        tasks: leadTasks,
        thread: leadThread,
        types,
        sources,
        presets,
        week,
        conflicts,
        stages,
        conversation: null,
        workspace: null,
    })
    app.config.globalProperties.$page = { props: { brand: 'Atelier Nordlicht' } } as typeof app.config.globalProperties.$page
    app.use(createI18n({ legacy: false, locale: 'de', messages: { de } }))

    return renderToString(app)
}

describe('Anfrage head action', () => {
    it('moves a fresh inquiry to the next stage before offering a quote', async () => {
        const html = await headAction('new')

        expect(html).toMatch(/<button[^>]*>(<!--[^>]*-->)*Kontaktiert(<!--[^>]*-->)*<\/button>/)
        expect(html).not.toContain('Angebot schreiben')
    })

    it('offers the quote once the stage before the quote stage is reached', async () => {
        expect(await headAction('contacted')).toContain('Angebot schreiben')
    })
})
