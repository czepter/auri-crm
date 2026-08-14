// The shape Editor.js's save() returns. Kept in its own module so the
// serializer, the importer and the Vue components share one definition
// rather than each restating it.
export type EditorBlock = {
  id?: string
  type: string
  // Editor.js block data is per-tool by design; narrowing it here would mean
  // a union that has to be edited every time a tool is added.
  data: Record<string, any>
}

export type EditorDocument = {
  time?: number
  blocks: EditorBlock[]
  version?: string
}

// print goes to Gotenberg, mail goes into an inbox we do not render.
export type SerializeProfile = 'print' | 'mail'

export type PaletteEntry = {
  type: string
  group: string
  label: string
  inline: boolean
}
