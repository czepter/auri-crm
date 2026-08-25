import { describe, expect, it } from 'vitest'
import { isCompleteDate } from './galleryExpiry'

describe('isCompleteDate', () => {
  it('rejects the empty string DatePicker emits mid-type', () => {
    expect(isCompleteDate('')).toBe(false)
  })

  it('accepts a parsed ISO date', () => {
    expect(isCompleteDate('2027-12-31')).toBe(true)
  })
})
