type Capability = App.Enums.Capability

export type NavEntry = {
    /** Eine Zeile. Fehlt er, ist der Eintrag eine Gruppenueberschrift. */
    key?: string
    /** Eine Gruppenueberschrift, mit `fold` eine zuklappbare. */
    group?: string
    fold?: true
    label?: string
    href?: string
    icon?: string
    count?: number
    needs?: Capability | Capability[]
    studio?: boolean
    children?: NavEntry[]
}

export type NavCounts = {
    unread?: number
    leads?: number
    projects?: number
}

/** Die Zeilen, die "Studio" zuklappt. */
export const studioKeys = ['pakete', 'vorlagen', 'team', 'einstellungen']

/** Die Zeilen unter "Unterlagen". */
export const documentKeys = ['angebote', 'rechnungen', 'gutschriften']

// C-02 (#236), jetzt in vier Bloecken statt einem. Oben steht, was ohne
// einzelnen Auftrag gilt; "Arbeit" ist, was heute ansteht, "Auftraege" sind
// die Datensaetze — dieselbe Trennung, die eine Aufgabenliste von einem
// Projekt unterscheidet. Elf gleichrangige Zeilen waren eine Liste von allem,
// was das Produkt hat, in der Reihenfolge, in der die Screens entstanden sind.
//
// "Studio" faltet zu: seine Zeilen sind Einrichtung, nicht Alltag. Wer darin
// steht, sieht sie aufgeklappt, sonst kostet der Block eine Zeile.
//
// "Unterlagen" buendelt Angebote, Rechnungen und Gutschriften — die drei
// Arten, die eine Buchhaltung als Belege kennt.
//
// Nur Eintraege mit einem Screen dahinter erscheinen, dieselbe Regel, die C-06
// auf seine Tabs und S-01 auf die Uebersicht anwendet: ein Eintrag, der nirgends
// hinfuehrt, bringt dem Team bei, der Nav zu misstrauen. Aus S-06 gilt dasselbe
// fuer Befugnis: was diese Rolle abgewiesen bekaeme, fehlt, statt in einen 403
// zu rendern.
//
// Icons: 17px, Strich 1.6, runde Enden, Farbe folgt dem Text. Anfragen ist ein
// Board aus drei Spalten, Posteingang ein Umschlag, Unterlagen eine Mappe,
// Auswertungen Balken. Kontakte bekam das Adressbuch, sonst waere es dasselbe
// Personensymbol wie Team.
export function navItems(can: Capability[], counts: NavCounts = {}): NavEntry[] {
    const allowed = (item: NavEntry) =>
        !item.needs || [item.needs].flat().some((need) => can.includes(need))

    const entries: NavEntry[] = [
        {
            key: 'uebersicht',
            label: 'Übersicht',
            href: '/',
            icon: '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
        },
        {
            // S-13: Zahlen ueber alle Auftraege, also dieselbe Befugnis, die alle
            // Auftraege sehen laesst. Steht neben der Uebersicht, weil es eine
            // Auswertung ist und keine Einrichtung.
            key: 'auswertungen',
            label: 'Auswertungen',
            href: '/auswertungen',
            needs: 'view_all_projects',
            icon: '<path d="M3.5 20h17"/><path d="M7 20v-6M12 20V6M17 20v-9"/>',
        },
        { group: 'Arbeit' },
        {
            // E-12: das Fach fuer Mail, die ankam und zu nichts passte. Dieselbe
            // Befugnis wie eine Antwort an einen Kunden, denn eine Mail zu
            // triagieren heisst zu entscheiden, zu welchem Gespraech sie gehoert.
            key: 'posteingang',
            label: 'Posteingang',
            href: '/posteingang',
            needs: 'manage_messages',
            icon: '<path d="M3 6.5h18v11H3z"/><path d="m3 7.5 9 6 9-6"/>',
        },
        {
            // T-11. Eine Zeile statt eines Popovers: Zaehler und aktiver Zustand
            // stehen hier schon, ein Panel waere ein zweiter Weg auf dieselbe Liste.
            key: 'benachrichtigungen',
            label: 'Benachrichtigungen',
            href: '/benachrichtigungen',
            count: counts.unread,
            icon: '<path d="M18 16v-5a6 6 0 10-12 0v5l-1.5 2.5h15L18 16z"/><path d="M10 19.5a2 2 0 004 0"/>',
        },
        {
            // T-24. Der Screen gab es, die Zeile nicht — Tasks/Index lief unter
            // dem aktiven Eintrag der Uebersicht.
            key: 'aufgaben',
            label: 'Aufgaben',
            href: '/aufgaben',
            icon: '<path d="M4 6.5 5.8 8.3 9 5"/><path d="M4 12.5 5.8 14.3 9 11"/><path d="M4 18.5 5.8 20.3 9 17"/><path d="M12.5 6.5H20M12.5 12.5H20M12.5 18.5H20"/>',
        },
        {
            key: 'kalender',
            label: 'Kalender',
            href: '/kalender',
            icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        },
        {
            // T-10. Bleibt eine eigene Zeile: C-13 (#249) faltet Abwesenheiten in
            // das Monatsraster, und bis dahin waere ein Aufklapper fuer einen
            // einzigen Untereintrag mehr Mechanik als Nutzen.
            key: 'abwesenheiten',
            label: 'Abwesenheiten',
            href: '/abwesenheiten',
            icon: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h3M13 14h3M8 17h3"/>',
        },
        { group: 'Aufträge' },
        {
            key: 'anfragen',
            label: 'Anfragen',
            href: '/anfragen',
            count: counts.leads,
            needs: 'manage_leads',
            icon: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 8v8M13 8v5M18 8v10"/>',
        },
        {
            key: 'unterlagen',
            label: 'Unterlagen',
            icon: '<path d="M4 7.5h6l1.6 2H20v9.5H4z"/><path d="M7 7.5V5h9v4.5"/>',
            children: [
                {
                    key: 'angebote',
                    label: 'Angebote',
                    href: '/angebote',
                    needs: 'manage_quotes',
                    icon: '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M9.5 13h5M9.5 16h5"/>',
                },
                {
                    key: 'rechnungen',
                    label: 'Rechnungen',
                    href: '/rechnungen',
                    needs: 'manage_contracts',
                    icon: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
                },
                {
                    key: 'gutschriften',
                    label: 'Gutschriften',
                    href: '/gutschriften',
                    needs: 'manage_contracts',
                    icon: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 12h6"/>',
                },
            ],
        },
        {
            key: 'projekte',
            label: 'Projekte',
            href: '/projekte',
            count: counts.projects,
            icon: '<path d="M4 8h16v12H4z"/><path d="M9 8V5h6v3"/><path d="M4 13h16"/>',
        },
        {
            key: 'galerien',
            label: 'Galerien',
            href: '/galerien',
            needs: 'manage_shoots',
            icon: '<rect x="3" y="3" width="13" height="13" rx="2"/><path d="M8 21h11a2 2 0 0 0 2-2V8"/>',
        },
        {
            key: 'kontakte',
            label: 'Kontakte',
            href: '/kontakte',
            needs: 'manage_contacts',
            icon: '<path d="M6 3h12v18H6z"/><circle cx="12" cy="10" r="2.4"/><path d="M8.7 17c.6-1.7 1.8-2.6 3.3-2.6s2.7.9 3.3 2.6"/><path d="M6 7H3.5M6 12H3.5M6 17H3.5"/>',
        },
        { group: 'Studio', fold: true },
        {
            key: 'pakete',
            label: 'Pakete',
            href: '/pakete',
            needs: 'manage_packages',
            studio: true,
            icon: '<path d="M21 8.5v7l-9 4.5-9-4.5v-7L12 4z"/><path d="M3 8.5 12 13l9-4.5M12 13v7"/>',
        },
        {
            // Vier Arten unter einem Eintrag (TemplateHubController). Wer nur
            // eine davon fuehren darf, sieht ihn deshalb auch — der Hub zeigt
            // nur die Arten, fuer die es reicht.
            key: 'vorlagen',
            label: 'Vorlagen',
            href: '/vorlagen',
            needs: [
                'manage_tasks',
                'manage_form_templates',
                'manage_contracts',
                'manage_settings',
            ],
            studio: true,
            icon: '<rect x="4" y="3" width="12" height="16" rx="2"/><path d="M8 8h4M8 12h4M19 7v12a2 2 0 0 1-2 2H8"/>',
        },
        {
            key: 'team',
            label: 'Team',
            href: '/team',
            needs: 'manage_staff',
            studio: true,
            icon: '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16.5 5.5a3.2 3.2 0 010 5.6M17.5 14.8c2 .7 3.5 2.6 3.5 5.2"/>',
        },
        {
            key: 'einstellungen',
            label: 'Einstellungen',
            href: '/einstellungen',
            needs: 'manage_settings',
            studio: true,
            icon: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
        },
    ]

    return entries
        .map((item) =>
            item.children
                ? { ...item, children: item.children.filter(allowed) }
                : item,
        )
        .filter((item) => {
            if (item.group) return true
            if (item.children) return item.children.length > 0

            return allowed(item)
        })
}
