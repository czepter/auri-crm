/**
 * DatePicker emits update:modelValue on every keystroke — '' for anything
 * incomplete, since parse.ts's toIso() returns null while a date is still
 * half-typed. Saving straight off that event would PATCH expires_at to null
 * nine times before a tenth keystroke ever finishes a valid date. Only a
 * value the picker could actually parse is worth a request; DatePicker's own
 * onBlur already reverts unparsed text to the last committed value.
 */
export function isCompleteDate(value: string): boolean {
  return value !== ''
}
