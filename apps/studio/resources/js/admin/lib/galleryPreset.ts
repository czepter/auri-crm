import type { Permissions } from '@/admin/lib/galleryPermissions'

export type PresetSet = { name: string; access: 'internal' | 'guests' }

export type PresetRelease = {
  view_mode: 'none' | 'text' | 'logo' | 'locked'
  watermark_text: string | null
  download_rule: 'open' | 'after_payment' | 'off'
  download_sizes: ('original' | 'web')[]
  permissions: Permissions
  intro_enabled: boolean
}

export const DEFAULT_RELEASE: PresetRelease = {
  view_mode: 'none',
  watermark_text: null,
  download_rule: 'open',
  download_sizes: ['original', 'web'],
  permissions: {
    client: { favorites: true, comments: true, hide: true, share: true },
    guest: { favorites: true, comments: false, hide: false, share: true },
  },
  intro_enabled: false,
}

type Row = {
  viewMode: string | null
  downloadRule: string | null
  downloadSizes: string[] | null
  permissions: Record<string, Record<string, boolean>> | null
  introEnabled: boolean | null
}

/*
 * Ganz oder gar nicht, wie die Gestaltung: eine Vorlage setzt die Freigabe
 * oder lässt sie offen. Offen heißt null, und was fehlt, kommt aus den
 * Vorgaben der Galerie.
 */
export function releaseOf(row: Row): PresetRelease | null {
  if (row.viewMode === null && row.downloadRule === null && row.downloadSizes === null && row.permissions === null && row.introEnabled === null) {
    return null
  }

  return {
    ...DEFAULT_RELEASE,
    ...(row.viewMode === null ? {} : { view_mode: row.viewMode as PresetRelease['view_mode'] }),
    ...(row.downloadRule === null ? {} : { download_rule: row.downloadRule as PresetRelease['download_rule'] }),
    ...(row.downloadSizes === null ? {} : { download_sizes: row.downloadSizes as PresetRelease['download_sizes'] }),
    ...(row.permissions === null ? {} : { permissions: row.permissions as Permissions }),
    ...(row.introEnabled === null ? {} : { intro_enabled: row.introEnabled }),
  }
}

/** Ein Name genügt; leere und doppelte (ohne Groß-/Kleinschreibung) legen nichts an. */
export function addSet(sets: PresetSet[], name: string): PresetSet[] {
  const trimmed = name.trim()

  if (trimmed === '' || sets.some((set) => set.name.toLowerCase() === trimmed.toLowerCase())) {
    return sets
  }

  return [...sets, { name: trimmed, access: 'internal' }]
}

export function withAccess(sets: PresetSet[], index: number, access: PresetSet['access']): PresetSet[] {
  return sets.map((set, at) => (at === index ? { ...set, access } : set))
}

export function withoutSet(sets: PresetSet[], index: number): PresetSet[] {
  return sets.filter((_, at) => at !== index)
}
