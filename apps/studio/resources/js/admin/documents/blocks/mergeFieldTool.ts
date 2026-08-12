// The inline tool behind the chips in the mockup. A merge field is a span
// carrying its path in data-mf and *showing the sample value* — a paragraph
// full of {{ contact.name }} is unreadable as prose, which is what staff are
// actually editing. serialize.ts turns the span back into {{ path }} via the
// data attribute, so what is stored stays exactly what MergeFieldResolver
// already understands.
type MergeField = { path: string; sample: string }

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
      search.className = 'inp'
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

      const chip = document.createElement('span')
      chip.className = 'doc-mf'
      chip.dataset.mf = field.path
      chip.contentEditable = 'false'
      chip.textContent = field.sample

      this.range.deleteContents()
      this.range.insertNode(chip)
    }
  }
}
