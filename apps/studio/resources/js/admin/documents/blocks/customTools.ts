// The blocks Editor.js has no package for. Each is a plain tool class: a
// toolbox entry, a render() that returns its editing DOM, and a save() that
// returns exactly the data shape serialize.ts expects. Kept in one file
// because they are four small classes that change together, not four
// concerns.
//
// `sanitize` is the allow-list Editor.js applies when it saves a block, which
// is why serialize.ts does not re-sanitise inline markup — except here, on
// construction, where the value comes from stored data rather than from
// Editor.js's own save path. See sanitizeInline.ts for why that path is not
// covered by `sanitize`.

import { sanitizeInline } from './sanitizeInline'

function field(placeholder: string, value: string, className = 'doc-editable'): HTMLElement {
  const element = document.createElement('div')
  element.className = className
  element.contentEditable = 'true'
  element.dataset.placeholder = placeholder
  element.innerHTML = sanitizeInline(value)

  return element
}

/** data: { text: string } */
export class CalloutTool {
  static get toolbox() {
    return { title: 'Hinweis', icon: '<b>◇</b>' }
  }

  static get sanitize() {
    return { text: { b: {}, i: {}, a: { href: true }, span: { class: 'doc-mf', 'data-mf': true } } }
  }

  private element: HTMLElement

  constructor({ data }: { data: { text?: string } }) {
    this.element = field('Hinweis', data?.text ?? '', 'doc-callout doc-editable')
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { text: string } {
    return { text: this.element.innerHTML }
  }
}

/** data: { size: 's' | 'm' | 'l' } — serialize.ts falls back to 'm' for anything else. */
export class SpacerTool {
  static get toolbox() {
    return { title: 'Abstand', icon: '<b>↕</b>' }
  }

  private element: HTMLElement
  private size: string

  constructor({ data }: { data: { size?: string } }) {
    this.size = data?.size ?? 'm'
    this.element = document.createElement('div')
    this.element.className = 'doc-spacer-tool'
    this.paint()
  }

  private paint(): void {
    this.element.replaceChildren()

    for (const size of ['s', 'm', 'l']) {
      const button = document.createElement('button')
      button.type = 'button'
      button.textContent = size.toUpperCase()
      button.className = size === this.size ? 'doc-size on' : 'doc-size'
      button.addEventListener('click', () => {
        this.size = size
        this.paint()
      })
      this.element.append(button)
    }
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { size: string } {
    return { size: this.size }
  }
}

/** data: { left: string, right: string } — inline HTML per side, not nested blocks. */
export class ColumnsTool {
  static get toolbox() {
    return { title: '2 Spalten', icon: '<b>◫</b>' }
  }

  static get sanitize() {
    const inline = { b: {}, i: {}, a: { href: true }, br: {}, span: { class: 'doc-mf', 'data-mf': true } }

    return { left: inline, right: inline }
  }

  private element: HTMLElement
  private left: HTMLElement
  private right: HTMLElement

  constructor({ data }: { data: { left?: string; right?: string } }) {
    this.left = field('Links', data?.left ?? '')
    this.right = field('Rechts', data?.right ?? '')
    this.element = document.createElement('div')
    this.element.className = 'doc-cols'
    this.left.classList.add('doc-col')
    this.right.classList.add('doc-col')
    this.element.append(this.left, this.right)
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { left: string; right: string } {
    return { left: this.left.innerHTML, right: this.right.innerHTML }
  }
}

/** data: { url: string, caption: string } — URL only; uploads are out of scope for Phase 1. */
export class ImageTool {
  static get toolbox() {
    return { title: 'Bild', icon: '<b>▭</b>' }
  }

  private element: HTMLElement
  private url: HTMLInputElement
  private caption: HTMLInputElement

  constructor({ data }: { data: { url?: string; caption?: string } }) {
    this.url = document.createElement('input')
    this.url.className = 'doc-tool-input'
    this.url.type = 'url'
    this.url.placeholder = 'https://…'
    this.url.value = data?.url ?? ''

    this.caption = document.createElement('input')
    this.caption.className = 'doc-tool-input'
    this.caption.type = 'text'
    this.caption.placeholder = 'Bildunterschrift'
    this.caption.value = data?.caption ?? ''

    const figure = document.createElement('img')
    figure.className = 'doc-image-preview'
    const paint = () => {
      figure.src = this.url.value
      figure.style.display = this.url.value === '' ? 'none' : 'block'
    }
    this.url.addEventListener('input', paint)
    paint()

    this.element = document.createElement('div')
    this.element.className = 'doc-image-tool'
    this.element.append(figure, this.url, this.caption)
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { url: string; caption: string } {
    return { url: this.url.value.trim(), caption: this.caption.value }
  }
}

/**
 * data: { html: string } — a pre-block-editor body, kept verbatim.
 *
 * Deliberately has no `toolbox` getter, so Editor.js will render an existing
 * block of this type but never offer it in the inserter: it is what a legacy
 * body is imported into, not something an author creates. A textarea rather
 * than a rendered preview because the point is that the author can read the
 * old markup, keep it, edit it, or delete the block and rebuild in blocks.
 */
export class RawHtmlTool {
  private element: HTMLTextAreaElement

  constructor({ data }: { data: { html?: string } }) {
    this.element = document.createElement('textarea')
    this.element.className = 'doc-tool-input doc-rawhtml'
    this.element.rows = 8
    this.element.value = data?.html ?? ''
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { html: string } {
    return { html: this.element.value }
  }
}
