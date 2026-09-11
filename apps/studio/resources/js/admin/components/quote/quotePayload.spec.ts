import { describe, expect, it } from 'vitest'
import { buildQuotePayload, type QuoteDraft } from './quotePayload'

function baseDraft(): QuoteDraft {
  return {
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
      rows: [],
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
  }
}

function classicDraft(): QuoteDraft {
  const draft = baseDraft()

  draft.rows = [
    {
      id: 'item-1',
      title: 'Fine-Art-Album',
      description: 'Handgebunden',
      quantity: 2,
      unitPrice: '690,00',
      discount: '10%',
      remember: true,
    },
  ]

  return draft
}

function choiceDraft(): QuoteDraft {
  const draft = baseDraft()

  draft.slider.hourlyRate = '250,00'
  draft.slider.minHours = 3
  draft.slider.maxHours = 12
  draft.slider.hours = 5
  draft.slider.rows = [
    {
      id: 'item-2',
      title: 'Reportage',
      outcome: 'Digitale Bilder',
      unitPrice: '0,00',
      selectable: true,
      selected: false,
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

  it('markiert den Regler-Entwurf als choice', () => {
    const payload = buildQuotePayload(choiceDraft(), { canManageCatalog: false })

    expect(payload.pricing_mode).toBe('choice')
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
})
