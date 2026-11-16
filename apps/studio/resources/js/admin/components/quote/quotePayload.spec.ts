import { describe, expect, it } from 'vitest'
import { buildPreviewQuote, buildQuotePayload, quoteRowToLine, rowsFromInvoiceItems, type QuoteDraft, type VatSettings } from './quotePayload'

const STANDARD: VatSettings = { mode: 'standard', rate: 1900, note: null }
// effectiveVatRate() always zeroes the rate under small_business (see
// StudioSettings::current()) — ReadQuoteWorkspace never sends a nonzero one here.
const SMALL_BUSINESS: VatSettings = { mode: 'small_business', rate: 0, note: 'Kleinunternehmer nach § 19 UStG' }

function baseDraft(pricingMode: QuoteDraft['pricingMode']): QuoteDraft {
  return {
    pricingMode,
    validUntil: '2026-10-01',
    discount: '',
    discountHidden: false,
    intro: '',
    outro: '',
    rows: [],
    slider: {
      hourlyRate: '',
      minHours: null,
      maxHours: null,
      imagesPerHour: null,
      hours: null,
      stages: [
        { percent: 30, label: 'Bei der Zusage', minimum: '500', note: 'wird mit Schlussrechnung verrechnet', dueOn: '2026-11-27', fixed: false },
        { percent: 70, label: 'Am Termin', minimum: '', note: '', dueOn: '', fixed: false },
      ],
    },
    recipientContact: 'contact-1',
    recipientStreet: 'Musterstraße 1',
    recipientPostcode: '10115',
    recipientCity: 'Berlin',
    timeTitle: 'Hochzeit',
    timeDescription: 'Trauung und Feier',
    timeSortOrder: 0,
  }
}

function classicDraft(): QuoteDraft {
  const draft = baseDraft('classic')

  draft.rows = [
    {
      id: 'item-1',
      title: 'Fine-Art-Album',
      description: 'Handgebunden',
      quantity: 2,
      unitPrice: '690,00',
      discount: '10%',
      vatRate: null,
      selectable: false,
      selected: false,
      outcome: '',
      hasImage: false,
    },
  ]

  return draft
}

function choiceDraft(): QuoteDraft {
  const draft = baseDraft('choice')

  draft.slider.hourlyRate = '250,00'
  draft.slider.minHours = 3
  draft.slider.maxHours = 12
  draft.slider.hours = 5
  draft.rows = [
    {
      id: 'item-2',
      title: 'Reportage',
      description: '',
      quantity: 2,
      unitPrice: '690,00',
      discount: '10%',
      vatRate: null,
      selectable: true,
      selected: false,
      outcome: 'Digitale Bilder',
      hasImage: false,
    },
  ]

  return draft
}

describe('buildQuotePayload', () => {
  it('markiert den klassischen Entwurf als classic', () => {
    const payload = buildQuotePayload(classicDraft(), { canManageCatalog: false })

    expect(payload.pricing_mode).toBe('classic')
  })

  it('markiert den Auswahl-Entwurf als choice', () => {
    const payload = buildQuotePayload(choiceDraft(), { canManageCatalog: false })

    expect(payload.pricing_mode).toBe('choice')
  })

  it('liest den Modus aus dem Feld, nicht aus dem Stundensatz', () => {
    const draft = choiceDraft()
    draft.slider.hourlyRate = ''

    const payload = buildQuotePayload(draft, { canManageCatalog: false })

    expect(payload.pricing_mode).toBe('choice')
    expect(payload.items).toHaveLength(1)
  })

  it.each([
    ['classic', classicDraft()],
    ['choice', choiceDraft()],
  ])('trägt Menge, Preis, Rabatt, Auswahl und id auf jeder Zeile im %s-Modus', (_mode, draft) => {
    const payload = buildQuotePayload(draft, { canManageCatalog: false })

    expect(payload.items).toHaveLength(1)

    const item = payload.items[0] as Record<string, unknown>

    for (const key of [
      'id',
      'quantity',
      'unit_price',
      'discount_type',
      'discount_value',
      'selectable',
      'selected',
      'outcome',
    ]) {
      expect(item).toHaveProperty(key)
    }

    expect(item.quantity).toBe(2)
    expect(item.discount_type).toBe('percentage')
  })

  it.each([
    ['classic', classicDraft()],
    ['choice', choiceDraft()],
  ])('schickt beide Zahlungsstufen im %s-Modus mit', (_mode, draft) => {
    const payload = buildQuotePayload(draft, { canManageCatalog: false })

    expect(payload.stages).toHaveLength(2)
    expect(payload.stages[0]).toMatchObject({ percent: 30, label: 'Bei der Zusage', note: 'wird mit Schlussrechnung verrechnet', due_on: '2026-11-27' })
    expect(payload.stages[1]).toMatchObject({ percent: 70, label: 'Am Termin', note: null, due_on: null })
  })

  it.each([
    ['classic', classicDraft()],
    ['choice', choiceDraft()],
  ])('reicht Empfänger und Zeitzeile unverändert durch im %s-Modus', (_mode, draft) => {
    const payload = buildQuotePayload(draft, { canManageCatalog: false })

    expect(payload.recipient_contact).toBe('contact-1')
    expect(payload.recipient_street).toBe('Musterstraße 1')
    expect(payload.recipient_postcode).toBe('10115')
    expect(payload.recipient_city).toBe('Berlin')
    expect(payload.time_title).toBe('Hochzeit')
    expect(payload.time_description).toBe('Trauung und Feier')
  })

  // Zwei Einheiten für dasselbe Feld: die Zeilenrechnung im Editor arbeitet
  // in Prozent, die Spalte hinter der Route in Basispunkten.
  it('rechnet den Zeilenrabatt in Prozent, schickt ihn in Basispunkten', () => {
    const row = classicDraft().rows[0]

    expect(quoteRowToLine(row).discountValue).toBe(10)
    expect(buildQuotePayload(classicDraft(), { canManageCatalog: false }).items[0].discount_value).toBe(1000)
  })
})

