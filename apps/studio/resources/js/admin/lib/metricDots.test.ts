import { describe, expect, it } from 'vitest'
import { drawDots, MAX_DOTS, type Tone } from './metricDots'

function tones(count: number, tone: Tone = 'on'): Tone[] {
  return Array.from({ length: count }, () => tone)
}

describe('drawDots', () => {
  it('draws in stage order, whatever order the cases arrived in', () => {
    expect(drawDots(['', 's2', 'warn', 'on']).shown).toEqual(['warn', 'on', 's2', ''])
  })

  it('never draws more than twelve, and counts the rest', () => {
    const { shown, overflow } = drawDots(tones(17))

    expect(shown).toHaveLength(MAX_DOTS)
    expect(overflow).toBe(5)
  })

  it('counts no overflow at exactly twelve', () => {
    expect(drawDots(tones(MAX_DOTS)).overflow).toBe(0)
  })

  it('keeps the twelve it draws in stage order, so the cut falls at the pale end', () => {
    const { shown } = drawDots([...tones(6, 'on'), ...tones(6, 's3'), ...tones(4, 'warn')])

    expect(shown.filter((tone) => tone === 'warn')).toHaveLength(4)
    expect(shown.filter((tone) => tone === 's3')).toHaveLength(2)
  })

  it('is empty for a metric with nothing behind it', () => {
    expect(drawDots([])).toEqual({ shown: [], overflow: 0 })
  })
})
