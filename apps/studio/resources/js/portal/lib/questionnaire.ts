type Field = App.Crm.Data.FormInstanceFieldData

export const PER_STEP = 4

export function steps(fields: Field[]): Field[][] {
  return Array.from({ length: Math.ceil(fields.length / PER_STEP) }, (_, i) => fields.slice(i * PER_STEP, (i + 1) * PER_STEP))
}

// Boolean answers ride as "ja"/"" and nothing in the form shows an error for
// them, so they never block a step.
export function unanswered(field: Field, answers: Record<string, string>): boolean {
  if (!field.required || field.type === 'boolean') return false
  if (field.type === 'file') return (field.files ?? []).length === 0

  return (answers[field.id] ?? '').trim() === ''
}
