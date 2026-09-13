<script setup lang="ts">
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Ellipsis, GripVertical } from 'lucide-vue-next'
import type { PricingMode, QuoteRow } from './quotePayload'
import { quoteRowToLine } from './quotePayload'
import { lineAmount } from './quoteTotals'
import { eurosToCents, formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { ChoiceGroup } from '@/ui/choice-group'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/ui/dropdown-menu'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Label } from '@/ui/label'
import QuoteCard from './QuoteCard.vue'
import DiscountInput from './DiscountInput.vue'

const props = defineProps<{
  mode: PricingMode
  quoteId: string
  canManageCatalog: boolean
  row: QuoteRow
}>()

const open = defineModel<boolean>('open', { required: true })

const emit = defineEmits<{ change: []; remove: []; moveUp: []; moveDown: []; dragStart: []; dragEnd: [] }>()

const portalStatus = computed(() => (props.row.selectable ? (props.row.selected ? 'preselected' : 'pickable') : 'included'))
const portalLabels: Record<string, string> = {
  included: 'quotes.portalIncluded',
  preselected: 'quotes.preselected',
  pickable: 'quotes.pickable',
}

function setPortalStatus(value: string) {
  props.row.selectable = value !== 'included'
  props.row.selected = value === 'preselected'
  emit('change')
}

const amount = computed(() => lineAmount(quoteRowToLine(props.row)))

// Der Griff allein wäre das Ziehbild; gezogen wird die ganze Karte.
function onDragStart(event: DragEvent) {
  const card = (event.currentTarget as HTMLElement).closest('[data-position]')

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    if (card) event.dataTransfer.setDragImage(card, 16, 24)
  }

  emit('dragStart')
}

const packageOpen = ref(false)
const packageForm = useForm({ name: '' })

function saveAsPackage() {
  packageForm
    .transform((data) => ({
      name: data.name,
      item: {
        title: props.row.title,
        description: props.row.description || null,
        quantity: props.row.quantity,
        unit_price: eurosToCents(props.row.unitPrice),
        selectable: props.row.selectable,
        selected: props.row.selected,
        outcome: props.row.outcome || null,
      },
    }))
    .post(`/angebote/${props.quoteId}/positionen/als-paket`, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        packageOpen.value = false
        packageForm.reset()
      },
    })
}
</script>

<template>
  <QuoteCard v-model:open="open" :title="row.title || $t('quotes.itemTitle')">
    <template #handle>
      <button
        type="button"
        draggable="true"
        class="-mr-3 ml-2 flex h-8 w-6 flex-none cursor-grab items-center justify-center rounded-[6px] text-faint hover:text-ink-2 active:cursor-grabbing"
        :aria-label="$t('quotes.move')"
        @dragstart="onDragStart"
        @dragend="emit('dragEnd')"
        @keydown.up.prevent="emit('moveUp')"
        @keydown.down.prevent="emit('moveDown')"
      >
        <GripVertical class="size-4" />
      </button>
    </template>

    <template #summary>
      <span
        v-if="mode === 'choice'"
        class="rounded-[10px] px-2 py-0.5 text-[11px] leading-none font-semibold"
        :class="portalStatus === 'included' ? 'bg-sunk-2 text-ink-2' : 'bg-[var(--accent-bg)] text-[var(--accent)]'"
      >{{ $t(portalLabels[portalStatus]) }}</span>
      <span class="tabular-nums">{{ formatEuros(amount) }}</span>
    </template>

    <div class="flex flex-col gap-4">
      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.itemTitle') }}</Label>
        <Input v-model="row.title" type="text" @blur="emit('change')" />
      </div>

      <div class="flex min-w-0 flex-col gap-1.5">
        <Label>{{ $t('quotes.itemDescription') }}</Label>
        <Input v-model="row.description" type="text" @blur="emit('change')" />
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemQuantity') }}</Label>
          <Input v-model.number="row.quantity" class="tabular-nums" type="number" min="1" @blur="emit('change')" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.itemPrice') }}</Label>
          <InputGroup>
            <InputGroupInput
              v-model="row.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('quotes.itemPriceHint')"
              @blur="emit('change')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label>{{ $t('quotes.discount') }}</Label>
          <DiscountInput v-model="row.discount" @change="emit('change')" />
        </div>
      </div>

      <ChoiceGroup
        v-if="mode === 'choice'"
        :label="$t('quotes.inPortal')"
        :model-value="portalStatus"
        :options="Object.entries(portalLabels).map(([value, label]) => ({ value, label: $t(label) }))"
        variant="segmented"
        class="self-start"
        @update:model-value="setPortalStatus"
      />

      <div class="flex items-center justify-between pt-1">
        <div class="flex items-center gap-1">
          <Button
            variant="ghost"
            type="button"
            class="rounded-[6px] px-2 text-destructive hover:bg-late-bg hover:text-destructive"
            @click="emit('remove')"
          >
            {{ $t('common.remove') }}
          </Button>

          <DropdownMenu v-if="canManageCatalog">
            <DropdownMenuTrigger as-child>
              <Button variant="ghost" type="button" class="rounded-[6px] px-2 hover:bg-sunk-2" :aria-label="$t('project.more')">
                <Ellipsis />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              <DropdownMenuItem @select="packageOpen = true">{{ $t('quotes.saveAsPackage') }}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <span class="text-[15px] font-semibold tabular-nums">{{ formatEuros(amount) }}</span>
      </div>
    </div>
  </QuoteCard>

  <Dialog v-model:open="packageOpen">
    <DialogContent class="max-w-[420px]">
      <DialogHeader>
        <DialogTitle>{{ $t('quotes.saveAsPackage') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form :id="`item-package-${quoteId}`" class="flex flex-col gap-1.5" @submit.prevent="saveAsPackage">
          <Label :for="`item-package-name-${quoteId}`">{{ $t('quotes.packageName') }}</Label>
          <Input :id="`item-package-name-${quoteId}`" v-model="packageForm.name" type="text" required />
          <p v-if="packageForm.errors.name" class="text-[12px] text-destructive">{{ packageForm.errors.name }}</p>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button type="submit" :form="`item-package-${quoteId}`" :disabled="packageForm.processing">{{ $t('common.save') }}</Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
