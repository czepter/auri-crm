import { centsToEuros, eurosToCents, formatDiscount, parseDiscount } from '@/lib/money'
import { applyDiscount, lineAmount, spread, subtotal, vatLines } from '@/lib/quoteTotals'

export type PricingMode = 'classic' | 'choice'

/** A position is the same thing in both modes (spec §4.2/§4.3) — one shape, one list. */
export type QuoteRow = {
  id?: string
  title: string
  description: string
  quantity: number
  unitPrice: string
  discount: string
  /** #412: Basispunkte, null = der Satz des Angebots. */
  vatRate: number | null
  selectable: boolean
  selected: boolean
  outcome: string
  hasImage: boolean
  /** #480: a rate picked into an invoice — billed at its own gross, whatever the price field says. */
  installmentId?: string
  /** The line of the accepted quote this position bills, so the quote knows what is still open. */
  quoteItemId?: string
}

/** Ein Kontakt des Projekts, wie ihn die Empfaengerkarte waehlt (§4.4). */
export type QuoteContact = { id: string; name: string; street: string | null; postcode: string | null; city: string | null }

/** ReadQuoteWorkspace's own vatSettings — was Quote::computeVat() aus den aktuellen Studio-Einstellungen macht. */
export type VatSettings = { mode: string; rate: number; note: string | null }

/** `fixed`: the rate is an amount, not a share — written as percent 0 with the amount as its minimum, which PaymentStageSplitter already pays out as it stands. */
export type StageRow = { percent: number; label: string; minimum: string; note: string; dueOn: string; fixed: boolean }

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
  discountHidden: boolean
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
  timeSortOrder: number
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
      vat_rate: row.vatRate,
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
    discount_hidden: draft.discountHidden,
    hourly_rate: eurosToCents(draft.slider.hourlyRate),
    min_hours: draft.slider.minHours,
    max_hours: draft.slider.maxHours,
    images_per_hour: draft.slider.imagesPerHour,
    hours: draft.slider.hours,
    time_title: draft.timeTitle,
    time_description: draft.timeDescription,
    time_sort_order: draft.timeSortOrder,
    items,
    stages: draft.slider.stages.map((stage) => ({
      percent: stage.fixed ? 0 : stage.percent,
      label: stage.label,
      minimum: eurosToCents(stage.minimum) ?? 0,
      note: stage.note || null,
      due_on: stage.dueOn || null,
    })),
  }
}

export type InvoiceItemPayload = {
  title?: string
  description?: string | null
  quantity?: number
  unit_price?: number | null
  discount_type?: string | null
  discount_value?: number | null
  vat_rate?: number | null
  installment_id?: string | null
  quote_item_id?: string | null
}

/** The inverse of the `items` buildQuotePayload writes, for an invoice draft posted earlier. */
export function rowsFromInvoiceItems(items: InvoiceItemPayload[]): QuoteRow[] {
  return items.map((item) => ({
    title: item.title ?? '',
    description: item.description ?? '',
    quantity: item.quantity ?? 1,
    unitPrice: centsToEuros(item.unit_price ?? null),
    discount: formatDiscount(item.discount_type ?? null, item.discount_value ?? null),
    vatRate: item.vat_rate ?? null,
    selectable: false,
    selected: false,
    outcome: '',
    hasImage: false,
    installmentId: item.installment_id ?? undefined,
    quoteItemId: item.quote_item_id ?? undefined,
  }))
}

/**
 * Der Entwurf, so wie ihn QuoteSlider und die klassische Tabelle in
 * portal/pages/Quotes/Show.vue lesen — rein im Browser gerechnet, damit die
 * Portal-Vorschau bei jeder Änderung sofort mitzieht statt auf einen
 * Server-Roundtrip zu warten (wie QuotePreviewPane ihn für das PDF macht).
 */
