<script setup lang="ts">
import { ref } from 'vue'
import { Combobox, ComboboxAnchor, ComboboxContent, ComboboxInput, ComboboxItem } from '@/ui/combobox'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Switch } from '@/ui/switch'
import { Textarea } from '@/ui/textarea'

export type ProjectFieldValues = Record<string, string | number | boolean | string[] | null>

type Field = {
  id: string
  key: string
  label: string
  type: string
  typeLabel: string
  options: string[]
  unit: string | null
  required: boolean
  allowNewOptions: boolean
  isActive: boolean
}

defineProps<{
  fields: Field[]
  editing: boolean
  errors: Record<string, string>
}>()

// Ein Objekt, Schlüssel = ProjectField.key, wie es auch gespeichert wird —
// die Zeile schreibt direkt hinein.
const values = defineModel<ProjectFieldValues>({ required: true })

/** Was gelesen wird, wenn niemand bearbeitet. Leer ist „—“, nie eine fehlende Zeile. */
function read(field: Field): string {
  const value = values.value[field.key]

  if (value === undefined || value === null || value === '') return '—'

  if (field.type === 'boolean') return value ? 'Ja' : 'Nein'
  if (field.type === 'date') return new Date(String(value)).toLocaleDateString('de-DE')
  if (field.type === 'number') return field.unit ? `${value} ${field.unit}` : String(value)

  return String(value)
}

function tags(field: Field): string[] {
  const value = values.value[field.key]

  return Array.isArray(value) ? (value as string[]) : []
}

function removeTag(field: Field, tag: string) {
  values.value[field.key] = tags(field).filter((one) => one !== tag)
}

function addTag(field: Field, event: Event) {
  const input = event.target as HTMLInputElement
  const tag = input.value.trim()

  if (tag === '' || tags(field).includes(tag)) return

  values.value[field.key] = [...tags(field), tag]
  input.value = ''
}

// Was der Nutzer gerade tippt, je Feld. Undefined, solange er nichts getippt
// hat — dann zeigt das Feld den gespeicherten Wert, und die Liste steht
// ungefiltert offen.
const term = ref<Record<string, string | undefined>>({})

function display(field: Field): string {
  return term.value[field.key] ?? String(values.value[field.key] ?? '')
}

function choices(field: Field): string[] {
  const typed = (term.value[field.key] ?? '').trim()
  const known = field.options.filter((option) => option.toLowerCase().includes(typed.toLowerCase()))

  return field.allowNewOptions && typed !== '' && !field.options.includes(typed) ? [...known, typed] : known
}

function choose(field: Field, option: string) {
  values.value[field.key] = option
  term.value[field.key] = option
}
</script>

<template>
  <div>
    <div
      v-for="field in fields"
      :key="field.id"
      class="flex items-center gap-4 border-b border-border py-[9px] last:border-b-0"
    >
      <span class="w-[164px] flex-none text-small leading-[1.47] text-ink-2">{{ field.label }}</span>

      <div class="min-w-0 flex-1">
        <template v-if="!editing">
          <span v-if="field.type === 'tags'" class="flex flex-wrap gap-1.5">
            <span
              v-for="tag in tags(field)"
              :key="tag"
              class="rounded-[var(--r-sm)] bg-sunk-2 px-2.5 py-1 text-small leading-none"
            >{{ tag }}</span>
            <span v-if="!tags(field).length" class="text-ui leading-[1.47]">—</span>
          </span>
          <span v-else class="text-ui leading-[1.47]">{{ read(field) }}</span>
        </template>

        <template v-else>
          <Textarea
            v-if="field.type === 'textarea'"
            :model-value="(values[field.key] as string) ?? ''"
            @update:model-value="values[field.key] = $event"
          />

          <InputGroup v-else-if="field.type === 'number' && field.unit" class="max-w-[280px]">
            <InputGroupInput
              class="tabular-nums"
              :model-value="(values[field.key] as string) ?? ''"
              type="number"
              @update:model-value="values[field.key] = $event"
            />
            <InputGroupAddon align="inline-end">{{ field.unit }}</InputGroupAddon>
          </InputGroup>

          <Input
            v-else-if="field.type === 'number'"
            class="max-w-[280px] tabular-nums"
            :model-value="(values[field.key] as string) ?? ''"
            type="number"
            @update:model-value="values[field.key] = $event"
          />

          <Input
            v-else-if="field.type === 'date'"
            class="max-w-[280px]"
            :model-value="(values[field.key] as string) ?? ''"
            type="date"
            @update:model-value="values[field.key] = $event"
          />

          <Switch
            v-else-if="field.type === 'boolean'"
            :model-value="Boolean(values[field.key])"
            @update:model-value="values[field.key] = $event"
          />

          <!-- Eine Auswahl, die wachsen darf, ist ein Suchfeld mit „anlegen“;
               eine geschlossene bleibt ein Select. -->
          <Combobox
            v-else-if="field.type === 'select' && field.allowNewOptions"
            class="max-w-[280px]"
            ignore-filter
          >
            <ComboboxAnchor>
              <ComboboxInput
                :model-value="display(field)"
                @update:model-value="term[field.key] = String($event)"
              />
            </ComboboxAnchor>
            <ComboboxContent v-if="choices(field).length">
              <ComboboxItem
                v-for="option in choices(field)"
                :key="option"
                :value="option"
                @select="choose(field, option)"
              >
                {{ field.options.includes(option) ? option : $t('project.fieldNewOption', { term: option }) }}
              </ComboboxItem>
            </ComboboxContent>
          </Combobox>

          <Select
            v-else-if="field.type === 'select'"
            :model-value="(values[field.key] as string) ?? ''"
            @update:model-value="values[field.key] = String($event)"
          >
            <SelectTrigger class="max-w-[280px]"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in field.options" :key="option" :value="option">{{ option }}</SelectItem>
            </SelectContent>
          </Select>

          <div
            v-else-if="field.type === 'tags'"
            class="flex max-w-[420px] flex-wrap items-center gap-1.5 rounded-md bg-sunk-2 px-2 py-1.5"
          >
            <span
              v-for="tag in tags(field)"
              :key="tag"
              class="inline-flex items-center gap-1.5 rounded-[var(--r-sm)] bg-background px-2.5 py-1 text-small leading-none"
            >
              {{ tag }}
              <button
                :aria-label="$t('project.fieldTagRemove', { tag })"
                class="cursor-pointer border-0 bg-transparent p-0 text-ink-2"
                type="button"
                @click="removeTag(field, tag)"
              >×</button>
            </span>
            <input :aria-label="$t('project.fieldTagAdd')"
              class="min-w-[120px] flex-1 border-0 bg-transparent text-small leading-[1.35] outline-none placeholder:text-faint"
              :list="`tags-${field.id}`"
              :placeholder="$t('project.fieldTagAdd')"
              type="text"
              @change="addTag(field, $event)"
              @keydown.enter.prevent="addTag(field, $event)"
            />
            <datalist :id="`tags-${field.id}`">
              <option v-for="option in field.options" :key="option" :value="option" />
            </datalist>
          </div>

          <Input
            v-else
            class="max-w-[420px]"
            :model-value="(values[field.key] as string) ?? ''"
            type="text"
            @update:model-value="values[field.key] = $event"
          />

          <p
            v-if="errors[`fields.${field.key}`]"
            class="mt-1 text-small leading-[1.45] text-destructive"
          >{{ errors[`fields.${field.key}`] }}</p>
        </template>
      </div>
    </div>
  </div>
</template>
