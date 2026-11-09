export type GalleryDesign = {
  cover: string
  type: string
  colour: string
  size: string
  spacing: string
  flow: string
}

export const COVERS = ['zentriert', 'links', 'rahmen', 'geteilt'] as const
export const TYPES = ['sans', 'serif', 'modern', 'zeitlos', 'kraeftig', 'dezent'] as const
export const SIZES = ['klein', 'mittel', 'gross'] as const
export const SPACINGS = ['eng', 'weit'] as const
export const FLOWS = ['spalten', 'zeilen'] as const

// bg, ink, muted and line are the same values as css/gallery.css; hero is the placeholder photo.
export const COLOURS: Record<string, { bg: string; ink: string; muted: string; line: string; hero: string }> = {
  hell: { bg: '#FFFFFF', ink: '#151A22', muted: '#626B78', line: '#E2E7EB', hero: '#C9D1D7' },
  gold: { bg: '#FBF6E9', ink: '#2A2412', muted: '#7A6E4A', line: '#E9DDB8', hero: '#D9C58A' },
  rose: { bg: '#FBF1F1', ink: '#2E1B1E', muted: '#836468', line: '#EBD3D5', hero: '#DDB3B8' },
  terracotta: { bg: '#FAF0EA', ink: '#33190F', muted: '#8A5B49', line: '#EBCDBE', hero: '#D39A80' },
  sand: { bg: '#FBF7F2', ink: '#2A211B', muted: '#7A6A5C', line: '#E6DBCC', hero: '#D8C4AC' },
  olive: { bg: '#F3F4EA', ink: '#20240F', muted: '#646B45', line: '#D9DDBF', hero: '#B3BB86' },
  agave: { bg: '#EEF4F0', ink: '#14261D', muted: '#577264', line: '#CFE0D5', hero: '#9DBBA8' },
  meer: { bg: '#EDF3F6', ink: '#10242E', muted: '#55707E', line: '#CBDDE5', hero: '#8FB2C4' },
  dunkel: { bg: '#0E0C0B', ink: '#F2EDE9', muted: '#8B817B', line: '#241F1C', hero: '#2C2521' },
}

export const COLOUR_KEYS = Object.keys(COLOURS)

export const FONTS: Record<string, { family: string; weight: number }> = {
  sans: { family: "'Hanken Grotesk', system-ui, sans-serif", weight: 600 },
  serif: { family: "Georgia, 'Times New Roman', serif", weight: 600 },
  modern: { family: "'Avenir Next', Avenir, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif", weight: 500 },
  zeitlos: { family: "'Palatino Linotype', Palatino, 'Book Antiqua', 'URW Palladio L', serif", weight: 500 },
  kraeftig: { family: "'Helvetica Neue', Arial, sans-serif", weight: 800 },
  dezent: { family: "'Helvetica Neue', 'Segoe UI', Arial, sans-serif", weight: 300 },
}

export const DEFAULT_DESIGN: GalleryDesign = {
  cover: 'zentriert', type: 'serif', colour: 'hell', size: 'mittel', spacing: 'eng', flow: 'zeilen',
}

// Schrift und Farbe als Paket; die übrigen Schlüssel bleiben, wie sie sind.
export const STYLES = [
  { key: 'klar', type: 'sans', colour: 'hell' },
  { key: 'klassisch', type: 'serif', colour: 'hell' },
  { key: 'warm', type: 'sans', colour: 'sand' },
  { key: 'dunkel', type: 'sans', colour: 'dunkel' },
] as const

export function styleOf(design: Pick<GalleryDesign, 'type' | 'colour'>): string {
  return STYLES.find((style) => style.type === design.type && style.colour === design.colour)?.key ?? 'eigene'
}

export function withStyle(design: GalleryDesign, key: string): GalleryDesign {
  const style = STYLES.find((one) => one.key === key)

  return style ? { ...design, type: style.type, colour: style.colour } : design
}

// Ein gespeichertes Design kann Schlüssel auslassen; der Editor zeigt immer alle sechs.
export function complete(design: Partial<GalleryDesign> | null): GalleryDesign {
  return { ...DEFAULT_DESIGN, ...design }
}

// Spalten in der Vorschau: Telefon zeigt höchstens zwei, wie der Betrachter.
export function columnsOf(size: string, phone: boolean): number {
  const columns = { klein: 5, mittel: 4, gross: 2 }[size] ?? 4

  return phone ? Math.min(columns, size === 'gross' ? 1 : 2) : columns
}
