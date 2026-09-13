import { describe, expect, it } from 'vitest'
import { buildQuotePayload, quoteRowToLine, type QuoteDraft } from './quotePayload'

function baseDraft(pricingMode: QuoteDraft['pricingMode']): QuoteDraft {
  return {
    pricingMode,
    validUntil: '2026-10-01',
    discount: '',
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
        { percent: 30, label: 'Bei der Zusage', minimum: '500' },
        { percent: 70, label: 'Am Termin', minimum: '' },
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
    expect(payload.stages[0]).toMatchObject({ percent: 30, label: 'Bei der Zusage' })
    expect(payload.stages[1]).toMatchObject({ percent: 70, label: 'Am Termin' })
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
