import { describe, expect, it, vi } from 'vitest'
import { favoriteSync, keyIntent, stepIndex, swipeIntent } from './lightbox'

describe('keyIntent', () => {
  it('closes on Escape even while the comment field has focus', () => {
    expect(keyIntent('Escape', true)).toBe('close')
  })

  it('steps on the arrows when nothing is being typed', () => {
    expect(keyIntent('ArrowLeft', false)).toBe('prev')
    expect(keyIntent('ArrowRight', false)).toBe('next')
  })

  it('leaves the arrows to the comment field while it has focus', () => {
    expect(keyIntent('ArrowLeft', true)).toBeNull()
    expect(keyIntent('ArrowRight', true)).toBeNull()
  })

  it('ignores every other key', () => {
    expect(keyIntent('a', false)).toBeNull()
    expect(keyIntent('Enter', false)).toBeNull()
  })
})

describe('stepIndex', () => {
  it('wraps past the last picture to the first', () => {
    expect(stepIndex(3, 1, 4)).toBe(0)
  })

  it('wraps before the first picture to the last', () => {
    expect(stepIndex(0, -1, 4)).toBe(3)
  })

  it('moves by one in between', () => {
    expect(stepIndex(1, 1, 4)).toBe(2)
    expect(stepIndex(1, -1, 4)).toBe(0)
  })
})

describe('swipeIntent', () => {
  it('steps on a long horizontal move', () => {
    expect(swipeIntent(-80, 5)).toBe('next')
    expect(swipeIntent(80, 5)).toBe('prev')
  })

  it('ignores short or mostly vertical moves', () => {
    expect(swipeIntent(-20, 0)).toBeNull()
    expect(swipeIntent(-60, 60)).toBeNull()
  })
})

describe('favoriteSync', () => {
  it('sends one toggle per settled intent when taps overlap', async () => {
    let server = false
    const send = vi.fn(async () => (server = !server))
    const request = favoriteSync(send)

    const first = request('a', true, false)
    request('a', false, false)
    request('a', true, false)

    expect(await first).toBe(true)
    expect(server).toBe(true)
    expect(send).toHaveBeenCalledTimes(1)
  })

  it('settles on the server state when the request fails', async () => {
    const request = favoriteSync(async () => null)

    expect(await request('a', true, false)).toBe(false)
  })
})
