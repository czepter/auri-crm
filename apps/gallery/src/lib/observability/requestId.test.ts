import { describe, expect, it } from 'vitest'
import { resolveRequestId } from './requestId'

describe('resolveRequestId', () => {
  it('accepts the id the edge already sent', () => {
    expect(resolveRequestId('edge-supplied-id')).toBe('edge-supplied-id')
  })

  it('mints a fresh id when the caller sent none', () => {
    expect(resolveRequestId(null)).toMatch(/^[0-9a-f-]{36}$/)
    expect(resolveRequestId(undefined)).toMatch(/^[0-9a-f-]{36}$/)
  })

  it('mints a fresh id when the header is present but blank', () => {
    expect(resolveRequestId('   ')).toMatch(/^[0-9a-f-]{36}$/)
  })

  it('never mints the same id twice', () => {
    expect(resolveRequestId(null)).not.toBe(resolveRequestId(null))
  })
})
