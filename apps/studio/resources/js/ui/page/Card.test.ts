import { describe, expect, it } from 'vitest'
import { createSSRApp, h, type VNode } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createI18n } from 'vue-i18n'
import de from '@/locales/de.json'
import Card from './Card.vue'

// Kein DOM im Testlauf (vitest.setup.ts), also kein Klick. Was ein Zustand
// rendert, sieht ein SSR-Render durch den echten Compiler trotzdem — und der
// Bearbeiten-Zustand ist ein Modell, gerade damit er von außen setzbar ist.
type CardProps = InstanceType<typeof Card>['$props']

function render(props: CardProps, slots: Record<string, () => VNode> = {}) {
    const i18n = createI18n({ legacy: false, locale: 'de', messages: { de } })
    const app = createSSRApp({
        render: () => h(Card, props, slots),
    })
    app.use(i18n)
    return renderToString(app)
}

const read = () => h('p', 'gelesen')
const fields = () => h('p', 'bearbeitet')

describe('Card', () => {
    it('writes the summary beside the title while it is shut', async () => {
        const props = { title: 'Termine', summary: '2 Termine · ab 11.06.2027' }
        const open = await render({ ...props })
        const shut = await render({ ...props, defaultOpen: false })

        expect(shut).toContain('2 Termine · ab 11.06.2027')
        expect(open).not.toContain('2 Termine · ab 11.06.2027')
    })

    it('leaves the body away while it is shut', async () => {
        const open = await render({ title: 'Angebot' }, { default: read })
        const shut = await render({ title: 'Angebot', defaultOpen: false }, { default: read })

        expect(open).toContain('gelesen')
        expect(shut).not.toContain('gelesen')
    })

    it('turns the chevron over while it is open', async () => {
        const open = await render({ title: 'Angebot' })
        const shut = await render({ title: 'Angebot', defaultOpen: false })

        expect(open).toContain('rotate-180')
        expect(shut).not.toContain('rotate-180')
    })

    it('offers to edit only where the caller allows it', async () => {
        const plain = await render({ title: 'Notizen' })
        const editable = await render({ title: 'Anfrage', editable: true })

        expect(plain).not.toContain('Ändern')
        expect(editable).toContain('Ändern')
    })

    it('keeps the edit offer away while the card is shut', async () => {
        const html = await render({ title: 'Anfrage', editable: true, defaultOpen: false })

        expect(html).not.toContain('Ändern')
    })

    it('swaps the read view for the fields, not beside it', async () => {
        const html = await render(
            { title: 'Anfrage', editable: true, editing: true },
            { default: read, edit: fields },
        )

        expect(html).toContain('bearbeitet')
        expect(html).not.toContain('gelesen')
    })

    it('shows discard and save in place of the edit offer', async () => {
        const html = await render({ title: 'Anfrage', editable: true, editing: true })

        expect(html).toContain('Abbrechen')
        expect(html).toContain('Speichern')
        expect(html).not.toContain('Ändern')
    })
})
