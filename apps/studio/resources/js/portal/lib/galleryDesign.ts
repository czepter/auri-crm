export type GalleryDesign = {
  cover: string
  type: string
  colour: string
  size: string
  spacing: string
  flow: string
}

// css/gallery.css keys its rules on exactly these attributes.
export function designAttrs(design: GalleryDesign) {
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
export function objectPosition(focal: { x: number; y: number } | null) {
  return focal ? `${focal.x}% ${focal.y}%` : '50% 38%'
}
