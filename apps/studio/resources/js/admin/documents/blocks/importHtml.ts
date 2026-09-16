import type { EditorDocument } from './types'

// A template written before the block editor existed has blocks = null. Rather
// than parsing its HTML into blocks — which would silently rewrite wording
// that is already in sent documents' template history — it becomes one
// rawHtml block the author can keep, replace, or build around.
export function htmlToBlocks(html: string): EditorDocument {
  if (html.trim() === '') return { blocks: [] }

  return { blocks: [{ type: 'rawHtml', data: { html } }] }
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
