// Die Bausteine, die ein Angebot oder eine Rechnung braucht und die Editor.js
// nicht mitbringt: Zeilentabellen (Positionen, Ablauf, Nutzungsrechte …),
// Ankreuzlisten, Kleingedrucktes und der Seitenumbruch.
//
// Wie customTools.ts: eine Klasse je Baustein, `save()` liefert genau die
// Form, die serialize.ts liest. Die Tabellen entstehen aus einer Fabrik, weil
// sie sich nur in ihren Spalten unterscheiden (rowSpecs.ts).

import { sanitizeInline } from './sanitizeInline'
import type { RowSpec } from './rowSpecs'

type Row = Record<string, string>

function cell(placeholder: string, value: string, className: string): HTMLElement {
  const element = document.createElement('div')
  element.className = className
  element.contentEditable = 'true'
  element.dataset.placeholder = placeholder
  element.innerHTML = sanitizeInline(value)

  return element
}

function button(label: string, onClick: () => void, className = 'doc-rowbtn'): HTMLButtonElement {
  const element = document.createElement('button')
  element.type = 'button'
  element.className = className
  element.textContent = label
  element.addEventListener('click', onClick)

  return element
}

/** Inline-Auszeichnung, die in jeder Zelle erlaubt bleibt — inkl. Feld-Chip. */
const INLINE = { b: {}, i: {}, a: { href: true }, span: { class: 'doc-mf', 'data-mf': true } }

/**
 * data: { rows: Array<Record<string, string>> }
 *
 * Eine Zeile ist ein Objekt mit den Schlüsseln der Spezifikation, kein Array:
 * eine später eingefügte Spalte verschiebt sonst jede gespeicherte Zeile.
 */
export function rowsTool(spec: RowSpec) {
  return class RowsTool {
    static get toolbox() {
      return { title: spec.title, icon: spec.icon }
    }

    static get sanitize() {
      return { rows: false }
    }

    private element: HTMLElement
    private rows: Row[]

    constructor({ data }: { data: { rows?: Row[] } }) {
      this.rows = Array.isArray(data?.rows) && data.rows.length > 0 ? data.rows.map((row) => ({ ...row })) : [this.blank()]
      this.element = document.createElement('div')
      this.element.className = 'doc-rows'
      this.paint()
    }

    private blank(): Row {
      return Object.fromEntries(spec.columns.map((column) => [column.key, ''])) as Row
    }

    private paint(): void {
      this.element.replaceChildren()

      const head = document.createElement('div')
      head.className = 'doc-rows-head'
      head.style.gridTemplateColumns = `repeat(${spec.columns.length}, minmax(0, 1fr)) 28px`

      for (const column of spec.columns) {
        const label = document.createElement('span')
        label.textContent = column.label
        head.append(label)
      }

      head.append(document.createElement('span'))
      this.element.append(head)

      this.rows.forEach((row, index) => {
        const line = document.createElement('div')
        line.className = 'doc-rows-line'
        line.style.gridTemplateColumns = `repeat(${spec.columns.length}, minmax(0, 1fr)) 28px`

        for (const column of spec.columns) {
          const field = cell(column.label, row[column.key] ?? '', `doc-editable${column.numeric ? ' doc-num' : ''}`)
          field.addEventListener('input', () => {
            this.rows[index][column.key] = field.innerHTML
          })
          line.append(field)
        }

        line.append(
          button('×', () => {
            this.rows.splice(index, 1)

            if (this.rows.length === 0) this.rows.push(this.blank())

            this.paint()
          }),
        )

        this.element.append(line)
      })

      this.element.append(
        button('Zeile', () => {
          this.rows.push(this.blank())
          this.paint()
        }, 'doc-rowbtn doc-rowadd'),
      )
    }

    render(): HTMLElement {
      return this.element
    }

    save(): { rows: Row[] } {
      return { rows: this.rows }
    }
  }
}

/** data: { items: Array<{ text: string, checked: boolean }> } — zum Ankreuzen auf Papier. */
export class ChecklistTool {
  static get toolbox() {
    return { title: 'Ankreuzliste', icon: '<b>☐</b>' }
  }

  static get sanitize() {
    return { items: false }
  }

  private element: HTMLElement
  private items: { text: string; checked: boolean }[]

  constructor({ data }: { data: { items?: { text: string; checked: boolean }[] } }) {
    this.items = data?.items?.length ? data.items.map((item) => ({ ...item })) : [{ text: '', checked: false }]
    this.element = document.createElement('div')
    this.element.className = 'doc-checklist-tool'
    this.paint()
  }

  private paint(): void {
    this.element.replaceChildren()

    this.items.forEach((item, index) => {
      const line = document.createElement('div')
      line.className = 'doc-check-line'

      const box = document.createElement('input')
      box.type = 'checkbox'
      box.checked = item.checked
      box.addEventListener('change', () => {
        this.items[index].checked = box.checked
      })

      const text = cell('Option', item.text, 'doc-editable')
      text.addEventListener('input', () => {
        this.items[index].text = text.innerHTML
      })

      line.append(
        box,
        text,
        button('×', () => {
          this.items.splice(index, 1)

          if (this.items.length === 0) this.items.push({ text: '', checked: false })

          this.paint()
        }),
      )

      this.element.append(line)
    })

    this.element.append(
      button('Option', () => {
        this.items.push({ text: '', checked: false })
        this.paint()
      }, 'doc-rowbtn doc-rowadd'),
    )
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { items: { text: string; checked: boolean }[] } {
    return { items: this.items }
  }
}

/** data: { text: string } — Widerruf, Hinweise, Haftung: kleiner gesetzt, gleiche Sprache. */
export class FinePrintTool {
  static get toolbox() {
    return { title: 'Kleingedrucktes', icon: '<b>¶</b>' }
  }

  static get sanitize() {
    return { text: INLINE }
  }

  private element: HTMLElement

  constructor({ data }: { data: { text?: string } }) {
    this.element = cell('Kleingedrucktes', data?.text ?? '', 'doc-editable doc-fineprint')
  }

  render(): HTMLElement {
    return this.element
  }

  save(): { text: string } {
    return { text: this.element.innerHTML }
  }
}

/** data: {} — trennt zwei Seiten, sichtbar als Linie im Editor. */
export class PageBreakTool {
  static get toolbox() {
    return { title: 'Seitenumbruch', icon: '<b>⇥</b>' }
  }

  private element: HTMLElement

  constructor() {
    this.element = document.createElement('div')
    this.element.className = 'doc-pagebreak-tool'
    this.element.textContent = 'Seitenumbruch'
  }

  render(): HTMLElement {
    return this.element
  }

  save(): Record<string, never> {
    return {}
  }
}
