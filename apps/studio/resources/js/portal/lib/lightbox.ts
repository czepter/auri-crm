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

export type SwipeIntent = 'prev' | 'next' | null

/** Horizontal enough to be a swipe; anything steeper is a scroll. */
export function swipeIntent(dx: number, dy: number, min = 48): SwipeIntent {
  if (Math.abs(dx) < min || Math.abs(dx) < Math.abs(dy) * 1.5) return null

  return dx < 0 ? 'next' : 'prev'
}

/**
 * The favorite endpoint toggles, so overlapping taps would flip it back and
 * forth. One request per key is in flight; the latest wanted state decides
 * whether another follows. A failed request settles on the last server state.
 */
export function favoriteSync(send: (key: string) => Promise<boolean | null>) {
  const wanted = new Map<string, boolean>()
  const busy = new Set<string>()

  return async function request(key: string, want: boolean, server: boolean): Promise<boolean | null> {
    wanted.set(key, want)

    if (busy.has(key)) return null

    busy.add(key)

    try {
      while (server !== wanted.get(key)) {
        const next = await send(key)

        if (next === null) break

        server = next
      }
    } finally {
      busy.delete(key)
    }

    return server
  }
}
