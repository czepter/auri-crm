/** Two letters for the studio's avatar — first letters of the first two words, so "Nordlicht Fotografie" reads NF rather than NO. */
export function initials(name: string | null): string {
  return (name ?? '')
    .split(' ')
    .map((word) => word[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()
}
