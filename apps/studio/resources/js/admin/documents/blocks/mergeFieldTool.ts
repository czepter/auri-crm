// The inline tool behind the chips in the mockup. A merge field is a span
// carrying its path in data-mf and *showing the sample value* — a paragraph
// full of {{ contact.name }} is unreadable as prose, which is what staff are
// actually editing. serialize.ts turns the span back into {{ path }} via the
// data attribute, so what is stored stays exactly what MergeFieldResolver
// already understands.
type MergeField = { path: string; sample: string }

function chip(field: MergeField): HTMLSpanElement {
  const span = document.createElement('span')
  span.className = 'doc-mf'
  span.dataset.mf = field.path
  span.contentEditable = 'false'
  span.textContent = field.sample

  return span
}

const ICON = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M10 7H9a1 1 0 0 0-1 1v2.5a1.5 1.5 0 0 1-1.5 1.5A1.5 1.5 0 0 1 8 13.5V16a1 1 0 0 0 1 1h1M14 7h1a1 1 0 0 1 1 1v2.5a1.5 1.5 0 0 0 1.5 1.5 1.5 1.5 0 0 0-1.5 1.5V16a1 1 0 0 1-1 1h-1"/></svg>'

type BlockApi = {
  blocks: {
    getBlockIndex(id: string): number
    insert(type: string, data: object, config: undefined, index: number, focus: boolean, replace: boolean): unknown
  }
  caret: { setToBlock(index: number, position: string): boolean }
}

// The inserter lists block tools only, so each field is an entry of a block
// that swaps itself for a paragraph holding the chip. Nothing of this type is
// ever saved, which keeps it out of DocumentBlockCatalog.
export function createMergeFieldBlock(fields: MergeField[]) {
  return class MergeFieldBlock {
    static toolbox = fields.map((field) => ({
      title: `${field.sample} · ${field.path}`,
      icon: ICON,
      data: { path: field.path },
    }))

    private api: BlockApi
    private id: string
    private path: string

    constructor({ data, api, block }: { data: { path?: string }; api: BlockApi; block: { id: string } }) {
      this.api = api
      this.id = block.id
      this.path = data.path ?? ''
    }

    // Editable on purpose: the inserter appends a paragraph after any new
    // block that has no input.
    render(): HTMLElement {
      const holder = document.createElement('div')
      holder.contentEditable = 'true'

      return holder
    }

    rendered(): void {
      const field = fields.find((candidate) => candidate.path === this.path)
      const index = this.api.blocks.getBlockIndex(this.id)

      // The trailing space gives the caret an editable node behind the chip.
      this.api.blocks.insert('paragraph', { text: field ? `${chip(field).outerHTML}&nbsp;` : '' }, undefined, index, true, true)
      this.api.caret.setToBlock(index, 'end')
    }

    save(): object {
      return {}
    }
  }
}

export function createMergeFieldTool(fields: MergeField[]) {
  return class MergeFieldTool {
    static isInline = true
    static title = 'Feld'

    // Editor.js strips anything not declared here when it saves a block.
    static get sanitize() {
      return { span: { class: 'doc-mf', 'data-mf': true } }
    }

    private button: HTMLButtonElement | null = null
    private range: Range | null = null

    render(): HTMLElement {
      this.button = document.createElement('button')
      this.button.type = 'button'
      this.button.classList.add('ce-inline-tool')
      this.button.textContent = '{ }'

      return this.button
    }

    surround(range: Range): void {
      this.range = range
      this.showPicker()
    }

    checkState(): boolean {
      return false
    }

    private showPicker(): void {
      const picker = document.createElement('div')
      picker.className = 'doc-mf-picker'

      const search = document.createElement('input')
      search.className = 'doc-tool-input'
      search.type = 'search'
      picker.append(search)

      const list = document.createElement('div')
      list.className = 'doc-mf-list'
      picker.append(list)

      const paint = () => {
        const needle = search.value.toLowerCase()
        list.replaceChildren()

        for (const field of fields.filter((f) => f.path.toLowerCase().includes(needle)).slice(0, 12)) {
          const option = document.createElement('button')
          option.type = 'button'
          option.className = 'doc-mf-option'
          const sample = document.createElement('b')
          sample.textContent = field.sample
          const path = document.createElement('em')
          path.textContent = field.path
          option.replaceChildren(sample, path)
          option.addEventListener('click', () => {
            this.insert(field)
            picker.remove()
          })
          list.append(option)
        }
      }

      search.addEventListener('input', paint)
      paint()

      document.body.append(picker)
      search.focus()

      // One dismissal path, registered after this click finishes bubbling.
      setTimeout(() => {
        document.addEventListener('click', () => picker.remove(), { once: true })
      }, 0)
    }

    private insert(field: MergeField): void {
      if (this.range === null) return

      this.range.deleteContents()
      this.range.insertNode(chip(field))
    }
  }
}
