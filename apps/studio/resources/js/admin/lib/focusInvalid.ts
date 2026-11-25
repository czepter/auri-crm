const FOCUSABLE = 'input:not([type=hidden]), textarea, [role=combobox]'

/** The first invalid control of its form takes the focus; a Field around it hands it to its visible control. */
export function focusInvalid(control: Element) {
  if ((control as HTMLInputElement).form?.querySelector(':is(input, select, textarea):invalid') !== control) return

  const scope = (control.closest('[data-field]') ?? control) as HTMLElement

  ;(scope.matches(FOCUSABLE) ? scope : scope.querySelector<HTMLElement>(FOCUSABLE) ?? control as HTMLElement).focus()
}

/** After a request came back with errors: the first Field showing one, in the topmost dialog if one is open. Someone typing elsewhere keeps the focus. */
export function focusFirstError() {
  if (document.activeElement?.matches('input, textarea, [contenteditable]')) return

  const dialogs = document.querySelectorAll('[role=dialog], [role=alertdialog]')
  const scope = dialogs.length ? dialogs[dialogs.length - 1] : document

  scope.querySelector('[data-field][data-invalid]')?.querySelector<HTMLElement>(FOCUSABLE)?.focus()
}
