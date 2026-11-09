import { describe, expect, it, vi } from 'vitest'
import { shareLink } from './share'

describe('shareLink', () => {
  it('uses the share sheet when the browser has one', async () => {
    const share = vi.fn().mockResolvedValue(undefined)

    expect(await shareLink('https://x.test/g', 'Hochzeit', { share, clipboard: {} } as unknown as Navigator)).toBe('shared')
    expect(share).toHaveBeenCalledWith({ url: 'https://x.test/g', title: 'Hochzeit' })
  })

  it('copies the link otherwise', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)

    expect(await shareLink('https://x.test/g', null, { clipboard: { writeText } } as unknown as Navigator)).toBe('copied')
    expect(writeText).toHaveBeenCalledWith('https://x.test/g')
  })

  it('reports a refused clipboard, not a dismissed sheet', async () => {
    const abort = new DOMException('', 'AbortError')

    expect(await shareLink('u', null, { share: vi.fn().mockRejectedValue(abort), clipboard: {} } as unknown as Navigator)).toBe('shared')
    expect(await shareLink('u', null, { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('no')) } } as unknown as Navigator)).toBe('failed')
  })
})
