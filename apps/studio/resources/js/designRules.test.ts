import { expect, it } from 'vitest'
import de from './locales/de.json'

const sources = import.meta.glob<string>(
  ['./{admin,portal,ui}/**/*.{vue,ts}', '!./**/*.test.ts', '!./**/*.d.ts'],
  { query: '?raw', import: 'default', eager: true },
)

const lines = Object.entries(sources).flatMap(([path, source]) =>
  source.split('\n').map((text, index) => ({ path: path.slice(2), where: `${path.slice(2)}:${index + 1}`, text })),
)

const offenders = (pattern: RegExp, only?: (path: string) => boolean) =>
  lines.filter(({ path, text }) => (only?.(path) ?? true) && pattern.test(text)).map(({ where, text }) => `${where}: ${text.trim()}`)

it('has no shadows: surfaces take a border', () => {
  expect(offenders(/(?<![\w-])(?:[\w[\]&:>*()-]+:)*shadow-(?!none\b)/)).toEqual([])
})

it('formats dates through the date helpers', () => {
  expect(offenders(/toISOString\(\)|toLocaleDateString\(/, (path) => path.endsWith('.vue'))).toEqual([])
})

it('uses translation keys that exist in de.json', () => {
  const has = (key: string) => key.split('.').reduce<unknown>((node, part) => (node as Record<string, unknown> | undefined)?.[part], de) !== undefined
  const missing = lines.flatMap(({ where, text }) =>
    [...text.matchAll(/(?<![\w.])\$?t\(\s*(['"])([A-Za-z][\w-]*(?:\.[\w-]+)+)\1/g)]
      .filter(([, , key]) => !has(key!))
      .map(([, , key]) => `${where}: ${key}`),
  )
  expect(missing).toEqual([])
})

// Ratchet: sizes off the type scale (text-small/ui/body/title/heading) whose pixel value or line height would change the look. Lower on removal, never raise.
const FONT_PX = { admin: 51, portal: 274, ui: 8 }

it.each(Object.entries(FONT_PX))('keeps arbitrary font sizes in %s at or below %i', (area, limit) => {
  const found = lines
    .filter(({ path }) => path.startsWith(`${area}/`))
    .flatMap(({ where, text }) => [...text.matchAll(/(?<![\w-])text-\[\d+(?:\.\d+)?px\]/g)].map(() => where))
  expect(found.length, found.slice(0, 20).join('\n')).toBeLessThanOrEqual(limit)
})
