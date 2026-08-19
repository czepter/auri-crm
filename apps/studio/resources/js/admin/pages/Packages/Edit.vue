<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PackageItemsEditor, { type PackageItemRow } from '@/admin/components/PackageItemsEditor.vue'
import { centsToEuros, eurosToCents } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'

const props = defineProps<{ package: App.Catalog.Data.PackageData }>()

const form = useForm({
  name: props.package.name,
  description: props.package.description ?? '',
  items: props.package.items.map(
    (item): PackageItemRow => ({
      title: item.title,
      description: item.description ?? '',
      quantity: item.quantity,
      unitPrice: centsToEuros(item.unitPrice),
    }),
  ),
})

function submit() {
  form
    .transform((data) => ({
      ...data,
      items: data.items.map((item) => ({
        title: item.title,
        description: item.description || null,
        quantity: item.quantity,
        unit_price: eurosToCents(item.unitPrice),
      })),
    }))
    .patch(`/pakete/${props.package.id}`)
}
</script>

<template>
  <AdminLayout active="pakete">
    <div style="max-width: 640px">
      <h1>{{ $t('packages.editTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <Field id="name" class="mb-6" :label="$t('packages.name')" :error="form.errors.name">
          <Input id="name" v-model="form.name" type="text" required />
        </Field>
        <Field id="description" class="mb-6" :label="$t('packages.description')">
          <Input id="description" v-model="form.description" type="text" />
        </Field>

        <div class="mt-8 first-of-type:mt-6">
          <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('packages.items') }}</div>
          <PackageItemsEditor v-model="form.items" />
        </div>

        <div class="mt-6 flex items-center gap-4">
          <Button type="submit" class="ml-0" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button as="a" href="/pakete" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
