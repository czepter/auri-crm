import type { FormFieldRow } from '@/admin/components/templates/FormFieldsEditor.vue'

// Shared by the project's questionnaire cards and the create wizard, so an
// instance field turns into an editor row, and back, one way only.
export function toRow(field: App.Crm.Data.FormInstanceFieldData): FormFieldRow {
  return {
    id: field.id,
    label: field.label,
    type: field.type,
    required: field.required,
    optionsText: optionsText(field),
    fileMaxSize: field.fileMaxSize ?? 10,
    fileMultiple: field.fileMultiple,
    helpText: field.groupPhotos?.help ?? '',
    minutesPerGroup: field.groupPhotos?.minutesPerGroup ?? 2,
  }
}

export function toPayload(field: FormFieldRow) {
  return { id: field.id ?? null, label: field.label, type: field.type, required: field.required, options: options(field) }
}

function optionsText(field: App.Crm.Data.FormInstanceFieldData): string {
  if (field.type === 'file') return (field.fileFormats ?? []).join('\n')
  if (field.type === 'group_photos') return (field.groupPhotos?.suggestions ?? []).join('\n')

  return (field.options ?? []).join('\n')
}

// select stores its choices, file its allowed formats and group_photos its
// builder configuration in the one options column — see
// FormFieldType::optionRules().
function options(field: FormFieldRow) {
  const list = field.optionsText.split('\n').map((o) => o.trim()).filter(Boolean)

  if (field.type === 'file') {
    return { formats: list, maxSize: field.fileMaxSize ?? 10, multiple: field.fileMultiple ?? false }
  }

  if (field.type === 'group_photos') {
    return { help: field.helpText?.trim() || null, suggestions: list, minutesPerGroup: field.minutesPerGroup ?? 2 }
  }

  return field.type === 'select' ? list : null
}
