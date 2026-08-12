// The editor's render path is a trust boundary the tools' own `sanitize`
// configs do not cover: Editor.js applies those when it *saves* inside a live
// browser editor, not when a tool is constructed from stored block data. A
// blocks payload that reached storage without passing through that save path
// would otherwise be re-injected as live HTML into the next staff member's
// contenteditable.
//
// DOM-based rather than regex-based, because an allow-list over parsed nodes
// is the only kind that holds against nested and malformed markup. That means
// it needs a real DOM, so it is not unit-tested under vitest's node
// environment — the load-bearing, fully tested copy of this allow-list lives
// server-side on the write path; this one is defence in depth on the read.

export type InlineAllowList = Record<string, Record<string, true>>

export const DOCUMENT_INLINE: InlineAllowList = {
  B: {},
  STRONG: {},
  I: {},
  EM: {},
  U: {},
  BR: {},
  A: { href: true },
  SPAN: { class: true, 'data-mf': true },
}

export function sanitizeInline(html: string, allow: InlineAllowList = DOCUMENT_INLINE): string {
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, 'text/html')

  const walk = (node: Element): void => {
    for (const child of Array.from(node.children)) {
      walk(child)

      const permitted = allow[child.tagName]

      if (permitted === undefined) {
        // Unwrap rather than delete: the author's text survives, the tag does not.
        child.replaceWith(...Array.from(child.childNodes))

        continue
      }

      for (const attribute of Array.from(child.attributes)) {
        if (permitted[attribute.name] !== true) child.removeAttribute(attribute.name)
      }

      if (child.tagName === 'A' && !/^(https?:|mailto:)/i.test(child.getAttribute('href') ?? '')) {
        child.removeAttribute('href')
      }
    }
  }

  walk(doc.body)

  return doc.body.innerHTML
}
