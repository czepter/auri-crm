import { computed, inject, provide, shallowReactive, type InjectionKey } from "vue"

type Capability = App.Enums.Capability

export type Command = { id: string; title: string; href?: string; run?: () => void; shortcut?: string }
type Source = () => Command[]

const sources: InjectionKey<Source[]> = Symbol("paletteCommands")

export function providePageCommands() {
    const list = shallowReactive<Source[]>([])
    provide(sources, list)

    return computed(() => list.flatMap((source) => source()))
}

// AdminLayout mounts anew with every navigation, so a source never outlives its page.
export function usePageCommands(source: Source) {
    inject(sources, null)?.push(source)
}

// Same capabilities as the nav rows that lead to the list each of these starts in.
export function createCommands(can: Capability[], t: (key: string) => string): Command[] {
    return [
        { id: "new-lead", title: t("leads.createKicker"), href: "/anfragen/neu", needs: "manage_leads" },
        { id: "new-quote", title: t("quotes.create"), href: "/angebote/neu", needs: "manage_quotes" },
        { id: "new-contact", title: t("contacts.create"), href: "/kontakte/neu", needs: "manage_contacts" },
        { id: "new-gallery", title: t("galleries.create"), href: "/galerien/neu", needs: "manage_shoots" },
        { id: "new-invoice", title: t("invoicesOverview.create"), href: "/rechnungen/neu", needs: "manage_contracts" },
    ].flatMap(({ needs, ...command }) => (can.includes(needs as Capability) ? [command] : []))
}
