import type { QuoteRow } from '@/admin/components/quote/quotePayload'
import { centsToEuros, formatEurosRounded } from '@/admin/lib/money'

export type InsertOption = {
  kind: 'package' | 'item' | 'new'
  id: string
  label: string
  meta: string
}

/** Treffer am Wortanfang vor Treffer in der Mitte, sonst kein Rang. */
function rank(title: string, term: string): number {
  const index = title.toLowerCase().indexOf(term)

  return index < 0 ? Number.POSITIVE_INFINITY : index
}

function packageTotal(pkg: App.Catalog.Data.PackageData): number {
  return pkg.items.reduce((sum, item) => sum + (item.unitPrice ?? 0) * item.quantity, 0)
}

export function matched<T>(rows: T[], title: (row: T) => string, needle: string): T[] {
  return rows
    .map((row) => ({ row, at: rank(title(row), needle) }))
    .filter((entry) => entry.at !== Number.POSITIVE_INFINITY)
    .sort((a, b) => a.at - b.at)
    .map((entry) => entry.row)
}

export function insertOptions(
  term: string,
  packages: App.Catalog.Data.PackageData[],
  items: App.Catalog.Data.CatalogItemData[],
): InsertOption[] {
  const needle = term.trim().toLowerCase()

  if (needle === '') {
    return []
  }

  return [
    ...matched(packages, (pkg) => pkg.name, needle).map((pkg): InsertOption => ({
      kind: 'package',
      id: pkg.id,
      label: pkg.name,
      meta: `${pkg.itemCount} · ${formatEurosRounded(packageTotal(pkg))}`,
    })),
    ...matched(items, (item) => item.title, needle).map((item): InsertOption => ({
      kind: 'item',
      id: item.id,
      label: item.title,
      meta: item.unitPrice === null ? '' : formatEurosRounded(item.unitPrice),
    })),
    // Steht auch bei Treffern da: derselbe Titel kann eine andere Position meinen.
    { kind: 'new', id: 'new', label: term.trim(), meta: '' },
  ]
}

/** `recentIds` ist "kind:id", jüngste zuerst — so bleibt die Reihenfolge über Pakete und Positionen hinweg erhalten. */
export function recentOptions(
  recentIds: string[],
  packages: App.Catalog.Data.PackageData[],
  items: App.Catalog.Data.CatalogItemData[],
): InsertOption[] {
  return recentIds
    .map((recentId): InsertOption | null => {
      const [kind, id] = recentId.split(':')

      if (kind === 'package') {
        const pkg = packages.find((entry) => entry.id === id)
        return pkg ? { kind: 'package', id: pkg.id, label: pkg.name, meta: `${pkg.itemCount} · ${formatEurosRounded(packageTotal(pkg))}` } : null
      }

      const item = items.find((entry) => entry.id === id)
      return item ? { kind: 'item', id: item.id, label: item.title, meta: item.unitPrice === null ? '' : formatEurosRounded(item.unitPrice) } : null
    })
    .filter((option): option is InsertOption => option !== null)
}

export function packageRows(pkg: App.Catalog.Data.PackageData): QuoteRow[] {
  return pkg.items.map((item) => ({
    title: item.title,
    description: item.description ?? '',
    quantity: item.quantity,
    unitPrice: centsToEuros(item.unitPrice),
    discount: '',
    selectable: item.selectable,
    selected: item.selected,
    outcome: item.outcome ?? '',
    hasImage: item.hasImage,
  }))
}

/** Eine Zeile aus der Bibliothek ist per Definition gemerkt. */
export function itemRow(item: App.Catalog.Data.CatalogItemData): QuoteRow {
  return {
    title: item.title,
    description: item.description ?? '',
    quantity: 1,
    unitPrice: centsToEuros(item.unitPrice),
    discount: '',
    selectable: false,
    selected: false,
    outcome: '',
    hasImage: false,
  }
}
