<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

const props = defineProps<{ contact: App.Crm.Data.ContactSuggestionData }>()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
})

function submit() {
  form.patch(`/kontakte/${props.contact.id}`)
}
</script>

<template>
  <AdminLayout active="kontakte">
    <div style="max-width: 520px">
      <h1>{{ $t('contacts.editTitle') }}</h1>

      <form style="margin-top: var(--s5)" @submit.prevent="submit">
        <div class="fld grp">
          <label for="name">{{ $t('contacts.name') }}</label>
          <input id="name" v-model="form.name" class="inp" type="text" />
          <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>
        </div>
        <div class="fld grp">
          <label for="email">{{ $t('contacts.email') }}</label>
          <input id="email" v-model="form.email" class="inp" type="email" />
          <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        </div>
        <div class="fld grp">
          <label for="phone">{{ $t('contacts.phone') }}</label>
          <input id="phone" v-model="form.phone" class="inp" type="tel" />
        </div>

        <div class="formfoot">
          <button class="btn" type="submit" :disabled="form.processing">
            {{ $t('common.save') }}
          </button>
          <a href="/kontakte" class="txtbtn">{{ $t('common.cancel') }}</a>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
