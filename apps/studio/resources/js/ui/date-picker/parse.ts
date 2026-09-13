/*
 * Zwischen dem ISO-Tag, den die API spricht, und der deutschen Schreibweise
 * im Feld. Beides hier statt in der Komponente, weil die Fallen (einstellige
 * Tage, der 31. Februar) sonst nur im Browser auffallen.
 */

/** TT.MM.JJJJ oder YYYY-MM-DD zu YYYY-MM-DD. null, solange das nichts Gültiges ist. */
export function toIso(text: string): string | null {
  const value = text.trim()
  const german = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(value)
  const iso = german
    ? `${german[3]}-${german[2].padStart(2, '0')}-${german[1].padStart(2, '0')}`
    : /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null
  if (!iso) return null

  // Date rollt den 31.02. auf den 03.03. weiter, statt aufzugeben.
  const date = new Date(`${iso}T00:00:00`)
  if (Number.isNaN(date.getTime()) || date.getDate() !== Number(iso.slice(8))) return null

  return iso
}

/**
 * Setzt beim Tippen reiner Ziffern die Punkte: 16102026 wird 16.10.2026.
 * Nur solange jeder Punkt hinter zwei Ziffern steht, sonst hat jemand
 * selbst punktiert (4.6.2026) und das bleibt stehen. Ein Punkt am Ende
 * passt nicht aufs Muster, damit "16." beim Weitertippen nicht verschwindet.
 */
export function withDots(text: string): string {
  if (!/^(\d{2}\.){0,2}\d+$/.test(text)) return text
  const digits = text.replaceAll('.', '').slice(0, 8)

  return [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4)].filter(Boolean).join('.')
}

/** YYYY-MM-DD als TT.MM.JJJJ. */
export function toGerman(iso: string): string {
  const [year, month, day] = iso.split('-')

  return `${day}.${month}.${year}`
}
