// Die Bausteine, die aus Zeilen mit festen Spalten bestehen — Positionen,
// Zahlungsplan, Nutzungsrechte, Ablauf, Lieferumfang, Ausfallstaffel.
//
// Eine Spezifikation, zwei Leser: die Werkzeugfabrik in tableTools.ts baut
// daraus die Eingabemaske, serialize.ts daraus die Tabelle im Dokument. Ohne
// die gemeinsame Quelle driften Kopfzeile und Eingabe auseinander, sobald
// jemand eine Spalte umbenennt.

export type RowColumn = {
  key: string
  label: string
  /** Rechtsbündig: Beträge, Mengen, Fristen in Zahlen. */
  numeric?: boolean
}

export type RowSpec = {
  type: string
  title: string
  icon: string
  columns: RowColumn[]
  /** Summenzeile unter der Tabelle — nur dort, wo Geld addiert wird. */
  totals?: boolean
}

export const ROW_SPECS: RowSpec[] = [
  {
    type: 'lineItems',
    title: 'Positionen',
    icon: '<b>≡</b>',
    columns: [
      { key: 'qty', label: 'Menge', numeric: true },
      { key: 'label', label: 'Leistung' },
      { key: 'unit', label: 'Einzelpreis', numeric: true },
      { key: 'total', label: 'Summe', numeric: true },
    ],
    totals: true,
  },
  {
    type: 'paymentPlan',
    title: 'Zahlungsplan',
    icon: '<b>◷</b>',
    columns: [
      { key: 'label', label: 'Rate' },
      { key: 'due', label: 'Fällig' },
      { key: 'amount', label: 'Betrag', numeric: true },
    ],
  },
  {
    type: 'usageRights',
    title: 'Nutzungsrechte',
    icon: '<b>◎</b>',
    columns: [
      { key: 'channel', label: 'Kanal' },
      { key: 'scope', label: 'Gebiet' },
      { key: 'duration', label: 'Dauer' },
      { key: 'exclusive', label: 'Exklusiv' },
    ],
  },
  {
    type: 'schedule',
    title: 'Ablaufplan',
    icon: '<b>◔</b>',
    columns: [
      { key: 'time', label: 'Uhrzeit', numeric: true },
      { key: 'item', label: 'Programmpunkt' },
      { key: 'place', label: 'Ort' },
    ],
  },
  {
    type: 'deliverables',
    title: 'Lieferumfang',
    icon: '<b>▤</b>',
    columns: [
      { key: 'item', label: 'Leistung' },
      { key: 'amount', label: 'Umfang' },
      { key: 'format', label: 'Format' },
      { key: 'deadline', label: 'Frist' },
    ],
  },
  {
    type: 'cancellation',
    title: 'Ausfallstaffel',
    icon: '<b>◑</b>',
    columns: [
      { key: 'window', label: 'Zeitraum vor Termin' },
      { key: 'share', label: 'Anteil', numeric: true },
    ],
  },
]

export function rowSpec(type: string): RowSpec | undefined {
  return ROW_SPECS.find((spec) => spec.type === type)
}
