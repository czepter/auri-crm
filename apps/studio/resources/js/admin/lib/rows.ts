/**
 * Identität statt Kopie, wenn nichts passiert: der Aufrufer schreibt das
 * Ergebnis in ein Model und würde sonst einen Autosave für eine Bewegung
 * auslösen, die es nicht gab.
 */
export function moveRow<T>(rows: T[], from: number, to: number): T[] {
  if (to < 0 || to >= rows.length || from === to) {
    return rows
  }

  const next = [...rows]
  next.splice(to, 0, ...next.splice(from, 1))

  return next
}
