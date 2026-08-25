import { describe, expect, it } from 'vitest'
import { decideExpiryOnBlur } from './galleryExpiry'

describe('decideExpiryOnBlur', () => {
  // The regression: this used to be indistinguishable from a half-typed
  // date (both parsed to null) and silently saved nothing at all.
  it('saves null for a deliberately cleared field', () => {
    expect(decideExpiryOnBlur('')).toEqual({ save: true, value: null })
  })

  it('saves the parsed date once a complete one is typed', () => {
    expect(decideExpiryOnBlur('31.12.2027')).toEqual({ save: true, value: '2027-12-31' })
  })

  // The bug fixed twice now, in both directions: mid-typing must neither
  // clear the field (round 1) nor silently do nothing forever (this would
  // be fine on its own — but only because DatePicker's own onBlur reverts
  // the visible text to what the server holds; a caller relying on this
  // without that revert would leave a half-typed date on screen forever).
  it('saves nothing for a half-typed date', () => {
    expect(decideExpiryOnBlur('31.12.20')).toEqual({ save: false })
  })
})
