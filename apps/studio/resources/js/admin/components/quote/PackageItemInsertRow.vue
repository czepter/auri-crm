<script setup lang="ts">
import { computed, ref } from 'vue'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { catalogItemRow, newPackageItemRow, packageInsertOptions } from '@/admin/lib/packageItem'
import { Combobox, ComboboxAnchor, ComboboxContent, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxLabel } from '@/ui/combobox'

const props = defineProps<{ catalogItems: App.Catalog.Data.CatalogItemData[] }>()

const emit = defineEmits<{ insert: [row: PackageItemRow] }>()

const term = ref('')

const options = computed(() => packageInsertOptions(term.value, props.catalogItems))

const groups = computed(() =>
  [
    { key: 'groupCatalog', options: options.value.filter((option) => option.kind === 'item') },
    { key: 'groupNew', options: options.value.filter((option) => option.kind === 'new') },
  ].filter((group) => group.options.length),
)

function choose(kind: string, id: string) {
  if (kind === 'item') {
    const item = props.catalogItems.find((entry) => entry.id === id)
    if (item) emit('insert', catalogItemRow(item))
  } else {
    emit('insert', newPackageItemRow(term.value.trim()))
  }

  term.value = ''
}
</script>

<template>
  <!-- ignore-filter: gefiltert wird in packageInsertOptions(), damit Rangfolge und die
       Gruppe "Neu" prüfbar bleiben statt in der Bibliothek zu stecken. -->
  <!-- :model-value="null": die Auswahl ist ein Befehl, kein Wert — sonst schreibt Reka "new:new" ins Suchfeld. -->
  <Combobox ignore-filter :model-value="null">
    <ComboboxAnchor>
      <ComboboxInput v-model="term" :aria-label="$t('packages.itemInsert')" :placeholder="$t('packages.itemInsert')" />
    </ComboboxAnchor>
    <ComboboxContent v-if="options.length">
      <ComboboxGroup v-for="group in groups" :key="group.key">
        <ComboboxLabel>{{ $t(`packages.${group.key}`) }}</ComboboxLabel>
        <ComboboxItem
          v-for="option in group.options"
          :key="`${option.kind}-${option.id}`"
          :value="`${option.kind}:${option.id}`"
          @select="choose(option.kind, option.id)"
        >
          <span class="min-w-0 flex-1 truncate">
            {{ option.kind === 'new' ? $t('packages.itemInsertNew', { term: option.label }) : option.label }}
          </span>
          <span v-if="option.meta" class="text-small tabular-nums text-ink-2">{{ option.meta }}</span>
        </ComboboxItem>
      </ComboboxGroup>
    </ComboboxContent>
  </Combobox>
</template>
