import { describe, expect, it } from 'vitest'
import { serialize, stripUnsafeHrefs } from './serialize'
import type { EditorDocument } from './types'

const doc = (...blocks: EditorDocument['blocks']): EditorDocument => ({ blocks })

describe('text blocks', () => {
  it('renders a paragraph and keeps the inline markup Editor.js already sanitised', () => {
    const html = serialize(doc({ type: 'paragraph', data: { text: 'Hallo <b>Lena</b>' } }), 'print')

    expect(html).toBe('<p>Hallo <b>Lena</b></p>')
  })

  it('renders a heading at its level, clamped to h1-h3', () => {
    expect(serialize(doc({ type: 'header', data: { text: 'Vertrag', level: 2 } }), 'print')).toBe('<h2>Vertrag</h2>')
    expect(serialize(doc({ type: 'header', data: { text: 'Tief', level: 9 } }), 'print')).toBe('<h3>Tief</h3>')
  })

  it('renders both list styles, and tolerates the nested-list item shape', () => {
    expect(serialize(doc({ type: 'list', data: { style: 'ordered', items: ['eins', 'zwei'] } }), 'print'))
      .toBe('<ol><li>eins</li><li>zwei</li></ol>')

    expect(serialize(doc({ type: 'list', data: { style: 'unordered', items: [{ content: 'eins', items: [] }] } }), 'print'))
      .toBe('<ul><li>eins</li></ul>')
  })

  it('renders a quote with its caption, and omits an empty caption', () => {
    expect(serialize(doc({ type: 'quote', data: { text: 'Zitat', caption: 'Lena' } }), 'print'))
      .toBe('<blockquote><p>Zitat</p><cite>Lena</cite></blockquote>')

    expect(serialize(doc({ type: 'quote', data: { text: 'Zitat', caption: '' } }), 'print'))
      .toBe('<blockquote><p>Zitat</p></blockquote>')
  })

  it('renders a delimiter as a rule', () => {
    expect(serialize(doc({ type: 'delimiter', data: {} }), 'print')).toBe('<hr class="doc-rule">')
  })
})

describe('layout blocks', () => {
  it('renders a table, promoting the first row to headings when asked', () => {
    const block = { type: 'table', data: { withHeadings: true, content: [['Position', 'Betrag'], ['Reportage', '2.400']] } }

    expect(serialize(doc(block), 'print')).toBe(
      '<table class="doc-table"><thead><tr><th>Position</th><th>Betrag</th></tr></thead>' +
        '<tbody><tr><td>Reportage</td><td>2.400</td></tr></tbody></table>',
    )
  })

  it('renders columns as a flex row for print and as a table for mail', () => {
    const block = { type: 'columns', data: { left: 'links', right: 'rechts' } }

    expect(serialize(doc(block), 'print')).toBe(
      '<div class="doc-cols"><div class="doc-col">links</div><div class="doc-col">rechts</div></div>',
    )

    const mail = serialize(doc(block), 'mail')
    expect(mail).toContain('<table')
    expect(mail).toContain('width="50%"')
    expect(mail).not.toContain('class="doc-cols"')
  })

  it('renders a callout with a class for print and inline styles for mail', () => {
    const block = { type: 'callout', data: { text: 'Bitte beachten' } }

    expect(serialize(doc(block), 'print')).toBe('<div class="doc-callout">Bitte beachten</div>')
    expect(serialize(doc(block), 'mail')).toContain('style="')
  })

  it('renders a spacer at one of three fixed heights and ignores anything else', () => {
    expect(serialize(doc({ type: 'spacer', data: { size: 'l' } }), 'print')).toBe('<div style="height:48px"></div>')
    expect(serialize(doc({ type: 'spacer', data: { size: 'nonsense' } }), 'print')).toBe('<div style="height:24px"></div>')
  })

  it('renders an image with an escaped alt, and drops one with an unsafe url', () => {
    expect(serialize(doc({ type: 'image', data: { url: 'https://x.test/a.png', caption: 'Logo & Co' } }), 'print'))
      .toBe('<figure><img src="https://x.test/a.png" alt="Logo &amp; Co"><figcaption>Logo &amp; Co</figcaption></figure>')

    expect(serialize(doc({ type: 'image', data: { url: 'javascript:alert(1)', caption: '' } }), 'print')).toBe('')
  })
})

describe('safety and unknown input', () => {
  it('passes a legacy raw-html block through untouched', () => {
    expect(serialize(doc({ type: 'rawHtml', data: { html: '<p>alt <span>x</span></p>' } }), 'print'))
      .toBe('<p>alt <span>x</span></p>')
  })

  it('drops an anchor href that is not http, https or mailto', () => {
    expect(stripUnsafeHrefs('<a href="javascript:alert(1)">x</a>')).toBe('<a>x</a>')
    expect(stripUnsafeHrefs('<a href="https://x.test">x</a>')).toBe('<a href="https://x.test">x</a>')
    expect(stripUnsafeHrefs('<a href="mailto:a@b.test">x</a>')).toBe('<a href="mailto:a@b.test">x</a>')
  })

  it('drops an unsafe href regardless of how it is quoted', () => {
    expect(stripUnsafeHrefs(`<a href='javascript:alert(1)'>x</a>`)).toBe('<a>x</a>')
    expect(stripUnsafeHrefs('<a href=javascript:alert(1)>x</a>')).toBe('<a>x</a>')
    expect(stripUnsafeHrefs('<a href = "javascript:alert(1)">x</a>')).toBe('<a>x</a>')
  })

  it('keeps a safe href regardless of how it is quoted', () => {
    expect(stripUnsafeHrefs(`<a href='https://x.test'>x</a>`)).toBe(`<a href='https://x.test'>x</a>`)
    expect(stripUnsafeHrefs('<a href=https://x.test>x</a>')).toBe('<a href=https://x.test>x</a>')
  })

  it('strips an unsafe href out of paragraph text on the way through', () => {
    expect(serialize(doc({ type: 'paragraph', data: { text: '<a href="javascript:x()">klick</a>' } }), 'print'))
      .toBe('<p><a>klick</a></p>')
  })

  it('strips an unsafe single-quoted href out of paragraph text on the way through', () => {
    expect(serialize(doc({ type: 'paragraph', data: { text: `<a href='javascript:x()'>klick</a>` } }), 'print'))
      .toBe('<p><a>klick</a></p>')
  })

  it('skips a block type it does not know rather than emitting garbage', () => {
    expect(serialize(doc({ type: 'signature', data: {} }, { type: 'paragraph', data: { text: 'da' } }), 'print'))
      .toBe('<p>da</p>')
  })

  it('joins blocks with a newline so the stored body stays readable in a diff', () => {
    expect(serialize(doc({ type: 'paragraph', data: { text: 'a' } }, { type: 'paragraph', data: { text: 'b' } }), 'print'))
      .toBe('<p>a</p>\n<p>b</p>')
  })
})
