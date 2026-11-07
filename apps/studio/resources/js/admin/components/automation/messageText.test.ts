import { describe, expect, it } from 'vitest'
import { fieldCount, locate, needsSpace, offsetOf, parts, plain, read, type NodeLike } from './messageText'

const text = (value: string): NodeLike => ({ nodeType: 3, textContent: value, childNodes: [] })
const el = (tagName: string, ...kids: NodeLike[]): NodeLike => ({ nodeType: 1, tagName, textContent: null, childNodes: kids })
const chip = (path: string): NodeLike => ({ nodeType: 1, tagName: 'SPAN', dataset: { mf: path }, textContent: 'Label', childNodes: [] })
const flat = (node: NodeLike) => plain(read(node))

describe('messageText', () => {
  const known = new Set(['contact.name', 'project.name'])

  it('round-trips the saved string and keeps unknown fields literal', () => {
    const saved = 'Hallo {{ contact.name }},\n\n{{ project.name }} steht an. {{ nope.x }}'

    expect(parts(saved, known)).toEqual([
      { text: 'Hallo ' },
      { field: 'contact.name' },
      { text: ',\n\n' },
      { field: 'project.name' },
      { text: ' steht an. {{ nope.x }}' },
    ])
    expect(plain(parts(saved, known))).toBe(saved)
  })

  it('normalises spacing inside braces', () => {
    expect(plain(parts('{{contact.name}}', known))).toBe('{{ contact.name }}')
  })

  it('reads chips, text, nbsp and BR', () => {
    expect(flat(el('DIV', text('Hallo '), chip('contact.name'), el('BR'), text('Ende')))).toBe('Hallo {{ contact.name }}\nEnde')
  })

  it('maps DIV and P boundaries to newlines, an empty block to one blank line', () => {
    expect(flat(el('DIV', text('a'), el('DIV', text('b')), el('P', text('c'))))).toBe('a\nb\nc')
    expect(flat(el('DIV', el('DIV', text('a')), el('DIV', el('BR')), el('DIV', text('b'))))).toBe('a\n\nb')
    expect(flat(el('DIV', el('DIV', text('a')), text('b')))).toBe('a\nb')
  })

  it('round-trips newlines through the painted nodes', () => {
    const saved = 'a\n\nb {{ contact.name }}\nc'
    const root = el('DIV', ...parts(saved, known).map((p) => ('field' in p ? chip(p.field) : text(p.text))))

    expect(flat(root)).toBe(saved)
  })

  it('sees a typed field as one more chip than the DOM has', () => {
    const typed = el('DIV', text('Hallo {{ contact.name }}'))
    const next = flat(typed)

    expect(fieldCount(parts(next, known))).toBe(1)
    expect(typed.childNodes.length).toBe(1)
    expect(fieldCount(parts('Hallo {{ nope.x }}', known))).toBe(0)
  })

  it('keeps the caret by plain-text offset across a repaint', () => {
    const typed = el('DIV', text('Hi {{ contact.name }}'))
    const at = offsetOf(typed, typed.childNodes[0], 21)
    const painted = el('DIV', text('Hi '), chip('contact.name'))

    expect(at).toBe(21)
    expect(locate(painted, at)).toEqual({ node: painted, offset: 2 })
    expect(locate(painted, 2)).toEqual({ node: painted.childNodes[0], offset: 2 })
    expect(offsetOf(painted, painted, 2)).toBe(21)
    expect(locate(painted, 99)).toEqual({ node: painted, offset: 2 })
  })

  it('spaces a chip off a neighbour unless it opens the text or follows whitespace', () => {
    expect(needsSpace('{{ contact.name }}', 18)).toBe(true)
    expect(needsSpace('Hallo', 5)).toBe(true)
    expect(needsSpace('', 0)).toBe(false)
    expect(needsSpace('Hallo ', 6)).toBe(false)
    expect(needsSpace('Hallo\n', 6)).toBe(false)
    expect(needsSpace('Hallo', 0)).toBe(false)
  })
})
