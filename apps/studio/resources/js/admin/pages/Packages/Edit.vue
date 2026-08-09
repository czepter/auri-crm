<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PackageItemsEditor, { type PackageItemRow } from '@/admin/components/PackageItemsEditor.vue'
import { centsToEuros, eurosToCents } from '@/admin/lib/money'

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
        <div class="fld grp">
          <label for="name">{{ $t('packages.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="description">{{ $t('packages.description') }}</label>
          <input id="description" v-model="form.description" class="inp" type="text" />
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('packages.items') }}</div>
          <PackageItemsEditor v-model="form.items" />
        </div>

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">{{ $t('common.save') }}</button>
          <a href="/pakete" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
