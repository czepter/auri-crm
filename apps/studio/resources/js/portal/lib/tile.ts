export const ROW_HEIGHT = 260

// A last row may grow to 1.5 times the row height; beyond that the tile stops
// instead of filling the width.
const LAST_ROW_STRETCH = 1.5

export function tileStyle(asset: { width: number | null; height: number | null }) {
  const ratio = asset.width && asset.height ? asset.width / asset.height : 1.5

  // 34vw caps the row on a phone, where one full-height row would fit a single landscape frame.
  const row = `min(${ROW_HEIGHT}px, 34vw)`

  return {
    aspectRatio: String(ratio),
    flex: `${ratio * 100} 1 calc(${ratio} * ${row})`,
    maxWidth: `calc(${ratio * LAST_ROW_STRETCH} * ${row})`,
  }
}