describe('buildPreviewQuote', () => {
  it('markiert eine leere Preisangabe als enthaltene Leistung, ohne Summe', () => {
    const draft = classicDraft()
    draft.rows[0].unitPrice = ''

    const quote = buildPreviewQuote(draft, STANDARD)

    expect(quote.items[0].isIncluded).toBe(true)
    expect(quote.items[0].netTotal).toBe(0)
  })

  it('rechnet den Zeilenrabatt in die Zeilensumme ein', () => {
    // 2 × 690,00 € abzüglich 10 % = 124.200 Cent.
    const quote = buildPreviewQuote(classicDraft(), STANDARD)

    expect(quote.items[0].netTotal).toBe(124200)
  })

  it('setzt hourlyRate nur im Auswahl-Modus, damit QuoteSlider statt Tabelle rendert', () => {
    expect(buildPreviewQuote(classicDraft(), STANDARD).hourlyRate).toBeNull()
    expect(buildPreviewQuote(choiceDraft(), STANDARD).hourlyRate).toBe(25000)
  })

  it('zählt im Auswahl-Modus nur die Zeit plus gewählte wählbare Zeilen in die Summe', () => {
    // 5 Std. × 250,00 € Stundensatz = 125.000 Cent, die wählbare Zeile zählt nur gewählt mit.
    const draft = choiceDraft()
    draft.rows[0].selected = false

    expect(buildPreviewQuote(draft, STANDARD).total).toBe(125000)

    draft.rows[0].selected = true
    expect(buildPreviewQuote(draft, STANDARD).total).toBe(249200)
  })

  // Q-04 friert vat_mode erst beim Versenden ein; buildPreviewQuote zeigt
  // bis dahin, was Quote::computeVat() aus den aktuellen Einstellungen
  // rechnen würde — nie null, auch im Entwurf nicht (siehe QuotePortalPreview.vue).
  it('rechnet die MwSt aus den aktuellen Studio-Einstellungen, nie aus gefrorenen Spalten', () => {
    // Netto 124.200 Cent × 19 % gerundet = 23.598 Cent.
    const standard = buildPreviewQuote(classicDraft(), STANDARD)

    expect(standard.vatMode).toBe('standard')
    expect(standard.vatAmount).toBe(23598)
    expect(standard.vatNote).toBeNull()

    const smallBusiness = buildPreviewQuote(classicDraft(), SMALL_BUSINESS)

    expect(smallBusiness.vatMode).toBe('small_business')
    expect(smallBusiness.vatAmount).toBe(0)
    expect(smallBusiness.vatNote).toBe('Kleinunternehmer nach § 19 UStG')
  })
})

describe('rowsFromInvoiceItems', () => {
  it('restores the rows buildQuotePayload wrote', () => {
    const draft = classicDraft()
    draft.rows = [
      { ...draft.rows[0], title: 'Abzüge', description: 'Format A4', quantity: 2, unitPrice: '20.00', discount: '10%', vatRate: 1900, installmentId: 'ra-1', quoteItemId: 'qi-1' },
    ]

    const { items } = buildQuotePayload(draft, { canManageCatalog: false })
    const restored = rowsFromInvoiceItems(items.map((item) => ({ ...item, installment_id: 'ra-1', quote_item_id: 'qi-1' })))

    expect(restored).toEqual([expect.objectContaining({
      title: 'Abzüge', description: 'Format A4', quantity: 2, unitPrice: '20.00', discount: '10,00 %', vatRate: 1900, installmentId: 'ra-1', quoteItemId: 'qi-1',
    })])
  })
})
