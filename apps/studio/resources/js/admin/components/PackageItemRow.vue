<script setup lang="ts">
import { computed } from 'vue'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import { lineTotal } from '@/admin/lib/packageItem'
import { formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import DragHandle from '@/admin/components/quote/DragHandle.vue'
import QuoteCard from '@/admin/components/quote/QuoteCard.vue'

const props = defineProps<{ packageId?: string; row: PackageItemRow }>()

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{ change: []; remove: []; moveUp: []; moveDown: []; dragStart: []; dragEnd: []; upload: [file: File] }>()

const portalStatus = computed(() => (props.row.selectable ? (props.row.selected ? 'preselected' : 'pickable') : 'included'))
const portalLabels: Record<string, string> = {
  included: 'packages.itemPortalIncluded',
  preselected: 'packages.itemPortalPreselected',
  pickable: 'packages.itemPortalPickable',
}

function setPortalStatus(value: string) {
  props.row.selectable = value !== 'included'
  props.row.selected = value === 'preselected'
  emit('change')
}

const amount = computed(() => lineTotal(props.row))

function upload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (file) emit('upload', file)
}
</script>

<template>
  <QuoteCard v-model:open="open" :title="row.title || $t('packages.itemTitle')">
    <template #handle>
      <DragHandle @drag-start="emit('dragStart')" @drag-end="emit('dragEnd')" @move-up="emit('moveUp')" @move-down="emit('moveDown')" />
    </template>

    <template #summary>
      <span
        class="rounded-[10px] px-2 py-0.5 text-[11px] leading-none font-semibold"
        :class="portalStatus === 'included' ? 'bg-sunk-2 text-ink-2' : 'bg-[var(--accent-bg)] text-[var(--accent)]'"
      >{{ $t(portalLabels[portalStatus]) }}</span>
      <span class="tabular-nums">{{ formatEuros(amount) }}</span>
    </template>

    <div class="flex flex-col gap-4">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('packages.itemTitle') }}</Label>
        <Input v-model="row.title" type="text" @blur="emit('change')" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('packages.itemDescription') }}</Label>
        <Input v-model="row.description" type="text" @blur="emit('change')" />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('packages.itemQuantity') }}</Label>
          <Input v-model.number="row.quantity" class="tabular-nums" type="number" min="1" @blur="emit('change')" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('packages.itemPrice') }}</Label>
          <InputGroup>
            <InputGroupInput
              v-model="row.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('packages.itemPriceHint')"
              @blur="emit('change')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
      </div>

      <ChoiceGroup
        :label="$t('packages.itemPortalStatus')"
        :model-value="portalStatus"
        :options="Object.entries(portalLabels).map(([value, label]) => ({ value, label: $t(label) }))"
        variant="segmented"
        class="self-start"
        @update:model-value="setPortalStatus"
      />

      <!-- Q-10: das Bild liegt am Katalogeintrag, nicht am Paket. Erst nach dem
           ersten Speichern, weil ein Upload auf einer Zeile landet, die es in
           der Datenbank schon gibt. -->
      <label v-if="row.id && packageId" class="flex w-fit cursor-pointer items-center gap-2.5">
        <img
          v-if="row.hasImage"
          class="size-10 rounded-[6px] object-cover"
          :src="`/pakete/${packageId}/positionen/${row.id}/bild`"
          alt=""
        />
        <span class="text-[12.5px] text-[var(--accent)] underline">{{ $t('packages.itemImage') }}</span>
        <input class="hidden" type="file" accept="image/png,image/jpeg" @change="upload">
      </label>

      <div class="flex items-center justify-between pt-1">
        <Button
          variant="ghost"
          type="button"
          class="rounded-[6px] px-2 text-destructive hover:bg-late-bg hover:text-destructive"
          @click="emit('remove')"
        >
          {{ $t('common.remove') }}
        </Button>
        <span class="text-[15px] font-semibold tabular-nums">{{ formatEuros(amount) }}</span>
      </div>
    </div>
  </QuoteCard>
</template>
