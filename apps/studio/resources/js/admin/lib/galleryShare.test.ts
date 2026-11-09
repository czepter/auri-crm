import { describe, expect, it } from 'vitest'
import { generatePassword, mailText, shareText } from './galleryShare'

describe('generatePassword', () => {
  it('builds Wort-Ziffern from the random source', () => {
    expect(generatePassword(() => 0)).toBe('Wiese-1000')
    expect(generatePassword((max) => max - 1)).toBe('Farn-9999')
  })

  it('passes the password length rule of the server (4 to 64)', () => {
    const password = generatePassword()
    expect(password).toMatch(/^[A-Z][a-z]+-\d{4}$/)
    expect(password.length).toBeGreaterThanOrEqual(4)
  })
})

describe('shareText', () => {
  it('carries greeting, link and password', () => {
    expect(shareText({ name: 'Lena', url: 'https://x.test/a/b', password: 'Wiese-4821' }))
      .toBe('Hallo Lena,\n\neure Bilder sind da: https://x.test/a/b\n\nPasswort: Wiese-4821')
  })

  it('leaves the password line out without one', () => {
    expect(shareText({ name: null, url: 'https://x.test/a/b', password: null })).toBe('Hallo,\n\neure Bilder sind da: https://x.test/a/b')
  })
})

describe('mailText', () => {
  it('greets by name', () => {
    expect(mailText('Lena')).toBe('Hallo Lena,\n\neure Bilder sind da.')
  })
})
