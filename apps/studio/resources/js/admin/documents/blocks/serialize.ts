import type { EditorBlock, EditorDocument, SerializeProfile } from './types'

// Blocks -> HTML. Pure, no DOM, so it runs identically in the browser and in
// vitest's node environment. Its output lands in document_templates.body and
// from there in MergeFieldResolver, DocumentRenderer and Gotenberg — nothing
// downstream of the column knows the editor exists.
//
// Inline markup inside a block (bold, links, merge-field chips) arrives
// already restricted by each tool's Editor.js `sanitize` config, so this file
// does not re-sanitise it. The one exception is href, which is re-checked
// here because a mail body reaches an inbox we never render ourselves. We
// match all three href quoting styles (double, single, unquoted) because the
// serializer's input is not guaranteed to have come from the editor.

const SPACER_HEIGHTS: Record<string, string> = { s: '12px', m: '24px', l: '48px' }
const SAFE_URL = /^(https?:|mailto:)/i
const HREF = /\s+href\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/gi

export function stripUnsafeHrefs(html: string): string {
  return html.replace(HREF, (match, double: string, single: string, bare: string) => {
    const url = (double ?? single ?? bare ?? '').trim()

    return SAFE_URL.test(url) ? match : ''
  })
}

function escapeText(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function inline(value: unknown): string {
  return stripUnsafeHrefs(String(value ?? ''))
}

/** @editorjs/list v1 gives plain strings, v2 gives {content, items}. Accept both. */
function itemText(item: unknown): string {
  if (typeof item === 'string') return inline(item)

  return inline((item as { content?: string })?.content ?? '')
}

function serializeBlock(block: EditorBlock, profile: SerializeProfile): string {
  const data = block.data ?? {}

  switch (block.type) {
    case 'paragraph':
      return `<p>${inline(data.text)}</p>`

    case 'header': {
      const level = Math.min(3, Math.max(1, Number(data.level) || 2))

      return `<h${level}>${inline(data.text)}</h${level}>`
    }

    case 'list': {
      const tag = data.style === 'ordered' ? 'ol' : 'ul'
      const items = (data.items ?? []).map((item: unknown) => `<li>${itemText(item)}</li>`).join('')

      return `<${tag}>${items}</${tag}>`
    }

    case 'quote': {
      const caption = String(data.caption ?? '')
      const cite = caption === '' ? '' : `<cite>${inline(caption)}</cite>`

      return `<blockquote><p>${inline(data.text)}</p>${cite}</blockquote>`
    }

    case 'delimiter':
      return '<hr class="doc-rule">'

    case 'table': {
      const rows: string[][] = data.content ?? []
      const cell = (tag: string) => (row: string[]) =>
        `<tr>${row.map((value) => `<${tag}>${inline(value)}</${tag}>`).join('')}</tr>`

      if (data.withHeadings && rows.length > 0) {
        const [head, ...body] = rows

        return (
          `<table class="doc-table"><thead>${cell('th')(head)}</thead>` +
          `<tbody>${body.map(cell('td')).join('')}</tbody></table>`
        )
      }

      return `<table class="doc-table"><tbody>${rows.map(cell('td')).join('')}</tbody></table>`
    }

    case 'columns': {
      // Inline HTML per side rather than nested blocks: a nested Editor.js
      // instance per column is a large amount of machinery for a two-column
      // address header, and the tool in Task 6b is two contenteditable divs.
      const left = inline(data.left)
      const right = inline(data.right)

      // Email clients still lay out with tables far more reliably than with
      // flex, so the mail profile emits one rather than a styled div.
      if (profile === 'mail') {
        return (
          '<table width="100%" cellpadding="0" cellspacing="0"><tr>' +
          `<td width="50%" valign="top">${left}</td><td width="50%" valign="top">${right}</td>` +
          '</tr></table>'
        )
      }

      return `<div class="doc-cols"><div class="doc-col">${left}</div><div class="doc-col">${right}</div></div>`
    }

    case 'callout':
      return profile === 'mail'
        ? `<div style="padding:12px 14px;border-left:3px solid #B44E22;background:#FBF0EA">${inline(data.text)}</div>`
        : `<div class="doc-callout">${inline(data.text)}</div>`

    case 'spacer':
      return `<div style="height:${SPACER_HEIGHTS[String(data.size)] ?? SPACER_HEIGHTS.m}"></div>`

    case 'image': {
      const url = String(data.url ?? '').trim()

      if (!SAFE_URL.test(url)) return ''

      const caption = escapeText(String(data.caption ?? ''))
      const figcaption = caption === '' ? '' : `<figcaption>${caption}</figcaption>`

      return `<figure><img src="${escapeText(url)}" alt="${caption}">${figcaption}</figure>`
    }

    // What a pre-editor body is imported into. Verbatim by definition — this
    // is the same staff-authored HTML the textarea accepted before.
    case 'rawHtml':
      return String(data.html ?? '')

    // A block from a later phase, or a tool that was removed. Emitting a
    // guess would put wrong wording in a contract; skipping is honest.
    default:
      return ''
  }
}

export function serialize(doc: EditorDocument, profile: SerializeProfile): string {
  return (doc.blocks ?? [])
    .map((block) => serializeBlock(block, profile))
    .filter((html) => html !== '')
    .join('\n')
}
