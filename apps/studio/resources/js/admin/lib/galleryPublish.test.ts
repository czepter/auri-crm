import { describe, expect, it } from 'vitest'
import { publishLabelKey, showPublish, showWithdraw } from './galleryPublish'

describe('publishLabelKey', () => {
  it('offers Veröffentlichen before a first version exists', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: false, published: null })).toBe('galleryRecord.publish')
  })

  it('offers Neu veröffentlichen once a version stands and the draft moved', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: false, published: 1 })).toBe('galleryRecord.republish')
  })

  // A moved draft always wins, even over a withdrawn gallery — one click
  // both republishes and reopens, so Wieder freigeben has nothing to add.
  it('prefers Neu veröffentlichen over Wieder freigeben when both could apply', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: true, published: 1 })).toBe('galleryRecord.republish')
  })

  it('offers Wieder freigeben for an unchanged, withdrawn draft', () => {
    expect(publishLabelKey({ deviates: false, withdrawn: true, published: 1 })).toBe('galleryRecord.reopen')
  })
})

describe('showPublish', () => {
  it('hides the button once the standing version already matches the draft', () => {
    expect(showPublish({ deviates: false, withdrawn: false, published: 1 })).toBe(false)
  })

  it('shows it for a deviating draft', () => {
    expect(showPublish({ deviates: true, withdrawn: false, published: 1 })).toBe(true)
  })

  it('shows it for a withdrawn gallery even without a fresh deviation', () => {
    expect(showPublish({ deviates: false, withdrawn: true, published: 1 })).toBe(true)
  })
})

describe('showWithdraw', () => {
  it('hides the button before a first version exists', () => {
    expect(showWithdraw({ deviates: true, withdrawn: false, published: null })).toBe(false)
  })

  it('hides the button once already withdrawn', () => {
    expect(showWithdraw({ deviates: false, withdrawn: true, published: 1 })).toBe(false)
  })

  it('shows it for a live, published gallery', () => {
    expect(showWithdraw({ deviates: false, withdrawn: false, published: 1 })).toBe(true)
  })
})
