/**
 * C-05 (#239): the proof under a metric. One dot is one case — an inquiry, an
 * instalment, a quote, a task.
 *
 * Twelve at most, and the rest is a bare count: 8px dot plus 5px gutter makes
 * 151px, the narrowest tile that does not wrap, and neither a second row nor
 * smaller dots keep the row readable at that width.
 *
 * Drawn in stage order rather than in the order the rows arrived, because
 * otherwise the colour distribution jumps the moment one case moves on.
 */
export const TONES = ['warn', 'on', 's2', 's3', ''] as const

export type Tone = (typeof TONES)[number]

export const MAX_DOTS = 12

export function drawDots(tones: Tone[], max: number = MAX_DOTS): { shown: Tone[]; overflow: number } {
  const ordered = [...tones].sort((a, b) => TONES.indexOf(a) - TONES.indexOf(b))

  return { shown: ordered.slice(0, max), overflow: Math.max(0, ordered.length - max) }
}
