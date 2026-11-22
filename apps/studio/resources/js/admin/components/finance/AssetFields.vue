<script setup lang="ts">
import { ChoiceGroup } from '@/ui/choice-group'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

export type AssetForm = {
  name: string
  acquired_at: string
  cost_net: string
  cost_gross: string
  method: string
  useful_life_months: string
  afa_category: string
}

defineProps<{
  errors: Record<string, string>
  afaCategories: { key: string; label: string; months: number }[]
  inventoryNo?: string | null
}>()
const asset = defineModel<AssetForm>('asset', { required: true })
const emit = defineEmits<{ method: []; costs: [] }>()

function pickCategory(key: string, categories: { key: string; months: number }[]) {
  asset.value.afa_category = key === 'none' ? '' : key
  const months = categories.find((category) => category.key === key)?.months
  if (months) asset.value.useful_life_months = String(months)
}
</script>

<template>
  <div class="grid gap-4 rounded-lg border border-border p-4">
    <Field v-slot="{ id }" :label="$t('financeExpense.assetName')" :error="errors['asset.name']">
      <Input :id="id" v-model="asset.name" type="text" maxlength="200" />
    </Field>

    <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
      <Field v-slot="{ id }" :label="$t('financeExpense.acquiredAt')" :error="errors['asset.acquired_at']">
        <DatePicker :id="id" v-model="asset.acquired_at" />
      </Field>
      <Field v-if="inventoryNo" v-slot="{ id }" :label="$t('financeExpense.inventoryNo')">
        <Input :id="id" :model-value="inventoryNo" type="text" readonly class="tabular-nums" />
      </Field>
    </div>

    <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
      <Field v-slot="{ id }" :label="$t('financeExpense.costNet')" :error="errors['asset.cost_net']">
        <InputGroup>
          <InputGroupInput :id="id" v-model="asset.cost_net" class="tabular-nums" type="text" inputmode="decimal" @input="emit('costs')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
      </Field>
      <Field v-slot="{ id }" :label="$t('financeExpense.costGross')" :error="errors['asset.cost_gross']">
        <InputGroup>
          <InputGroupInput :id="id" v-model="asset.cost_gross" class="tabular-nums" type="text" inputmode="decimal" @input="emit('costs')" />
          <InputGroupAddon align="inline-end">€</InputGroupAddon>
        </InputGroup>
      </Field>
    </div>

    <Field :label="$t('financeExpense.method')" :error="errors['asset.method']">
      <ChoiceGroup
        :model-value="asset.method"
        :options="[{ value: 'gwg', label: $t('financeExpense.gwg') }, { value: 'linear', label: $t('financeExpense.linear') }]"
        @update:model-value="(value) => { asset.method = value; emit('method') }"
      />
    </Field>

    <div v-if="asset.method === 'linear'" class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
      <Field v-slot="{ id }" :label="$t('financeExpense.usefulLife')" :error="errors['asset.useful_life_months']">
        <Input :id="id" v-model="asset.useful_life_months" class="tabular-nums" type="number" min="1" max="600" inputmode="numeric" />
      </Field>
      <Field v-slot="{ id }" :label="$t('financeExpense.afaCategory')" :error="errors['asset.afa_category']">
        <Select :model-value="asset.afa_category || 'none'" @update:model-value="(value) => pickCategory(String(value), afaCategories)">
          <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="none">{{ $t('financeExpense.noCategory') }}</SelectItem>
            <SelectItem v-for="category in afaCategories" :key="category.key" :value="category.key">{{ category.label }}</SelectItem>
          </SelectContent>
        </Select>
      </Field>
    </div>
  </div>
</template>
