/*
 * Zwischen der Zeit, die die API spricht ("HH:MM", aus Postgres auch
 * "HH:MM:SS"), und dem Time-Objekt des Segmentfelds.
 */
import { Time } from '@internationalized/date'

/** "HH:MM" oder "HH:MM:SS" zu Time. undefined, solange das nichts Gültiges ist. */
export function toTime(text: string | null | undefined): Time | undefined {
  const match = /^(\d{1,2}):(\d{2})/.exec((text ?? '').trim())
  if (!match) return undefined

  const hour = Number(match[1])
  const minute = Number(match[2])
  if (hour > 23 || minute > 59) return undefined

  return new Time(hour, minute)
}

/** Time als "HH:MM". Leer, solange nichts gewählt ist. */
export function toClock(time: { hour: number, minute: number } | null | undefined): string {
  if (!time) return ''

  return `${String(time.hour).padStart(2, '0')}:${String(time.minute).padStart(2, '0')}`
}
