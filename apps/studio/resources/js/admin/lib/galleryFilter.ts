export type GalleryFilter = 'all' | 'draft' | 'prerelease' | 'published' | 'expiring'

export const EXPIRING_DAYS = 30

interface Row {
  status: string
  expiresAt: string | null
  projectType?: string | null
}

export type GalleryView = 'list' | 'grid'

const VIEW_KEY = 'galleries.view'

export function loadView(): GalleryView {
  try {
    return localStorage.getItem(VIEW_KEY) === 'grid' ? 'grid' : 'list'
  } catch {
    return 'list'
  }
}

export function saveView(view: GalleryView) {
  try {
    localStorage.setItem(VIEW_KEY, view)
  } catch {
    // Privates Fenster oder gesperrter Speicher: die Wahl gilt nur bis zum Neuladen.
  }
}

export function isExpiring(row: Row, now: Date): boolean {
  if (row.status !== 'published' || row.expiresAt === null) return false

  const left = new Date(row.expiresAt).getTime() - now.getTime()

  return left >= 0 && left <= EXPIRING_DAYS * 86_400_000
}

export function filterGalleries<T extends Row>(rows: T[], filter: GalleryFilter, now: Date, projectType = 'all'): T[] {
  const typed = projectType === 'all' ? rows : rows.filter((row) => row.projectType === projectType)

  if (filter === 'all') return typed
  if (filter === 'expiring') return typed.filter((row) => isExpiring(row, now))

  return typed.filter((row) => row.status === filter)
}
