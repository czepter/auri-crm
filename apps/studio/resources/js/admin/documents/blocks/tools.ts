import type { PaletteEntry } from './types'

// Palette (from DocumentBlockCatalog, per document type) -> Editor.js `tools`.
//
// The registry is a parameter rather than an import so this stays a pure
// function: DocumentEditor.vue passes the real tool classes, and its test
// passes strings. It also means a palette entry whose tool has not been built
// yet (a Phase 2 block reaching a stale bundle) drops out quietly instead of
// throwing inside Editor.js's constructor.
export function buildTools(
  palette: PaletteEntry[],
  registry: Record<string, unknown>,
): Record<string, unknown> {
  const tools: Record<string, unknown> = {}

  for (const entry of palette) {
    // Dimmed entries are shown, never registered: DocumentBlockCatalog::
    // allows() would refuse them on save anyway, and a tool that inserts a
    // block the server rejects is worse than one that is not offered.
    if (entry.inline || entry.allowed === false) continue

    const tool = registry[entry.type]

    if (tool !== undefined) tools[entry.type] = tool
  }

  return tools
}
