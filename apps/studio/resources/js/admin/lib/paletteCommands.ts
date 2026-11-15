import { computed, onScopeDispose, shallowReactive } from "vue"

type Capability = App.Enums.Capability

// A command with `run` changes something; one with `href` only navigates.
export type Command = { id: string; title: string; href?: string; run?: () => void; shortcut?: string }
type Source = () => Command[]

// Module-level: a page renders AdminLayout inside its own template, so no
// provide/inject path leads from the page to the palette.
const sources = shallowReactive<Source[]>([])

export const pageCommands = computed(() => sources.flatMap((source) => source()))

export function usePageCommands(source: Source) {
    sources.push(source)
    onScopeDispose(() => sources.splice(sources.indexOf(source), 1))
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
