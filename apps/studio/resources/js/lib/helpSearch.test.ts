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

  // Ein gemerkter abgelehnter Promise legt die Hilfesuche bis zum
  // Seitenneuladen still, und CommandPalette.vue awaitet ohne catch.
  it('merkt sich einen Fehlschlag nicht', async () => {
    const antwort = vi
      .fn()
      .mockRejectedValueOnce(new TypeError('offline'))
      .mockResolvedValueOnce(new Response(JSON.stringify([{ slug: 'erste-schritte' }])))
    vi.stubGlobal('fetch', antwort)

    // Frisches Modul: der Test darüber hat den Modulzustand schon belegt.
    vi.resetModules()
    const modul = await import('./helpSearch')

    await expect(modul.holeIndex()).resolves.toEqual([])
    await expect(modul.holeIndex()).resolves.toEqual([{ slug: 'erste-schritte' }])

    expect(antwort).toHaveBeenCalledTimes(2)

    vi.unstubAllGlobals()
    vi.resetModules()
  })
})
