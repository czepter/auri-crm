import { describe, expect, it } from 'vitest'
import { fillSamples, htmlToBlocks } from './importHtml'
import { serialize } from './serialize'

describe('htmlToBlocks', () => {
  it('turns a pre-editor body into blocks with merge-field chips and serializes back to itself', () => {
    const body = '<h2>Vertrag</h2><p>Mit {{ contact.name }}</p><ul><li>x</li></ul>'
    const doc = htmlToBlocks(body, { 'contact.name': 'Lena Meier' })

    expect(doc.blocks.map((block) => block.type)).toEqual(['header', 'paragraph', 'list'])
    expect(doc.blocks[1].data.text).toContain('data-mf="contact.name"')
    expect(serialize(doc, 'print')).toBe('<h2>Vertrag</h2><p>Mit {{ contact.name }}</p><ul><li>x</li></ul>')
  })

  it('keeps a body with a table as one raw-html block', () => {
    const body = '<p>a</p><table><tr><td>x</td></tr></table>'

    expect(htmlToBlocks(body).blocks).toEqual([{ type: 'rawHtml', data: { html: body } }])
  })

  it('gives an empty body an empty document rather than an empty block', () => {
    expect(htmlToBlocks('').blocks).toEqual([])
    expect(htmlToBlocks('   ').blocks).toEqual([])
  })
})

describe('fillSamples', () => {
  const samples = { 'contact.name': 'Lena Meier', 'project.name': 'Hochzeit Meier & Kowalski' }

  it('substitutes a known path, with or without inner spaces', () => {
    expect(fillSamples('<p>{{ contact.name }}</p>', samples)).toBe('<p>Lena Meier</p>')
    expect(fillSamples('<p>{{contact.name}}</p>', samples)).toBe('<p>Lena Meier</p>')
  })

  it('empties an unknown path, the same way MergeFieldResolver does', () => {
    expect(fillSamples('<p>{{ studio.unknown }}</p>', samples)).toBe('<p></p>')
  })

  it('substitutes every occurrence, not just the first', () => {
    expect(fillSamples('{{ contact.name }} und {{ contact.name }}', samples)).toBe('Lena Meier und Lena Meier')
  })

  it('leaves html without placeholders untouched', () => {
    expect(fillSamples('<p>nichts</p>', samples)).toBe('<p>nichts</p>')
  })

  it('leaves a malformed path alone, exactly as MergeFieldResolver does', () => {
    expect(fillSamples('<p>{{ contact..name }}</p>', samples)).toBe('<p>{{ contact..name }}</p>')
    expect(fillSamples('<p>{{ .contact }}</p>', samples)).toBe('<p>{{ .contact }}</p>')
    expect(fillSamples('<p>{{ contact. }}</p>', samples)).toBe('<p>{{ contact. }}</p>')
  })
})
