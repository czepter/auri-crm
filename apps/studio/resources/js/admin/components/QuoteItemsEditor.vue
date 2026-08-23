<script setup lang="ts">
import { NONE, fromOption, toOption } from '@/lib/select'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

export type QuoteItemRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
  discountType: '' | 'percentage' | 'amount'
  discountValue: string
}

const items = defineModel<QuoteItemRow[]>({ required: true })

function addRow() {
  items.value = [
    ...items.value,
    { title: '', description: '', quantity: 1, unitPrice: '', discountType: '', discountValue: '' },
  ]
}

function removeRow(index: number) {
  items.value = items.value.filter((_, i) => i !== index)
}

function setDiscountType(item: QuoteItemRow, value: unknown) {
  item.discountType = fromOption(value) as QuoteItemRow['discountType']
}
</script>

<template>
  <div>
    <div v-for="(item, index) in items" :key="index" class="relative mb-6 block cursor-default rounded-md border border-border p-2.5 text-inherit no-underline">
      <div class="mb-4 flex items-center justify-between [&_[data-slot=button][data-variant=ghost]]:p-0">
        <span class="mb-[5px] text-[10px] leading-none font-semibold tracking-[0.09em] uppercase text-faint">{{ $t('quotes.itemTitle') }} {{ index + 1 }}</span>
        <Button variant="outline" tone="accent" type="button" @click="removeRow(index)">{{ $t('common.remove') }}</Button>
      </div>

      <div class="grid grid-cols-[repeat(2,minmax(0,1fr))] gap-x-6 gap-y-4">
        <div class="col-span-full flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-title-${index}`">{{ $t('quotes.itemTitle') }}</Label>
          <Input :id="`quote-item-title-${index}`" v-model="item.title" type="text" required />
        </div>
        <div class="col-span-full flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-description-${index}`">{{ $t('quotes.itemDescription') }}</Label>
          <Input :id="`quote-item-description-${index}`" v-model="item.description" type="text" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-quantity-${index}`">{{ $t('quotes.itemQuantity') }}</Label>
          <Input
            :id="`quote-item-quantity-${index}`"
            v-model.number="item.quantity"
            class="tabular-nums"
            type="number"
            min="1"
            required
          />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-price-${index}`">
            {{ $t('quotes.itemPrice') }}
          </Label>
          <InputGroup>
            <InputGroupInput
              :id="`quote-item-price-${index}`"
              v-model="item.unitPrice"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              :placeholder="$t('quotes.itemPriceHint')"
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-discount-type-${index}`">{{ $t('quotes.discountType') }}</Label>
          <Select
            :model-value="toOption(item.discountType)"
            @update:model-value="(value) => setDiscountType(item, value)"
          >
            <SelectTrigger :id="`quote-item-discount-type-${index}`">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="NONE">{{ $t('quotes.discountNone') }}</SelectItem>
              <SelectItem value="percentage">{{ $t('quotes.discountPercentage') }}</SelectItem>
              <SelectItem value="amount">{{ $t('quotes.discountAmount') }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div v-if="item.discountType" class="flex min-w-0 flex-col gap-1.5">
          <Label :for="`quote-item-discount-value-${index}`">{{ $t('quotes.discountValue') }}</Label>
          <InputGroup>
            <InputGroupInput
              :id="`quote-item-discount-value-${index}`"
              v-model="item.discountValue"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
            />
            <InputGroupAddon align="inline-end">
              {{ item.discountType === 'percentage' ? '%' : '€' }}
            </InputGroupAddon>
          </InputGroup>
        </div>
      </div>
    </div>

    <Button variant="outline" type="button" @click="addRow">{{ $t('quotes.addItem') }}</Button>
  </div>
</template>
