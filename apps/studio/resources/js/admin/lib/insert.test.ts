import { describe, expect, it } from 'vitest'
import { insertOptions, itemRow, packageRows } from './insert'

const pkg = (name: string, items: { title: string; unitPrice: number | null; quantity?: number }[]) =>
  ({
    id: `p-${name}`,
    name,
    description: null,
    isActive: true,
    itemCount: items.length,
    items: items.map((item, index) => ({
      id: `pi-${index}`,
      title: item.title,
      description: null,
      quantity: item.quantity ?? 1,
      unitPrice: item.unitPrice,
    })),
  }) as unknown as App.Catalog.Data.PackageData

const item = (title: string, unitPrice: number | null) =>
  ({ id: `c-${title}`, title, description: null, unitPrice }) as App.Catalog.Data.CatalogItemData

describe('insertOptions', () => {
  it('ranks a match at the start of the title above one in the middle', () => {
    const result = insertOptions('foto', [], [item('Zweitfotograf', 49000), item('Fotobuch', 38000)])

    expect(result.filter((option) => option.kind === 'item').map((option) => option.label))
      .toEqual(['Fotobuch', 'Zweitfotograf'])
  })

  it('ignores case and matches packages as well as items', () => {
    const result = insertOptions(
      'ALB',
      [pkg('Album-Paket', [{ title: 'Buch', unitPrice: 38000 }])],
      [item('Album gross', 21000)],
    )

    expect(result.map((option) => option.kind)).toContain('package')
    expect(result.map((option) => option.kind)).toContain('item')
  })

  it('always offers the typed term as a new line, even when something matches', () => {
    const result = insertOptions('Fotobuch', [], [item('Fotobuch', 38000)])

    expect(result.filter((option) => option.kind === 'new')).toHaveLength(1)
  })

  it('offers nothing at all for an empty term', () => {
    expect(insertOptions('', [pkg('Album-Paket', [])], [item('Fotobuch', 38000)])).toEqual([])
  })
})

describe('packageRows', () => {
  it('copies every line of a package, keeping order and the included marker', () => {
    const rows = packageRows(
      pkg('Basis', [
        { title: 'Reportage', unitPrice: 189000, quantity: 1 },
        { title: 'Anfahrt', unitPrice: null },
      ]),
    )

    expect(rows.map((row) => row.title)).toEqual(['Reportage', 'Anfahrt'])
    expect(rows[0].unitPrice).toBe('1890.00')
    expect(rows[1].unitPrice).toBe('')
    expect(rows[0].remember).toBe(false)
  })
})

describe('itemRow', () => {
  it('turns a library line into a remembered row of quantity one', () => {
    expect(itemRow(item('Zweitfotograf', 49000))).toEqual({
      title: 'Zweitfotograf',
      description: '',
      quantity: 1,
      unitPrice: '490.00',
      discount: '',
      remember: true,
    })
  })
})
