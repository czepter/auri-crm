export type Rule = 'iban' | 'bic' | 'vatId' | 'taxNumber' | 'color'

// Spiegel von App\Settings\Rules\ValidIban: ISO 7064 MOD 97-10.
function ibanValid(value: string): boolean {
  const iban = value.replace(/\s/g, '').toUpperCase()

  if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/.test(iban)) return false

  const numeric = [...iban.slice(4), ...iban.slice(0, 4)]
    .map((char) => (/\d/.test(char) ? char : String(char.charCodeAt(0) - 55)))
    .join('')

  return [...numeric].reduce((rest, digit) => (rest * 10 + Number(digit)) % 97, 0) === 1
}

const checks: Record<Rule, (value: string) => boolean> = {
  iban: ibanValid,
  bic: (value) => /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/.test(value.replace(/\s/g, '').toUpperCase()),
  // Länderkennung plus 2 bis 12 Zeichen; DE hat genau neun Ziffern.
  vatId: (value) => {
    const id = value.replace(/[\s.-]/g, '').toUpperCase()

    return id.startsWith('DE') ? /^DE[0-9]{9}$/.test(id) : /^[A-Z]{2}[A-Z0-9]{2,12}$/.test(id)
  },
  // 10 bis 13 Ziffern, Schrägstriche und Leerzeichen sind Trenner (Landesformate und ELSTER).
  taxNumber: (value) => /^[0-9/\s]+$/.test(value) && /^\d{10,13}$/.test(value.replace(/[/\s]/g, '')),
  color: (value) => /^#[0-9A-Fa-f]{6}$/.test(value),
}

/** Leer ist gültig: Pflicht ist Sache des Formulars. */
export function ruleValid(rule: Rule, value: unknown): boolean {
  const text = String(value ?? '').trim()

  return text === '' || checks[rule](text)
}
