<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PackageItemsEditor, { type PackageItemRow } from '@/admin/components/PackageItemsEditor.vue'
import { eurosToCents } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

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
          <Label for="name">{{ $t('packages.name') }}</Label>
          <Input id="name" v-model="form.name" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <Label for="description">{{ $t('packages.description') }}</Label>
          <Input id="description" v-model="form.description" type="text" />
        </div>

        <div class="dsec">
          <div class="dlab">{{ $t('packages.items') }}</div>
          <PackageItemsEditor v-model="form.items" />
        </div>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
          <Button as="a" href="/pakete" variant="ghost">{{ $t('common.cancel') }}</Button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
