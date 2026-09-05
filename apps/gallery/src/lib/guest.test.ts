import { describe, expect, it } from 'vitest'
import { nameFromEmail } from './guest'

describe('nameFromEmail', () => {
  it('makes a name out of the part before the at sign', () => {
    expect(nameFromEmail('anna.mueller@example.com')).toBe('Anna Mueller')
    expect(nameFromEmail('anna_mueller@example.com')).toBe('Anna Mueller')
    expect(nameFromEmail('anna-mueller@example.com')).toBe('Anna Mueller')
  })

  it('drops the digits somebody hung on their address in 1987', () => {
    expect(nameFromEmail('anna.mueller1987@example.com')).toBe('Anna Mueller')
    expect(nameFromEmail('anna87@example.com')).toBe('Anna')
  })

  it('answers with nothing where there is nothing to suggest', () => {
    expect(nameFromEmail('1987@example.com')).toBe('')
    expect(nameFromEmail('')).toBe('')
  })
})
