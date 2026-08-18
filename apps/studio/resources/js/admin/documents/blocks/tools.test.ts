import { describe, expect, it } from 'vitest'
import { buildTools } from './tools'
import type { PaletteEntry } from './types'

const entry = (type: string, inline = false): PaletteEntry => ({ type, group: 'text', label: type, inline })

describe('buildTools', () => {
  const registry = { paragraph: 'P', header: 'H', columns: 'C' }

  it('registers exactly the palette entries the registry knows', () => {
    const tools = buildTools([entry('paragraph'), entry('header')], registry)

    expect(Object.keys(tools).sort()).toEqual(['header', 'paragraph'])
  })

  it('leaves out a block the palette does not offer, so a mail cannot insert columns', () => {
    const tools = buildTools([entry('paragraph')], registry)

    expect(tools).not.toHaveProperty('columns')
  })

  it('leaves out inline tools — they belong to the inline toolbar, not the inserter', () => {
    const tools = buildTools([entry('paragraph'), entry('mergeField', true)], registry)

    expect(tools).not.toHaveProperty('mergeField')
  })

  it('skips a palette entry with no implementation rather than crashing the editor', () => {
    const tools = buildTools([entry('paragraph'), entry('signature')], registry)

    expect(Object.keys(tools)).toEqual(['paragraph'])
  })
})

describe('buildTools with a palette that names what is refused', () => {
  it('registers the allowed blocks and never the dimmed ones', () => {
    const tools = buildTools(
      [
        { type: 'paragraph', group: 'text', label: 'Absatz', inline: false, allowed: true },
        { type: 'columns', group: 'layout', label: '2 Spalten', inline: false, allowed: false, only: 'Vertrag' },
      ],
      { paragraph: {}, columns: {} },
    )

    expect(Object.keys(tools)).toEqual(['paragraph'])
  })
})
