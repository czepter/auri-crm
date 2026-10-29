import { expect, it } from 'vitest'

const sources = import.meta.glob<string>('./**/*.{ts,vue}', { query: '?raw', import: 'default', eager: true })

it('imports nothing from the admin tree', () => {
  const offenders = Object.entries(sources)
    .filter(([, source]) => /(from|import)\s*\(?\s*['"]@\/admin\//.test(source))
    .map(([file]) => file)

  expect(offenders).toEqual([])
})
