import { afterEach, describe, expect, it, vi } from 'vitest'
import { copyText } from './copyText'

afterEach(() => vi.unstubAllGlobals())

describe('copyText', () => {
  it('kopiert in die Zwischenablage', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { clipboard: { writeText } })

    expect(await copyText('abc')).toBe(true)
    expect(writeText).toHaveBeenCalledWith('abc')
  })

  it('meldet Fehler, wenn die Zwischenablage fehlt oder ablehnt, und kopiert nichts Leeres', async () => {
    vi.stubGlobal('navigator', {})
    expect(await copyText('abc')).toBe(false)

    vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } })
    expect(await copyText('abc')).toBe(false)
    expect(await copyText('')).toBe(false)
  })
})
