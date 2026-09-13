import { matched } from '@/admin/lib/insert'
import { centsToEuros, eurosToCents, formatEurosRounded } from '@/admin/lib/money'

export type PackageItemRow = {
  // Leer bei einer neuen Zeile: das Bild haengt an einer Zeile, die es in
  // der Datenbank schon gibt, also erst nach dem ersten Speichern.
  id?: string
  title: string
  description: string
  quantity: number
  unitPrice: string
  selectable: boolean
  selected: boolean
  hasImage: boolean
}

export type PackageInsertOption = { kind: 'item' | 'new'; id: string; label: string; meta: string }

export function packageInsertOptions(term: string, items: App.Catalog.Data.CatalogItemData[]): PackageInsertOption[] {
  const needle = term.trim().toLowerCase()

  if (needle === '') {
    return []
  }

  return [
    ...matched(items, (item) => item.title, needle).map((item): PackageInsertOption => ({
      kind: 'item',
      id: item.id,
      label: item.title,
      meta: item.unitPrice === null ? '' : formatEurosRounded(item.unitPrice),
    })),
    // Steht auch bei Treffern da: derselbe Titel kann eine andere Leistung meinen.
    { kind: 'new', id: 'new', label: term.trim(), meta: '' },
  ]
}

/** Eine Zeile aus dem Katalog ist per Definition gemerkt. */
export function catalogItemRow(item: App.Catalog.Data.CatalogItemData): PackageItemRow {
  return {
    title: item.title,
    description: item.description ?? '',
    quantity: 1,
    unitPrice: centsToEuros(item.unitPrice),
    selectable: false,
    selected: false,
    hasImage: false,
  }
}

export function newPackageItemRow(title: string): PackageItemRow {
  return { title, description: '', quantity: 1, unitPrice: '', selectable: false, selected: false, hasImage: false }
}

export function lineTotal(row: PackageItemRow): number {
  return (eurosToCents(row.unitPrice) ?? 0) * row.quantity
}
