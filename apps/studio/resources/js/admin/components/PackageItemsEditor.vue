<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Switch } from '@/ui/switch'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'

export type PackageItemRow = {
  // Leer bei einer neuen Zeile: das Bild haengt an einer Zeile, die es in
  // der Datenbank schon gibt, also erst nach dem ersten Speichern.
  id?: string
  title: string
  description: string
  quantity: number
  unitPrice: string
  selectable: boolean
  selected: boolean
  outcome: string
  hasImage: boolean
}

const props = defineProps<{ packageId?: string }>()

// Shared by Create and Edit, so the two forms cannot drift into building a
// package item two different ways.
const items = defineModel<PackageItemRow[]>({ required: true })

function addRow() {
  items.value = [
    ...items.value,
    {
      title: '',
      description: '',
      quantity: 1,
      unitPrice: '',
      selectable: false,
      selected: false,
      outcome: '',
      hasImage: false,
    },
  ]
}

// Q-10: das Bild liegt am Katalogeintrag, nicht am Angebot. Eigener Weg,
// weil das Speichern die Zeilen komplett ersetzt — ein Upload muss auf einer
// Zeile landen, die es jetzt gibt.
function upload(item: PackageItemRow, event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]

  if (!file || !item.id || !props.packageId) return

  router.post(
    `/pakete/${props.packageId}/positionen/${item.id}/bild`,
    { image: file },
    { preserveScroll: true, forceFormData: true }
  )
}

function removeRow(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}
</script>

<template>
  <div>
    <div v-for="(item, index) in items" :key="index" class="mb-6 block rounded-lg border border-border p-2.5">
      <div class="mb-4 flex items-center justify-between">
        <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('packages.itemTitle') }} {{ index + 1 }}</span>
        <Button variant="outline" tone="accent" type="button" @click="removeRow(index)">{{ $t('common.remove') }}</Button>
      </div>

      <div class="grid grid-cols-2 gap-x-6 gap-y-4">
        <Field wide>
          <Label :for="`item-title-${index}`">{{ $t('packages.itemTitle') }}</Label>
          <Input :id="`item-title-${index}`" v-model="item.title" type="text" required />
        </Field>
        <Field wide>
          <Label :for="`item-description-${index}`">{{ $t('packages.itemDescription') }}</Label>
          <Input :id="`item-description-${index}`" v-model="item.description" type="text" />
        </Field>
        <Field>
          <Label :for="`item-quantity-${index}`">{{ $t('packages.itemQuantity') }}</Label>
          <Input
            :id="`item-quantity-${index}`"
            v-model.number="item.quantity"
            class="tabular-nums"
            type="number"
            min="1"
            required
          />
        </Field>
        <Field wide>
          <Label :for="`item-outcome-${index}`">{{ $t('packages.itemOutcome') }}</Label>
          <Input :id="`item-outcome-${index}`" v-model="item.outcome" type="text" />
        </Field>
        <Field>
          <Label :for="`item-price-${index}`">
            {{ $t('packages.itemPrice') }}
          </Label>
          <InputGroup>
            <InputGroupInput
              :id="`item-price-${index}`"
              v-model="item.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('packages.itemPriceHint')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </Field>

        <Field wide>
          <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="flex items-center gap-2.5">
              <Switch :id="`item-selectable-${index}`" v-model="item.selectable" />
              <Label variant="inline" :for="`item-selectable-${index}`">{{ $t('packages.itemSelectable') }}</Label>
            </div>
            <div v-if="item.selectable" class="flex items-center gap-2.5">
              <Switch :id="`item-selected-${index}`" v-model="item.selected" />
              <Label variant="inline" :for="`item-selected-${index}`">{{ $t('packages.itemSelected') }}</Label>
            </div>

            <label v-if="item.id && packageId" class="ml-auto flex cursor-pointer items-center gap-2.5">
              <img
                v-if="item.hasImage"
                class="size-10 rounded-[6px] object-cover"
                :src="`/pakete/${packageId}/positionen/${item.id}/bild`"
                alt=""
              />
              <span class="text-[12.5px] text-accent underline">{{ $t('packages.itemImage') }}</span>
              <input class="hidden" type="file" accept="image/png,image/jpeg" @change="upload(item, $event)" />
            </label>
          </div>
        </Field>
      </div>
    </div>

    <Button variant="outline" type="button" @click="addRow">{{ $t('packages.addItem') }}</Button>
  </div>
</template>
