import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { debounced } from './autosave'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('debounced', () => {
  it('collapses a burst into one call with the last arguments', () => {
    const spy = vi.fn()
    const save = debounced(spy, 600)

    save('a')
    save('b')
    save('c')
    vi.advanceTimersByTime(600)

    expect(spy).toHaveBeenCalledTimes(1)
    expect(spy).toHaveBeenCalledWith('c')
  })

  it('calls again after the window has passed', () => {
    const spy = vi.fn()
    const save = debounced(spy, 600)

    save('a')
    vi.advanceTimersByTime(600)
    save('b')
    vi.advanceTimersByTime(600)

    expect(spy).toHaveBeenCalledTimes(2)
  })

  it('does not call before the window is up', () => {
    const spy = vi.fn()
    debounced(spy, 600)('a')

    vi.advanceTimersByTime(599)

    expect(spy).not.toHaveBeenCalled()
  })
})
