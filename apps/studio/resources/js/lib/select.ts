/*
 * A native <select> can carry <option value="">, and thirteen of this app's
 * selects use it as a real, re-selectable choice: no discount, no template,
 * every category. reka-ui reserves the empty string for "nothing selected" —
 * SelectItem throws on value="" — so those choices need a stand-in on the
 * item while the model keeps storing '' the way the server expects.
 *
 *   <Select :model-value="toOption(form.x)" @update:model-value="v => form.x = fromOption(v)">
 *     <SelectItem :value="NONE">…</SelectItem>
 */
export const NONE = '__none'

export function toOption(value: string | null | undefined): string {
  return value === '' || value === null || value === undefined ? NONE : value
}

export function fromOption(value: unknown): string {
  return value === NONE || value === undefined || value === null ? '' : String(value)
}
