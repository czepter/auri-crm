import { eurosToCents, parseDiscount } from '@/admin/lib/money'

export type PricingMode = 'classic' | 'choice'

/** A position is the same thing in both modes (spec §4.2/§4.3) — one shape, one list. */
export type QuoteRow = {
  id?: string
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  selectable: boolean
  selected: boolean
  outcome: string
  hasImage: boolean
}

/** Ein Kontakt des Projekts, wie ihn die Empfaengerkarte waehlt (§4.4). */
export type QuoteContact = { id: string; name: string; street: string | null; postcode: string | null; city: string | null }

export type StageRow = { percent: number; label: string; minimum: string }

export type SliderDraft = {
  hourlyRate: string
  minHours: number | null
  maxHours: number | null
  imagesPerHour: number | null
  hours: number | null
  stages: StageRow[]
}

export type QuoteDraft = {
  pricingMode: PricingMode
  validUntil: string
  discount: string
  intro: string
  outro: string
  rows: QuoteRow[]
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
 * A row into `quoteTotals`' cents-and-plain-percent shape — the one place
 * that conversion happens, so the totals card, the time card's range tiles
 * and the payload builder can't drift apart on how a row is priced.
 */
export function quoteRowToLine(row: Pick<QuoteRow, 'quantity' | 'unitPrice' | 'discount' | 'selectable'>) {
  const discount = parseDiscount(row.discount)

  return {
    quantity: row.quantity,
    unitPrice: eurosToCents(row.unitPrice) ?? 0,
    discountType: discount.type || null,
    discountValue: discount.type === 'percentage' && discount.value !== null ? discount.value / 100 : discount.value,
    selectable: row.selectable,
  }
}

/**
 * Eine Route, ein Payload, eine Zeilenliste für beide Modi. `pricing_mode`
 * kommt aus dem Feld, das der Umschalter setzt — nicht aus dem Stundensatz,
 * sonst verliert ein Auswahlangebot ohne Stundensatz seine Positionen bei
 * jeder Autospeicherung.
 */
export function buildQuotePayload(draft: QuoteDraft, options: QuotePayloadOptions) {
  const quoteDiscount = parseDiscount(draft.discount)

  const items = draft.rows.map((row) => {
    const discount = parseDiscount(row.discount)

    return {
      id: row.id,
      title: row.title,
      description: row.description || null,
      quantity: row.quantity,
      unit_price: eurosToCents(row.unitPrice),
      discount_type: discount.type || null,
      discount_value: discount.value,
      selectable: row.selectable,
      selected: row.selected,
      outcome: row.outcome || null,
    }
  })

  return {
    pricing_mode: draft.pricingMode,
    valid_until: draft.validUntil || null,
    recipient_contact: draft.recipientContact,
    recipient_street: draft.recipientStreet,
    recipient_postcode: draft.recipientPostcode,
    recipient_city: draft.recipientCity,
    intro: draft.intro || null,
    outro: draft.outro || null,
    discount_type: quoteDiscount.type || null,
    discount_value: quoteDiscount.value,
    hourly_rate: eurosToCents(draft.slider.hourlyRate),
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
