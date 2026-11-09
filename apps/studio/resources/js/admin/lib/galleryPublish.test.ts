import { describe, expect, it } from 'vitest'
import { publishLabelKey, showPublish, showWithdraw, stateKey } from './galleryPublish'

describe('publishLabelKey', () => {
  it('offers Veröffentlichen before a first version exists', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: false, published: null, assets: 3 })).toBe('galleryRecord.publish')
  })

  it('offers Neu veröffentlichen once a version stands and the draft moved', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: false, published: 1, assets: 3 })).toBe('galleryRecord.republish')
  })

  // A moved draft always wins, even over a withdrawn gallery — one click
  // both republishes and reopens, so Wieder freigeben has nothing to add.
  it('prefers Neu veröffentlichen over Wieder freigeben when both could apply', () => {
    expect(publishLabelKey({ deviates: true, withdrawn: true, published: 1, assets: 3 })).toBe('galleryRecord.republish')
  })

  it('offers Wieder freigeben for an unchanged, withdrawn draft', () => {
    expect(publishLabelKey({ deviates: false, withdrawn: true, published: 1, assets: 3 })).toBe('galleryRecord.reopen')
  })
})

describe('showPublish', () => {
  it('hides the button once the standing version already matches the draft', () => {
    expect(showPublish({ deviates: false, withdrawn: false, published: 1, assets: 3 })).toBe(false)
  })

  it('shows it for a deviating draft', () => {
    expect(showPublish({ deviates: true, withdrawn: false, published: 1, assets: 3 })).toBe(true)
  })

  it('shows it for a withdrawn gallery even without a fresh deviation', () => {
    expect(showPublish({ deviates: false, withdrawn: true, published: 1, assets: 3 })).toBe(true)
  })

  // A control that cannot succeed must not be offered — PublishGallery
  // refuses an empty draft outright, and a fresh gallery with no pictures
  // yet always deviates (no version can match nothing), so this is the
  // very first thing a studio would hit on a new gallery.
  it('hides the button for an empty draft even though it deviates', () => {
    expect(showPublish({ deviates: true, withdrawn: false, published: null, assets: 0 })).toBe(false)
  })
})

describe('showWithdraw', () => {
  it('hides the button before a first version exists', () => {
    expect(showWithdraw({ deviates: true, withdrawn: false, published: null, assets: 3 })).toBe(false)
  })

  it('hides the button once already withdrawn', () => {
    expect(showWithdraw({ deviates: false, withdrawn: true, published: 1, assets: 3 })).toBe(false)
  })

  it('shows it for a live, published gallery', () => {
    expect(showWithdraw({ deviates: false, withdrawn: false, published: 1, assets: 3 })).toBe(true)
  })
})

describe('stateKey', () => {
  it('names the three states the head can show', () => {
    expect(stateKey({ deviates: true, withdrawn: false, published: null, assets: 3 })).toBe('draft')
    expect(stateKey({ deviates: true, withdrawn: false, published: 1, assets: 3 })).toBe('online')
    expect(stateKey({ deviates: false, withdrawn: true, published: 1, assets: 3 })).toBe('withdrawn')
  })
})
