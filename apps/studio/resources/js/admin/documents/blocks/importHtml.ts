import type { EditorDocument } from './types'

const BLOCK = /\s*(?:<(p|h[1-6]|ul|ol)>([\s\S]*?)<\/\1>|<hr(?:\s+class="doc-rule")?\s*\/?>)\s*/y
const PLACEHOLDER = /\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/g

function chips(text: string, samples: Record<string, string>): string {
  return text.replace(PLACEHOLDER, (_match, path: string) => `<span class="doc-mf" data-mf="${path}">${samples[path] ?? path}</span>`)
}

// Plain tags without attributes map to blocks, {{ path }} becomes the chip the
// editor shows. Anything else (tables, divs, styled tags, nested lists) keeps
// the whole body as one rawHtml block, so no signed wording is rewritten by
// an import that guessed.
export function htmlToBlocks(html: string, samples: Record<string, string> = {}): EditorDocument {
  if (html.trim() === '') return { blocks: [] }

  const raw: EditorDocument = { blocks: [{ type: 'rawHtml', data: { html } }] }
  const blocks: EditorDocument['blocks'] = []
  const source = html.trim()
  BLOCK.lastIndex = 0

  while (BLOCK.lastIndex < source.length) {
    const match = BLOCK.exec(source)
    if (match === null) return raw

    const [, tag, inner = ''] = match

    if (tag === undefined) blocks.push({ type: 'delimiter', data: {} })
    else if (tag === 'p') blocks.push({ type: 'paragraph', data: { text: chips(inner, samples) } })
    else if (tag[0] === 'h') blocks.push({ type: 'header', data: { text: chips(inner, samples), level: Math.min(3, Number(tag[1])) } })
    else {
      const items = [...inner.matchAll(/<li>([\s\S]*?)<\/li>/g)]
      if (/<(ul|ol|li\s)/.test(inner.replace(/<li>/g, '')) || inner.replace(/<li>[\s\S]*?<\/li>/g, '').trim() !== '') return raw

      blocks.push({ type: 'list', data: { style: tag === 'ol' ? 'ordered' : 'unordered', items: items.map((item) => chips(item[1], samples)) } })
    }
  }

  return { blocks }
}

// The instant half of the two-speed preview: what the sheet shows in the
// ~400ms before the server's answer lands. Cosmetic only — the server response
// always overwrites it, and MergeFieldResolver remains the only thing that
// decides what a real document says.
//
// ponytail: a plain regex, not a resolver. Pattern is identical to
// MergeFieldResolver::PATTERN on the PHP side — do not change it without
// updating both. If agreement grows beyond the form and empty-string-for-unknown
// behaviour, replace with a server round trip rather than a second engine.
export function fillSamples(html: string, samples: Record<string, string>): string {
  return html.replace(/\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*)\s*\}\}/g, (_match, path: string) => samples[path] ?? '')
}
