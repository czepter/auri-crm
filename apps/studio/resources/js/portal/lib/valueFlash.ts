export type FlashDirection = 'up' | 'down'

export function flashDirection(previous: number, next: number): FlashDirection | null {
  if (next === previous) return null

  return next > previous ? 'up' : 'down'
}
