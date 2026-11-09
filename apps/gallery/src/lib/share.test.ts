import { describe, expect, it, vi } from 'vitest'
import { shareLink } from './share'

describe('shareLink', () => {
  it('uses the share sheet when the browser has one', async () => {
    const share = vi.fn().mockResolvedValue(undefined)

    expect(await shareLink('https://x.test/g', 'Hochzeit', { share, clipboard: {} } as unknown as Navigator)).toBe('shared')
    expect(share).toHaveBeenCalledWith({ url: 'https://x.test/g', title: 'Hochzeit' })
  })

  it('copies the link otherwise and reports a refusal', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)

    expect(await shareLink('u', 't', { clipboard: { writeText } } as unknown as Navigator)).toBe('copied')
    expect(await shareLink('u', 't', { clipboard: { writeText: vi.fn().mockRejectedValue(new Error('no')) } } as unknown as Navigator)).toBe('failed')
  })
})
