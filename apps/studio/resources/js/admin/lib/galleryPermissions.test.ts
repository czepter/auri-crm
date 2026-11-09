import { describe, expect, it } from 'vitest'
import { offered, withPermission, type Permissions } from './galleryPermissions'

const base: Permissions = {
  client: { favorites: true, comments: true, hide: true, share: true },
  guest: { favorites: true, comments: false, hide: false, share: true },
}

describe('galleryPermissions', () => {
  it('offers hiding to the client only', () => {
    expect(offered('client', 'hide')).toBe(true)
    expect(offered('guest', 'hide')).toBe(false)
    expect(offered('guest', 'comments')).toBe(true)
  })

  it('changes one cell and leaves the original alone', () => {
    const next = withPermission(base, 'guest', 'comments', true)

    expect(next.guest.comments).toBe(true)
    expect(next.client).toEqual(base.client)
    expect(base.guest.comments).toBe(false)
  })
})
