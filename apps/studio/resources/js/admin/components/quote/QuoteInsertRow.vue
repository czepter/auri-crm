<script setup lang="ts">
import { computed, ref } from 'vue'
import type { QuoteRow } from '@/admin/components/quote/quotePayload'
import { insertOptions, itemRow, packageRows, recentOptions } from '@/admin/lib/insert'
import { Combobox, ComboboxAnchor, ComboboxContent, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxLabel } from '@/ui/combobox'

const props = defineProps<{
  packages: App.Catalog.Data.PackageData[]
  catalogItems: App.Catalog.Data.CatalogItemData[]
}>()

const emit = defineEmits<{ insert: [rows: QuoteRow[]] }>()

const term = ref('')

// Browserlokal statt am Projekt: "zuletzt verwendet" ist eine Gewohnheit der
// Person am Rechner, kein Attribut des Angebots.
const RECENT_KEY = 'quotes.recentInserts'
const MAX_RECENT = 6

function loadRecent(): string[] {
  try {
    return JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]')
  } catch {
    return []
  }
}

const recentIds = ref<string[]>(loadRecent())

const options = computed(() => term.value.trim()
  ? insertOptions(term.value, props.packages, props.catalogItems)
  : recentOptions(recentIds.value, props.packages, props.catalogItems))

const groups = computed(() => term.value.trim()
  ? [
      { key: 'groupPackages', options: options.value.filter((option) => option.kind === 'package') },
      { key: 'groupItems', options: options.value.filter((option) => option.kind === 'item') },
      { key: 'groupNew', options: options.value.filter((option) => option.kind === 'new') },
    ].filter((group) => group.options.length)
  : (options.value.length ? [{ key: 'groupRecent', options: options.value }] : []))

function remember(kind: string, id: string) {
  const key = `${kind}:${id}`
  recentIds.value = [key, ...recentIds.value.filter((entry) => entry !== key)].slice(0, MAX_RECENT)
  localStorage.setItem(RECENT_KEY, JSON.stringify(recentIds.value))
}

function choose(kind: string, id: string) {
  if (kind === 'package') {
    const pkg = props.packages.find((entry) => entry.id === id)
    emit('insert', pkg ? packageRows(pkg) : [])
    if (pkg) remember(kind, id)
  } else if (kind === 'item') {
    const item = props.catalogItems.find((entry) => entry.id === id)
    emit('insert', item ? [itemRow(item)] : [])
    if (item) remember(kind, id)
  } else {
    emit('insert', [{ title: term.value.trim(), description: '', quantity: 1, unitPrice: '', discount: '', vatRate: null, selectable: false, selected: false, outcome: '', hasImage: false }])
  }

  term.value = ''
}
</script>

<template>
  <!-- ignore-filter: gefiltert wird in insertOptions(), damit Rangfolge und die
       Gruppe "Neu" prüfbar bleiben statt in der Bibliothek zu stecken. -->
  <!-- :model-value="null": die Auswahl ist ein Befehl, kein Wert — sonst schreibt Reka "new:new" ins Suchfeld. -->
  <Combobox ignore-filter :model-value="null">
    <ComboboxAnchor>
      <ComboboxInput v-model="term" :aria-label="$t('quotes.insert')" :placeholder="$t('quotes.insert')" />
    </ComboboxAnchor>
    <ComboboxContent v-if="options.length">
      <ComboboxGroup v-for="group in groups" :key="group.key">
        <ComboboxLabel>{{ $t(`quotes.${group.key}`) }}</ComboboxLabel>
        <ComboboxItem
          v-for="option in group.options"
          :key="`${option.kind}-${option.id}`"
          :value="`${option.kind}:${option.id}`"
          @select="choose(option.kind, option.id)"
        >
          <span class="min-w-0 flex-1 truncate">
            {{ option.kind === 'new' ? $t('quotes.insertNew', { term: option.label }) : option.label }}
          </span>
          <span v-if="option.meta" class="text-small tabular-nums text-ink-2">{{ option.meta }}</span>
        </ComboboxItem>
      </ComboboxGroup>
    </ComboboxContent>
  </Combobox>
</template>
