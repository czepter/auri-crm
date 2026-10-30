/*
 * Welche Rechtstexte stehen müssen, bevor das erste Angebot hinausgeht.
 *
 * Impressum ist nicht dabei: ohne Adresse und ohne Vorlage zeichnet das
 * Portal die Seite aus den Studio-Daten, es gibt dort also immer eine
 * (siehe LegalTexts::url()). AGB sind eine Entscheidung des
 * Studios, keine Pflicht. Die Widerrufsbelehrung ist beides nicht: ohne sie
 * beginnt die Frist eines Verbrauchers gar nicht erst zu laufen.
 */
const REQUIRED_BEFORE_SEND = ['widerruf'] as const

export type LegalSource = { url: string; family: string }

/** @returns die Schlüssel der Texte, zu denen weder Adresse noch Vorlage hinterlegt ist */
export function missingLegalTexts(sources: Record<string, LegalSource>): string[] {
    return REQUIRED_BEFORE_SEND.filter((text) => {
        const source = sources[text]

        return !source || (!source.url && !source.family)
    })
}
