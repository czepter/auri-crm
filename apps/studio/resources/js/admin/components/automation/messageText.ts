export type Part = { text: string } | { field: string }

const FIELD = /\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/g

// Unknown paths stay literal text: the server check names them, a chip would hide them.
export function parts(text: string, known: Set<string>): Part[] {
  const out: Part[] = []
  let last = 0

  for (const match of text.matchAll(FIELD)) {
    if (!known.has(match[1])) continue
    if (match.index > last) out.push({ text: text.slice(last, match.index) })
    out.push({ field: match[1] })
    last = match.index + match[0].length
  }

  if (last < text.length) out.push({ text: text.slice(last) })

  return out
}

export function plain(list: Part[]): string {
  return list.map((part) => ('field' in part ? `{{ ${part.field} }}` : part.text)).join('')
}

// The structural subset of a DOM node that read/offsetOf/locate use, so the
// logic runs under vitest's node environment without a DOM library.
export type NodeLike = {
  nodeType: number
  textContent: string | null
  tagName?: string
  dataset?: { mf?: string }
  childNodes: ArrayLike<NodeLike>
}

const TEXT = 3
const ELEMENT = 1
const isBlock = (node: NodeLike) => node.nodeType === ELEMENT && (node.tagName === 'DIV' || node.tagName === 'P')
const isChip = (node: NodeLike) => node.nodeType === ELEMENT && Boolean(node.dataset?.mf)

export function read(node: NodeLike): Part[] {
  if (node.nodeType === TEXT) return [{ text: (node.textContent ?? '').replace(/ /g, ' ') }]
  if (node.nodeType !== ELEMENT) return []
  if (node.dataset?.mf) return [{ field: node.dataset.mf }]
  if (node.tagName === 'BR') return [{ text: '\n' }]

  const kids = Array.from(node.childNodes)

  return kids.flatMap((kid, i) => {
    const boundary = i > 0 && (isBlock(kid) || isBlock(kids[i - 1])) ? [{ text: '\n' }] : []
    const blank = isBlock(kid) && kid.childNodes.length === 1 && kid.childNodes[0].tagName === 'BR'

    return [...boundary, ...(blank ? [] : read(kid))]
  })
}

const length = (node: NodeLike) => plain(read(node)).length

/** Plain-text offset of a caret, each chip counting as the length of its `{{ path }}`. */
export function offsetOf(root: NodeLike, target: NodeLike, targetOffset: number): number {
  let total = 0

  const walk = (node: NodeLike): boolean => {
    if (node === target) {
      total += node.nodeType === TEXT
        ? Math.min(targetOffset, (node.textContent ?? '').length)
        : Array.from(node.childNodes).slice(0, targetOffset).reduce((sum, kid) => sum + length(kid), 0)

      return true
    }
    if (node.nodeType !== ELEMENT || isChip(node) || node.tagName === 'BR') {
      total += length(node)

      return false
    }

    return Array.from(node.childNodes).some(walk)
  }

  walk(root)

  return total
}

/** The inverse of offsetOf; past the end it lands at the end. */
export function locate(root: NodeLike, offset: number): { node: NodeLike; offset: number } {
  let rest = offset

  const walk = (parent: NodeLike): { node: NodeLike; offset: number } | null => {
    const kids = Array.from(parent.childNodes)

    for (const [i, kid] of kids.entries()) {
      if (kid.nodeType === TEXT) {
        const size = (kid.textContent ?? '').length
        if (rest <= size) return { node: kid, offset: rest }
        rest -= size
      } else if (isChip(kid) || kid.tagName === 'BR') {
        const size = length(kid)
        if (rest <= 0) return { node: parent, offset: i }
        if (rest <= size) return { node: parent, offset: i + 1 }
        rest -= size
      } else if (kid.nodeType === ELEMENT) {
        const found = walk(kid)
        if (found) return found
      }
    }

    return null
  }

  return walk(root) ?? { node: root, offset: root.childNodes.length }
}

export const fieldCount = (list: Part[]) => list.filter((part) => 'field' in part).length

/** A chip goes in with a space before it unless it opens the text or follows whitespace. */
export const needsSpace = (text: string, at: number) => at > 0 && !/\s/.test(text[at - 1])
