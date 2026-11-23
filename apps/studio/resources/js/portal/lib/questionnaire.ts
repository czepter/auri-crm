type Field = App.Crm.Data.FormInstanceFieldData

export const PER_STEP = 4

// A section opens a step of its own and does not count toward PER_STEP.
export function steps(fields: Field[]): Field[][] {
  const pages: Field[][] = []
  let questions = 0

  for (const field of fields) {
    const isSection = field.type === 'section'

    if (!pages.length || (isSection && questions > 0) || (!isSection && questions === PER_STEP)) {
      pages.push([])
      questions = 0
    }

    pages[pages.length - 1].push(field)
    if (!isSection) questions++
  }

  return pages
}

// Boolean answers ride as "ja"/"" and nothing in the form shows an error for
// them, so they never block a step.
export function unanswered(field: Field, answers: Record<string, string>): boolean {
  if (!field.required || field.type === 'boolean' || field.type === 'section') return false
  if (field.type === 'file') return (field.files ?? []).length === 0

  return (answers[field.id] ?? '').trim() === ''
}
