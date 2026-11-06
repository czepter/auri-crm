import { describe, expect, it } from 'vitest'
import { openDocument } from './rail'

describe('openDocument', () => {
  it('returns the first unsettled row', () => {
    const rows = [{ kind: 'quote', settled: true }, { kind: 'contract', settled: false }, { kind: 'payments', settled: false }]

    expect(openDocument(rows)?.kind).toBe('contract')
  })

  it('returns null when everything is settled', () => {
    expect(openDocument([{ settled: true }])).toBeNull()
  })
})
