import de from '@/locales/de.json'

/*
 * Die Rolle eines Kontakts am Projekt ist Freitext: „Trauredner" steht in
 * keiner Aufzählung. Die fünf bekannten haben eine Beschriftung im Katalog,
 * jede andere steht so da, wie sie getippt wurde.
 *
 * Nicht über $t: main.ts lässt einen fehlenden Schlüssel in der Entwicklung
 * werfen, und eine freie Rolle hat keinen.
 */
const KNOWN: Record<string, string> = de.contacts.role

export function roleLabel(role: string): string {
    return KNOWN[role] ?? role
}

/** Die fünf bekannten als Vorschlagsliste für das Eingabefeld. */
export const ROLE_SUGGESTIONS: string[] = Object.values(KNOWN)
