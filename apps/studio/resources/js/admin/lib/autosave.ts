/**
 * Der Autosave feuert beim Verlassen jeder Zelle. Ohne Bündelung wäre das ein
 * Request je Tastaturwechsel — mit ihr einer je Denkpause.
 */
export function debounced<T extends unknown[]>(fn: (...args: T) => void, delay: number): (...args: T) => void {
  let timer: ReturnType<typeof setTimeout> | undefined

  return (...args: T) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }
}
