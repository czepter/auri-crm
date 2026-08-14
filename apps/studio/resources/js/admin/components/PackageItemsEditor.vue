<script setup lang="ts">
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
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
    <div v-for="(item, index) in items" :key="index" class="card static grp">
      <div class="cardhead">
        <span class="kick">{{ $t('packages.itemTitle') }} {{ index + 1 }}</span>
        <Button variant="outline" tone="accent" type="button" @click="removeRow(index)">{{ $t('common.remove') }}</Button>
      </div>

      <div class="fgrid">
        <div class="fld wide">
          <Label :for="`item-title-${index}`">{{ $t('packages.itemTitle') }}</Label>
          <Input :id="`item-title-${index}`" v-model="item.title" type="text" required />
        </div>
        <div class="fld wide">
          <Label :for="`item-description-${index}`">{{ $t('packages.itemDescription') }}</Label>
          <Input :id="`item-description-${index}`" v-model="item.description" type="text" />
        </div>
        <div class="fld">
          <Label :for="`item-quantity-${index}`">{{ $t('packages.itemQuantity') }}</Label>
          <Input
            :id="`item-quantity-${index}`"
            v-model.number="item.quantity"
            class="tabular-nums"
            type="number"
            min="1"
            required
          />
        </div>
        <div class="fld">
          <Label :for="`item-price-${index}`">
            {{ $t('packages.itemPrice') }}
          </Label>
          <Input
            :id="`item-price-${index}`"
            v-model="item.unitPrice"
            class="tabular-nums"
            type="text"
            inputmode="decimal"
            :placeholder="$t('packages.itemPriceHint')"
          />
        </div>
      </div>
    </div>

    <Button variant="outline" type="button" @click="addRow">{{ $t('packages.addItem') }}</Button>
  </div>
</template>
