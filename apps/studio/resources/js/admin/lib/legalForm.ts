/*
 * Registernummer und Registergericht gehören auf Impressum und Beleg — aber
 * nur für Rechtsformen, die in einem Register stehen. Ein Einzelunternehmen
 * und eine GbR stehen in keinem, und ein Pflichtfeld, das es für sie nicht
 * gibt, ist eine Rechnung, die nicht gespeichert werden kann.
 */
const REGISTERED = [
    'GmbH',
    'UG (haftungsbeschränkt)',
    'AG',
    'e.K.',
    'OHG',
    'KG',
    'GmbH & Co. KG',
    'PartG',
]

export function requiresRegister(legalForm: string | null): boolean {
    return legalForm !== null && REGISTERED.includes(legalForm)
}
