<script setup lang="ts">
import ExpenseForm from '@/admin/components/finance/ExpenseForm.vue'

// The form seeds its state once; a new key per expense rebuilds it after a save and after a jump to another expense.
const props = defineProps<{
  expense: App.Finance.Data.ExpenseDetailData | null
  locked: boolean
  categories: App.Finance.Data.ExpenseCategoryData[]
  gwgLimit: number
  afaCategories: { key: string; label: string; months: number }[]
  vatModes: Record<string, string>
  defaultVatMode: string
}>()
</script>

<template>
  <ExpenseForm :key="props.expense?.id ?? 'new'" v-bind="props" />
</template>
