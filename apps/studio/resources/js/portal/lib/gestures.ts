/** Fires after `ms` of holding still; moving past `tolerance` px cancels. */
export function longPress(fire: () => void, ms = 550, tolerance = 8) {
  let timer: ReturnType<typeof setTimeout> | undefined
  let origin = { x: 0, y: 0 }

  function cancel() {
    clearTimeout(timer)
    timer = undefined
  }

  return {
    start(x: number, y: number) {
      cancel()
      origin = { x, y }
      timer = setTimeout(() => {
        timer = undefined
        fire()
      }, ms)
    },
    move(x: number, y: number) {
      if (Math.hypot(x - origin.x, y - origin.y) > tolerance) cancel()
    },
    cancel,
  }
}

/**
 * Feeds scroll positions, answers whether the bar is hidden. Travel is summed
 * per direction so a jitter of a few pixels neither hides nor reveals it.
 */
export function hideOnScroll(hideAfter = 14, revealAfter = 10, topGuard = 24) {
  let last = 0
  let travel = 0
  let hidden = false

  return function update(y: number): boolean {
    const delta = y - last
    last = y

    if (y <= topGuard) {
      travel = 0
      hidden = false

      return hidden
    }

    if (delta === 0) return hidden

    travel = Math.sign(travel) === Math.sign(delta) ? travel + delta : delta

    if (travel >= hideAfter) hidden = true
    else if (-travel >= revealAfter) hidden = false

    return hidden
  }
}
