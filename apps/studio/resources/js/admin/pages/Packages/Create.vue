<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import type { PackageItemRow } from '@/admin/lib/packageItem'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import PackageItemsEditor from '@/admin/components/PackageItemsEditor.vue'
import PackagePriceSummary from '@/admin/components/PackagePriceSummary.vue'
import { eurosToCents } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Field } from '@/ui/field'
import { Label } from '@/ui/label'

defineProps<{ catalogItems: App.Catalog.Data.CatalogItemData[] }>()

const form = useForm({
  name: '',
  description: '',
  items: [] as PackageItemRow[],
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
        selectable: item.selectable,
        selected: item.selected,
      })),
    }))
    .post('/pakete')
}
</script>

<template>
  <AdminLayout active="pakete">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :title="$t('packages.createTitle')" />

        <form class="flex items-start gap-8 px-8 pt-7 pb-8" @submit.prevent="submit">
          <div class="max-w-[640px] min-w-0 flex-1">
            <Field id="name" class="mb-6" :label="$t('packages.name')" :error="form.errors.name">
              <Input id="name" v-model="form.name" type="text" required />
            </Field>
            <Field id="description" class="mb-6" :label="$t('packages.description')">
              <Input id="description" v-model="form.description" type="text" />
            </Field>

            <div class="mt-8 first-of-type:mt-6">
              <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('packages.items') }}</div>
              <PackageItemsEditor v-model="form.items" :catalog-items="catalogItems" />
            </div>

            <div class="mt-6 flex items-center gap-4">
              <Button type="submit" class="ml-0" :disabled="form.processing">{{ $t('common.save') }}</Button>
              <Button as="a" href="/pakete" variant="ghost">{{ $t('common.cancel') }}</Button>
            </div>
          </div>

          <div class="sticky top-7 w-[380px] flex-none">
            <PackagePriceSummary :items="form.items" />
          </div>
        </form>
      </div>
    </Page>
  </AdminLayout>
</template>
