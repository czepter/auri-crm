import { afterEach, describe, expect, it, vi } from 'vitest'
import { hideOnScroll, longPress } from './gestures'

afterEach(() => vi.useRealTimers())

describe('longPress', () => {
  it('fires after the hold and not when the finger drifts', () => {
    vi.useFakeTimers()
    const fire = vi.fn()
    const press = longPress(fire)

    press.start(0, 0)
    vi.advanceTimersByTime(550)
    expect(fire).toHaveBeenCalledTimes(1)

    press.start(0, 0)
    press.move(0, 20)
    vi.advanceTimersByTime(600)
    expect(fire).toHaveBeenCalledTimes(1)
  })
})

describe('hideOnScroll', () => {
  it('hides after travelling down, reveals on travelling up, stays shown at the top', () => {
    const update = hideOnScroll()

    expect(update(10)).toBe(false)
    expect(update(100)).toBe(true)
    expect(update(95)).toBe(true)
    expect(update(85)).toBe(false)
    expect(update(200)).toBe(true)
    expect(update(5)).toBe(false)
  })
})
