// Die Druckform nach DIN 5008: Vierergruppen, Großbuchstaben. So steht die
// IBAN auch auf Rechnung und im Portal, beide zeigen den gespeicherten Wert.
export function groupIban(value: string): string {
  return value.replace(/\s/g, '').toUpperCase().replace(/(.{4})(?=.)/g, '$1 ')
}

// Wo der Cursor in der gruppierten Form steht, nachdem `chars` Zeichen ohne
// Leerzeichen vor ihm lagen: eins weiter für jede volle Gruppe davor.
export function ibanCaret(chars: number): number {
  return chars + Math.floor(Math.max(chars - 1, 0) / 4)
}
