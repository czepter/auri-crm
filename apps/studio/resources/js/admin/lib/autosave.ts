/**
 * Der Autosave feuert beim Verlassen jeder Zelle. Ohne Bündelung wäre das ein
 * Request je Tastaturwechsel — mit ihr einer je Denkpause.
 */
export function debounced<T extends unknown[]>(fn: (...args: T) => void, delay: number) {
  let timer: ReturnType<typeof setTimeout> | undefined

  const call = (...args: T) => {
    clearTimeout(timer)
    timer = setTimeout(() => fn(...args), delay)
  }

  call.cancel = () => clearTimeout(timer)

  return call
}

/** Keys whose current value differs from the last saved one. */
export function changedKeys<T extends object>(saved: T, current: T): (keyof T)[] {
  return (Object.keys(current) as (keyof T)[]).filter(
    (key) => JSON.stringify(saved[key]) !== JSON.stringify(current[key]),
  )
}

export type SaveState = 'saved' | 'saving' | 'dirty' | 'error'

type Saveable = { processing: boolean; isDirty: boolean; hasErrors: boolean }

/**
 * One indicator for every settings form. Inertia forms carry the three facts
 * the head needs, so the state is read off them, never kept beside them.
 */
export function saveState(forms: (Saveable | null | undefined)[]): SaveState {
  const live = forms.filter((form): form is Saveable => !!form)

  if (live.some((form) => form.processing)) return 'saving'
  if (live.some((form) => form.hasErrors)) return 'error'
  if (live.some((form) => form.isDirty)) return 'dirty'

  return 'saved'
}
