import { describe, expect, it } from 'vitest'
import { treffer } from './helpSearch'

describe('Palettensuche', () => {
  it('findet einen Artikel über seinen Volltext, nicht nur den Titel', () => {
    const index = [
      { slug: 'mcp-claude-verbinden', titel: 'Claude verbinden', beschreibung: '', art: 'aufgabe', suchtext: 'Über MCP spricht Claude mit deinem Studio.' },
    ]

    expect(treffer(index, 'MCP').map((t) => t.slug)).toEqual(['mcp-claude-verbinden'])
  })

  it('gibt bei leerer Eingabe nichts zurück', () => {
    expect(treffer([], '')).toEqual([])
  })
})
