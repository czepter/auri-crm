<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const form = useForm({ name: '', body: '' })

function submit() {
  form.post('/vertragsvorlagen')
}
</script>

<template>
  <AdminLayout active="vertragsvorlagen">
    <div style="max-width: 720px">
      <h1>{{ $t('contractTemplates.createTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('contractTemplates.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" required />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="body">{{ $t('contractTemplates.body') }}</label>
          <textarea id="body" v-model="form.body" class="inp" rows="16" required />
          <p class="hint">{{ $t('contractTemplates.bodyHint') }}</p>
          <p v-if="form.errors.body" class="err">{{ form.errors.body }}</p>
        </div>

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">{{ $t('common.save') }}</button>
          <a href="/vertragsvorlagen" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
