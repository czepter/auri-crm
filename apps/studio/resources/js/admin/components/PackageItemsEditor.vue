<script setup lang="ts">
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'

export type PackageItemRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
}

// Shared by Create and Edit, so the two forms cannot drift into building a
// package item two different ways.
const items = defineModel<PackageItemRow[]>({ required: true })

function addRow() {
  items.value = [...items.value, { title: '', description: '', quantity: 1, unitPrice: '' }]
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
      </div>
    </div>

    <Button variant="outline" type="button" @click="addRow">{{ $t('packages.addItem') }}</Button>
  </div>
</template>
