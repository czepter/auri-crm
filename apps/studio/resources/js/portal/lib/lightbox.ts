export type KeyIntent = 'close' | 'prev' | 'next' | null

/**
 * Escape always closes; the arrows belong to the comment field while it holds
 * focus. Without the split, moving the caret back over a typo steps to the
 * next picture, and sendComment() then files the text under that one.
 */
export function keyIntent(key: string, typing: boolean): KeyIntent {
  if (key === 'Escape') return 'close'
  if (typing) return null
  if (key === 'ArrowLeft') return 'prev'
  if (key === 'ArrowRight') return 'next'

  return null
}

/** Both ends wrap: the last picture's next is the first. */
export function stepIndex(index: number, delta: number, length: number): number {
  return (index + delta + length) % length
}
