import { describe, expect, it } from 'vitest'
import { splitStages } from './paymentStages'

const STAGES = [
  { label: 'Bei der Zusage', percent: 25, minimum: 80_000 },
  { label: 'Vier Wochen vorher', percent: 50, minimum: 0 },
  { label: 'Nach der Lieferung', percent: 25, minimum: 0 },
]

describe('splitStages', () => {
  // Dieselben zwei Faelle wie die PHP-Seite, damit die Vorschau und die
  // spaeter erzeugten Raten nicht auseinanderlaufen.
  it('lets a minimum push the share up and takes the difference off the last stage', () => {
    const split = splitStages(215_000, STAGES)

    expect(split.map((row) => row.amount)).toEqual([80_000, 108_000, 27_000])
    expect(split.reduce((sum, row) => sum + row.amount, 0)).toBe(215_000)
  })

  it('drops the stages an oversized minimum leaves nothing for', () => {
    const split = splitStages(75_000, STAGES)

    expect(split).toHaveLength(1)
    expect(split[0]).toEqual({ label: 'Bei der Zusage', amount: 75_000 })
  })

  it('always adds up to the total', () => {
    for (const total of [0, 1_000, 99_999, 250_000, 1_000_000]) {
      const split = splitStages(total, STAGES)

      expect(split.reduce((sum, row) => sum + row.amount, 0)).toBe(total)
    }
  })
})

describe('a stage written as an amount', () => {
  it('pays its minimum as it stands and leaves the rest to the last stage', () => {
    expect(splitStages(400_000, [
      { label: 'Reservierungsgebühr', percent: 0, minimum: 50_000 },
      { label: 'Restbetrag', percent: 100, minimum: 0 },
    ])).toEqual([
      { label: 'Reservierungsgebühr', amount: 50_000 },
      { label: 'Restbetrag', amount: 350_000 },
    ])
  })
})
