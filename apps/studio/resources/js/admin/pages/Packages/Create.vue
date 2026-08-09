<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PackageItemsEditor, { type PackageItemRow } from '@/admin/components/PackageItemsEditor.vue'
import { eurosToCents } from '@/admin/lib/money'

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
      })),
    }))
    .post('/pakete')
}
</script>

<template>
  <AdminLayout active="pakete">
    <div style="max-width: 640px">
      <h1>{{ $t('packages.createTitle') }}</h1>

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
