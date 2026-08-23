export interface PublishState {
  deviates: boolean
  withdrawn: boolean
  published: number | null
}

/**
 * The spec's Veröffentlichen section: one button, whose label follows the
 * deviation. A moved draft always wins the label, even over a withdrawn
 * gallery — publishing it both republishes and reopens in one step, so
 * there is nothing left for Wieder freigeben to say.
 */
export function publishLabelKey(state: PublishState): 'galleryRecord.publish' | 'galleryRecord.republish' | 'galleryRecord.reopen' {
  if (state.deviates) {
    return state.published === null ? 'galleryRecord.publish' : 'galleryRecord.republish'
  }

  return 'galleryRecord.reopen'
}

export function showPublish(state: PublishState): boolean {
  return state.deviates || state.withdrawn
}

/** Nothing to take back before a first version, or once one is already withdrawn. */
export function showWithdraw(state: PublishState): boolean {
  return state.published !== null && !state.withdrawn
}
