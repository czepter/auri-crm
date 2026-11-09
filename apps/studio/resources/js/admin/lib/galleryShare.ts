const WORDS = ['Wiese', 'Birke', 'Sonne', 'Fluss', 'Mohn', 'Linde', 'Hafen', 'Klee', 'Nebel', 'Salbei', 'Tulpe', 'Lerche', 'Heide', 'Brise', 'Moos', 'Farn']

function randomInt(max: number): number {
  const buffer = new Uint32Array(1)
  crypto.getRandomValues(buffer)

  return buffer[0] % max
}

/** Lesbar am Telefon diktierbar: ein Wort, ein Bindestrich, vier Ziffern. */
export function generatePassword(random: (max: number) => number = randomInt): string {
  return `${WORDS[random(WORDS.length)]}-${1000 + random(9000)}`
}

const greeting = (name: string | null) => (name ? `Hallo ${name},` : 'Hallo,')

export function mailText(name: string | null): string {
  return `${greeting(name)}\n\neure Bilder sind da.`
}

export function shareText({ name, url, password }: { name: string | null; url: string; password: string | null }): string {
  const lines = [greeting(name), '', `eure Bilder sind da: ${url}`]
  if (password) lines.push('', `Passwort: ${password}`)

  return lines.join('\n')
}
