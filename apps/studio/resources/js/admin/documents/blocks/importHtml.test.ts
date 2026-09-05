import { describe, expect, it } from 'vitest'
import { fillSamples, htmlToBlocks } from './importHtml'
import { serialize } from './serialize'

describe('htmlToBlocks', () => {
  it('wraps a pre-editor body in a single raw-html block that serializes back to itself', () => {
    const body = '<p>Vertrag mit {{ contact.name }}</p><ul><li>x</li></ul>'
    const doc = htmlToBlocks(body)

    expect(doc.blocks).toHaveLength(1)
    expect(doc.blocks[0].type).toBe('rawHtml')
    expect(serialize(doc, 'print')).toBe(body)
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
