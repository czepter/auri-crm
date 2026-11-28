import { isoDate } from '@/admin/lib/dates'

const WEEKDAYS = ['sonntag', 'montag', 'dienstag', 'mittwoch', 'donnerstag', 'freitag', 'samstag']
// "so" and "do" are words of their own, so the short forms only count with their period.
const SHORT: Record<string, number> = { 'mo.': 1, 'di.': 2, 'mi.': 3, 'do.': 4, 'fr.': 5, 'sa.': 6, 'so.': 0 }

const TAIL = /^(.*\S)\s+(heute|morgen|[a-zäöü]+\.?|(\d{1,2})\.(\d{1,2})\.(\d{4})?)$/i

function inDays(from: Date, days: number): string {
  const date = new Date(from.getFullYear(), from.getMonth(), from.getDate() + days)

  return isoDate(date)
}

/** Ends the title on a day word or date: that becomes the due date, the rest stays the title. */
export function parseQuickAdd(input: string, from = new Date()): { title: string; dueOn: string | null } {
  const raw = input.trim()
  const match = TAIL.exec(raw)
  if (!match) return { title: input, dueOn: null }

  const [, title, word, day, month, year] = match
  const key = word.toLowerCase()

  if (day !== undefined) {
    const at = (y: number) => new Date(y, Number(month) - 1, Number(day))
    const valid = (date: Date) => date.getDate() === Number(day) && date.getMonth() === Number(month) - 1

    if (year !== undefined) return valid(at(Number(year))) ? { title, dueOn: isoDate(at(Number(year))) } : { title: input, dueOn: null }

    const today = new Date(from.getFullYear(), from.getMonth(), from.getDate())
    const date = at(today.getFullYear()) < today ? at(today.getFullYear() + 1) : at(today.getFullYear())

    return valid(date) ? { title, dueOn: isoDate(date) } : { title: input, dueOn: null }
  }

  if (key === 'heute') return { title, dueOn: inDays(from, 0) }
  if (key === 'morgen') return { title, dueOn: inDays(from, 1) }

  const weekday = WEEKDAYS.indexOf(key) >= 0 ? WEEKDAYS.indexOf(key) : SHORT[key]
  if (weekday === undefined) return { title: input, dueOn: null }

  return { title, dueOn: inDays(from, ((weekday - from.getDay() + 6) % 7) + 1) }
}
