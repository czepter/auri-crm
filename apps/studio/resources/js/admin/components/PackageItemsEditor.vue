<script setup lang="ts">
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
        <button type="button" class="abtn hot" @click="removeRow(index)">{{ $t('common.remove') }}</button>
      </div>

      <div class="fgrid">
        <div class="fld wide">
          <label :for="`item-title-${index}`">{{ $t('packages.itemTitle') }}</label>
          <input :id="`item-title-${index}`" v-model="item.title" class="inp" type="text" required />
        </div>
        <div class="fld wide">
          <label :for="`item-description-${index}`">{{ $t('packages.itemDescription') }}</label>
          <input :id="`item-description-${index}`" v-model="item.description" class="inp" type="text" />
        </div>
        <div class="fld">
          <label :for="`item-quantity-${index}`">{{ $t('packages.itemQuantity') }}</label>
          <input
            :id="`item-quantity-${index}`"
            v-model.number="item.quantity"
            class="inp num"
            type="number"
            min="1"
            required
          />
        </div>
        <div class="fld">
          <label :for="`item-price-${index}`">
            {{ $t('packages.itemPrice') }}
          </label>
          <input
            :id="`item-price-${index}`"
            v-model="item.unitPrice"
            class="inp num"
            type="text"
            inputmode="decimal"
            :placeholder="$t('packages.itemPriceHint')"
          />
        </div>
      </div>
    </div>

    <button type="button" class="abtn" @click="addRow">{{ $t('packages.addItem') }}</button>
  </div>
</template>
