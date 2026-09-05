import { toIso } from '@/ui/date-picker/parse'

export type ExpiryDecision = { save: false } | { save: true; value: string | null }

/**
 * What DatePicker's own `blur` emit (the raw text, not the parsed value —
 * see its own doc comment) means for expires_at, decided here rather than
 * assumed: toIso() alone cannot tell "" (backspaced clear, meaning "no
 * expiry") from "31.12.20" (still mid-entry) apart — both parse to null.
 * Working from the raw text is what makes that distinction possible.
 *
 * - "" → a deliberate clear: save null.
 * - a complete date → save it.
 * - anything else (half-typed) → don't save. DatePicker's own onBlur has
 *   already reverted the field's displayed text to the last committed
 *   value by the time this runs.
 */
export function decideExpiryOnBlur(text: string): ExpiryDecision {
  if (text === '') return { save: true, value: null }

  const iso = toIso(text)

  return iso === null ? { save: false } : { save: true, value: iso }
}
