<script setup lang="ts">
export type QuoteItemRow = {
  title: string
  description: string
  quantity: number
  unitPrice: string
}

const items = defineModel<QuoteItemRow[]>({ required: true })

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
        <span class="kick">{{ $t('quotes.itemTitle') }} {{ index + 1 }}</span>
        <button type="button" class="abtn hot" @click="removeRow(index)">{{ $t('common.remove') }}</button>
      </div>

      <div class="fgrid">
        <div class="fld wide">
          <label :for="`quote-item-title-${index}`">{{ $t('quotes.itemTitle') }}</label>
          <input :id="`quote-item-title-${index}`" v-model="item.title" class="inp" type="text" required />
        </div>
        <div class="fld wide">
          <label :for="`quote-item-description-${index}`">{{ $t('quotes.itemDescription') }}</label>
          <input :id="`quote-item-description-${index}`" v-model="item.description" class="inp" type="text" />
        </div>
        <div class="fld">
          <label :for="`quote-item-quantity-${index}`">{{ $t('quotes.itemQuantity') }}</label>
          <input
            :id="`quote-item-quantity-${index}`"
            v-model.number="item.quantity"
            class="inp num"
            type="number"
            min="1"
            required
          />
        </div>
        <div class="fld">
          <label :for="`quote-item-price-${index}`">
            {{ $t('quotes.itemPrice') }}
          </label>
          <input
            :id="`quote-item-price-${index}`"
            v-model="item.unitPrice"
            class="inp num"
            type="text"
            inputmode="decimal"
            :placeholder="$t('quotes.itemPriceHint')"
          />
        </div>
      </div>
    </div>

    <button type="button" class="abtn" @click="addRow">{{ $t('quotes.addItem') }}</button>
  </div>
</template>
