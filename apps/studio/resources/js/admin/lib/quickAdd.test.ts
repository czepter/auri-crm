import { describe, expect, it } from 'vitest'
import { parseQuickAdd } from './quickAdd'

// Mittwoch, 25.11.2026
const wednesday = new Date(2026, 10, 25)

describe('parseQuickAdd', () => {
  it('reads heute and morgen', () => {
    expect(parseQuickAdd('Angebot schicken heute', wednesday)).toEqual({ title: 'Angebot schicken', dueOn: '2026-11-25' })
    expect(parseQuickAdd('Angebot schicken morgen', wednesday)).toEqual({ title: 'Angebot schicken', dueOn: '2026-11-26' })
  })

  it('takes the next occurrence of a weekday, a week out for today’s own', () => {
    expect(parseQuickAdd('Anrufen Freitag', wednesday).dueOn).toBe('2026-11-27')
    expect(parseQuickAdd('Anrufen Mittwoch', wednesday).dueOn).toBe('2026-12-02')
    expect(parseQuickAdd('Anrufen Mo.', wednesday).dueOn).toBe('2026-11-30')
  })

  it('reads a date, rolling into next year once it has passed', () => {
    expect(parseQuickAdd('Rechnung prüfen 27.11.', wednesday).dueOn).toBe('2026-11-27')
    expect(parseQuickAdd('Rechnung prüfen 03.01.', wednesday).dueOn).toBe('2027-01-03')
    expect(parseQuickAdd('Rechnung prüfen 24.11.', wednesday).dueOn).toBe('2027-11-24')
    expect(parseQuickAdd('Rechnung prüfen 27.11.2026', wednesday)).toEqual({ title: 'Rechnung prüfen', dueOn: '2026-11-27' })
  })

  it('leaves the title alone without a match', () => {
    expect(parseQuickAdd('Das ist so', wednesday)).toEqual({ title: 'Das ist so', dueOn: null })
    expect(parseQuickAdd('Rechnung 31.02.', wednesday).dueOn).toBeNull()
    expect(parseQuickAdd('morgen', wednesday)).toEqual({ title: 'morgen', dueOn: null })
  })

  it('ignores a day word inside the title', () => {
    expect(parseQuickAdd('Freitag Termin bestätigen', wednesday)).toEqual({ title: 'Freitag Termin bestätigen', dueOn: null })
  })
})
