import { describe, expect, it, vi } from 'vitest'
import { holeIndex, treffer } from './helpSearch'

describe('Palettensuche', () => {
  it('findet einen Artikel über seinen Volltext, nicht nur den Titel', () => {
    const index = [
      { slug: 'mcp-claude-verbinden', titel: 'Claude verbinden', beschreibung: '', art: 'aufgabe', suchtext: 'Über MCP spricht Claude mit deinem Studio.' },
    ]

    expect(treffer(index, 'MCP').map((t) => t.slug)).toEqual(['mcp-claude-verbinden'])
  })

  it('gibt bei leerer Eingabe nichts zurück', () => {
    const index = [
      { slug: 'mcp-claude-verbinden', titel: 'Claude verbinden', beschreibung: '', art: 'aufgabe', suchtext: 'Über MCP spricht Claude mit deinem Studio.' },
    ]

    expect(treffer(index, '')).toEqual([])
  })
})

describe('holeIndex', () => {
  it('holt den Index nur beim ersten Aufruf, danach aus dem Modulzustand', async () => {
    const antwort = vi.fn(async () => new Response(JSON.stringify([])))
    vi.stubGlobal('fetch', antwort)

    await holeIndex()
    await holeIndex()

    expect(antwort).toHaveBeenCalledOnce()

    vi.unstubAllGlobals()
  })
})
