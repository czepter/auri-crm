export type Entry = { id: string; title: string }

// Subsequence match with bonuses for word starts and consecutive characters.
export function score(title: string, query: string): number | null {
  const text = title.toLocaleLowerCase("de")
  const needle = query.trim().toLocaleLowerCase("de")
  let at = 0
  let total = 0
  let previous = -2

  for (const char of needle) {
    const found = text.indexOf(char, at)
    if (found < 0) return null

    total += 1
    if (found === previous + 1) total += 3
    if (found === 0 || text[found - 1] === " ") total += 4
    previous = found
    at = found + 1
  }

  return total - text.length * 0.05
}

// Rows the server matched on something other than the title (an e-mail
// address) stay, ranked below every title match. Commands come first when
// there is no query; a command whose title another command already took
// (the page's own "Neues Angebot" beside the global one) is dropped.
export function rank<T extends Entry>(commands: T[], recent: T[], found: T[], query: string): T[] {
  const unique = commands.filter((command, at) => commands.findIndex((other) => other.title === command.title) === at)

  if (!query.trim()) return [...unique, ...recent]

  const rows = new Map<string, { entry: T; value: number }>()

  for (const entry of [...unique, ...recent]) {
    const value = score(entry.title, query)
    if (value !== null) rows.set(entry.id, { entry, value })
  }

  for (const entry of found) {
    rows.set(entry.id, { entry, value: score(entry.title, query) ?? -1000 })
  }

  return [...rows.values()].sort((a, b) => b.value - a.value).map((row) => row.entry)
}
