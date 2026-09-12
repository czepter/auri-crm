import { eurosToCents, parseDiscount } from '@/admin/lib/money'

export type QuoteRow = {
  id?: string
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  remember: boolean
}

export type SliderRow = {
  id?: string
  title: string
  outcome: string
  unitPrice: string
  selectable: boolean
  selected: boolean
  hasImage: boolean
}

export type StageRow = { percent: number; label: string; minimum: string }

export type SliderDraft = {
  hourlyRate: string
  minHours: number | null
  maxHours: number | null
  imagesPerHour: number | null
  hours: number | null
  rows: SliderRow[]
  stages: StageRow[]
}

export type QuoteDraft = {
  validUntil: string
  discount: string
  intro: string
  outro: string
  rows: QuoteRow[]
  // Q-14: derselbe Entwurf traegt beide Welten. hourlyRate leer heisst
  // Positionstabelle, und dann ruehrt nichts hier unten etwas an.
  slider: SliderDraft
  // Kein Feld im Editor bedient diese fünf — sie reisen nur mit, damit ein
  // Autosave sie nicht durch ihr Fehlen im Payload leerräumt.
  recipientContact: string | null
  recipientStreet: string | null
  recipientPostcode: string | null
  recipientCity: string | null
  timeTitle: string | null
  timeDescription: string | null
}

export type QuotePayloadOptions = {
  canManageCatalog: boolean
}

/**
 * Q-14: eine Route, ein Payload. Der Stundensatz entscheidet, aus welcher
 * Zeilenliste die Positionen kommen; pricing_mode, Empfänger und Zeitzeile
 * reisen in beiden Fällen mit, sonst räumt ein Autosave sie durch ihr
 * Fehlen leer. Die id einer Zeile geht in beiden Modi mit, weil der
 * Controller das Bild einer Regler-Zeile daran wiederfindet — der Pfad
 * selbst kommt nie aus dem Formular.
 */
export function buildQuotePayload(draft: QuoteDraft, options: QuotePayloadOptions) {
  const quoteDiscount = parseDiscount(draft.discount)
  const rate = eurosToCents(draft.slider.hourlyRate)

  const items =
    rate !== null
      ? draft.slider.rows.map((row) => ({
          id: row.id,
          title: row.title,
          description: null,
          quantity: 1,
          unit_price: eurosToCents(row.unitPrice),
          discount_type: null,
          discount_value: null,
          selectable: row.selectable,
          selected: row.selected,
          outcome: row.outcome || null,
          // Ohne die Faehigkeit geht der Schluessel gar nicht erst mit, damit
          // der Waechter im Controller nicht ohne Anlass greift.
          ...(options.canManageCatalog ? { remember: false } : {}),
        }))
      : draft.rows.map((row) => {
          const discount = parseDiscount(row.discount)

          return {
            id: row.id,
            title: row.title,
            description: row.description || null,
            quantity: row.quantity,
            unit_price: eurosToCents(row.unitPrice),
            discount_type: discount.type || null,
            discount_value: discount.value,
            selectable: false,
            selected: false,
            outcome: null,
            ...(options.canManageCatalog ? { remember: row.remember } : {}),
          }
        })

  return {
    pricing_mode: rate !== null ? 'choice' : 'classic',
    valid_until: draft.validUntil || null,
    recipient_contact: draft.recipientContact,
    recipient_street: draft.recipientStreet,
    recipient_postcode: draft.recipientPostcode,
    recipient_city: draft.recipientCity,
    intro: draft.intro || null,
    outro: draft.outro || null,
    discount_type: quoteDiscount.type || null,
    discount_value: quoteDiscount.value,
    hourly_rate: rate,
    min_hours: draft.slider.minHours,
    max_hours: draft.slider.maxHours,
    images_per_hour: draft.slider.imagesPerHour,
    hours: draft.slider.hours,
    time_title: draft.timeTitle,
    time_description: draft.timeDescription,
    items,
    stages: draft.slider.stages.map((stage) => ({
      percent: stage.percent,
      label: stage.label,
      minimum: eurosToCents(stage.minimum) ?? 0,
    })),
  }
}
