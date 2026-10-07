<script setup lang="ts">
import { Field } from '@/ui/field'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ email: '' })
const status = computed(() => usePage().props.flash.status)

function submit() {
  form.post('/passwort/vergessen')
}
</script>

<template>
  <AuthLayout :title="$t('reset.requestTitle')">
    <!-- The same message whether or not the address has an account: a
         differing one tells a stranger which addresses exist. -->
    <p v-if="status" class="mb-4 mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-small leading-[1.3] font-medium text-destructive">{{ status }}</p>

    <form @submit.prevent="submit">
      <Field class="mb-6">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <Input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="username"
          required />
        <p v-if="form.errors.email" class="text-small leading-[1.45] text-destructive">{{ form.errors.email }}</p>
      </Field>

      <Button type="submit" class="w-full justify-center" :disabled="form.processing">
        {{ $t('reset.send') }}
      </Button>
    </form>

    <p class="mt-4 text-center">
      <Button :as="Link" variant="ghost" href="/login">{{ $t('reset.backToLogin') }}</Button>
    </p>
  </AuthLayout>
</template>
