// @vitest-environment happy-dom
import { afterEach, describe, expect, it } from 'vitest'
import { focusFirstError } from './focusInvalid'

const field = (id: string, invalid: boolean) =>
  `<div data-field ${invalid ? 'data-invalid' : ''}><input id="${id}"></div>`

afterEach(() => (document.body.innerHTML = ''))

describe('focusFirstError', () => {
  it('focuses the first field that shows an error', () => {
    document.body.innerHTML = `<form>${field('a', false)}${field('b', true)}${field('c', true)}</form><button id="go">Speichern</button>`
    document.getElementById('go')!.focus()

    focusFirstError()

    expect(document.activeElement?.id).toBe('b')
  })

  it('prefers the open dialog over the page behind it', () => {
    document.body.innerHTML = `${field('page', true)}<div role="dialog">${field('inside', true)}</div>`

    focusFirstError()

    expect(document.activeElement?.id).toBe('inside')
  })

  it('leaves the focus with whoever is typing', () => {
    document.body.innerHTML = `${field('typing', false)}${field('wrong', true)}`
    document.getElementById('typing')!.focus()

    focusFirstError()

    expect(document.activeElement?.id).toBe('typing')
  })
})
