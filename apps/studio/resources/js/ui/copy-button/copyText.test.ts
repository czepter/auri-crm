import { afterEach, describe, expect, it, vi } from 'vitest'
import { copyText } from './copyText'

function stubDom(execCommand: boolean) {
  const area = { setAttribute: vi.fn(), select: vi.fn(), remove: vi.fn(), style: {} }
  const doc = {
    createElement: vi.fn(() => area),
    body: { appendChild: vi.fn() },
    execCommand: vi.fn(() => execCommand),
  }
  vi.stubGlobal('document', doc)

  return doc
}

afterEach(() => vi.unstubAllGlobals())

describe('copyText', () => {
  it('fällt auf die Auswahl zurück, wenn die Zwischenablage ablehnt', async () => {
    vi.stubGlobal('navigator', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) } })
    const doc = stubDom(true)

    expect(await copyText('abc')).toBe(true)
    expect(doc.execCommand).toHaveBeenCalledWith('copy')
  })

  it('meldet Fehler, wenn auch die Auswahl scheitert, und kopiert nichts Leeres', async () => {
    vi.stubGlobal('navigator', {})
    stubDom(false)

    expect(await copyText('abc')).toBe(false)
    expect(await copyText('')).toBe(false)
  })
})
