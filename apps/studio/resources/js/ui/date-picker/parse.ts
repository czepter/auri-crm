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

/** YYYY-MM-DD als TT.MM.JJJJ. */
export function toGerman(iso: string): string {
  const [year, month, day] = iso.split('-')

  return `${day}.${month}.${year}`
}