export function buildPreviewQuote(draft: QuoteDraft, vat: VatSettings): App.Quotes.Data.QuoteData {
  const items = draft.rows.map((row, index) => {
    const unitPrice = eurosToCents(row.unitPrice)
    const line = quoteRowToLine(row)
    const lineTotal = unitPrice === null ? 0 : row.quantity * unitPrice
    const netTotal = unitPrice === null ? 0 : lineAmount(line)

    return {
      id: row.id ?? `preview-${index}`,
      title: row.title,
      description: row.description || null,
      quantity: row.quantity,
      unitPrice,
      isIncluded: unitPrice === null,
      lineTotal,
      discountType: line.discountType,
      discountValue: line.discountValue,
      discountAmount: lineTotal - netTotal,
      netTotal,
      vatRate: row.vatRate,
      selectable: row.selectable,
      selected: row.selected,
      outcome: row.outcome || null,
      hasImage: row.hasImage,
    }
  })

  const rate = eurosToCents(draft.slider.hourlyRate) ?? 0
  const hours = draft.slider.hours ?? draft.slider.minHours ?? 0
  const timeTotal = draft.pricingMode === 'choice' ? hours * rate : 0

  const chargedItems = items.filter((item) => !item.selectable || item.selected)
  const itemsSubtotal = subtotal(
    chargedItems.map((item) => ({
      quantity: item.quantity,
      unitPrice: item.unitPrice ?? 0,
      discountType: item.discountType as 'percentage' | 'amount' | null,
      discountValue: item.discountValue,
    })),
    timeTotal,
  )

  const quoteDiscount = parseDiscount(draft.discount)
  const total = applyDiscount(itemsSubtotal, {
    type: quoteDiscount.type || null,
    value: quoteDiscount.type === 'percentage' && quoteDiscount.value !== null ? quoteDiscount.value / 100 : quoteDiscount.value,
  })
  const hidden = draft.discountHidden && draft.pricingMode === 'classic'

  if (hidden) {
    hideDiscount(items, total)
  }

  return {
    id: 'preview',
    createdAt: null,
    showcaseCount: 0,
    predecessorId: null,
    isAddendum: false,
    predecessorNumber: null,
    status: 'draft',
    statusLabel: '',
    pricingMode: draft.pricingMode,
    currency: 'EUR',
    validUntil: draft.validUntil || null,
    number: null,
    sentAt: null,
    sentChannel: null,
    total: hidden ? total : itemsSubtotal,
    discountType: hidden ? null : quoteDiscount.type || null,
    discountValue: hidden ? null : quoteDiscount.value,
    discountAmount: hidden ? 0 : itemsSubtotal - total,
    discountHidden: draft.discountHidden,
    net: total,
    // Gefroren wird erst beim Versenden (Quote::computeVat()) — bis dahin
    // rechnet die Vorschau live aus den aktuellen Studio-Einstellungen,
    // genau wie QuoteData::fromModel() es serverseitig für einen Entwurf tut.
    vatMode: vat.mode,
    vatRate: vat.rate,
    vatNote: vat.mode === 'small_business' ? vat.note : null,
    vatAmount: vat.mode === 'small_business' ? 0 : vatLines(chargedItems, vat.rate, total).reduce((sum, line) => sum + line.vat, 0),
    vatLines: vat.mode === 'standard' ? vatLines(chargedItems, vat.rate, total) : [],
    intro: draft.intro || null,
    outro: draft.outro || null,
    items,
    hourlyRate: draft.pricingMode === 'choice' ? rate : null,
    minHours: draft.slider.minHours,
    maxHours: draft.slider.maxHours,
    imagesPerHour: draft.slider.imagesPerHour,
    hours: draft.slider.hours,
    timeTitle: draft.timeTitle,
    timeDescription: draft.timeDescription,
    timeSortOrder: draft.timeSortOrder,
    rangeMin: null,
    rangeMax: null,
    paymentStages: draft.slider.stages.map((stage, index) => ({
      id: `preview-${index}`,
      percent: stage.fixed ? 0 : stage.percent,
      label: stage.label,
      minimum: eurosToCents(stage.minimum) ?? 0,
      note: stage.note || null,
      dueOn: stage.dueOn || null,
    })),
    recipientContact: draft.recipientContact,
    recipientStreet: draft.recipientStreet,
    recipientPostcode: draft.recipientPostcode,
    recipientCity: draft.recipientCity,
    portalUrl: null,
  }
}

/**
 * Portal-Vorschau von Quote::asSeenByClient(): der Rabatt steckt in den
 * Zeilenpreisen. Im Auswahl-Modus rechnet QuoteSlider selbst.
 *
 * ponytail: anteilig über alle Zeilen statt je Steuersatz — bei gemischten
 * Sätzen kann eine Zeile hier um einen Cent vom PDF abweichen.
 */
function hideDiscount(items: App.Quotes.Data.QuoteItemData[], net: number) {
  const charged = items.filter((item) => item.netTotal > 0 && (!item.selectable || item.selected))
  const amounts = spread(charged.map((item) => item.netTotal), net)

  charged.forEach((item, index) => {
    Object.assign(item, { discountType: null, discountValue: null, discountAmount: 0, lineTotal: amounts[index], netTotal: amounts[index] })
  })
}
