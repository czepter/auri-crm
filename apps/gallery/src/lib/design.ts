export interface GalleryDesign {
  cover: string
  type: string
  colour: string
  size: string
  spacing: string
  flow: string
}

export interface GalleryCover {
  key: string | null
  focal: { x: number; y: number } | null
}

// The gate and the pre-release screen carry no design; they stay a photograph's dark room.
export const GATE_DESIGN: GalleryDesign = {
  cover: 'zentriert', type: 'sans', colour: 'dunkel', size: 'mittel', spacing: 'eng', flow: 'zeilen',
}

// styles/gallery.css keys its rules on exactly these attributes.
export function designAttrs(design: GalleryDesign): Record<string, string> {
  return {
    'data-gcover': design.cover,
    'data-gtype': design.type,
    'data-gcolour': design.colour,
    'data-gsize': design.size,
    'data-gspacing': design.spacing,
    'data-gflow': design.flow,
  }
}

// 50% 38% is where the cover sat before a focal point could be set.
export function objectPosition(focal: GalleryCover['focal']): string {
  return focal ? `${focal.x}% ${focal.y}%` : '50% 38%'
}
