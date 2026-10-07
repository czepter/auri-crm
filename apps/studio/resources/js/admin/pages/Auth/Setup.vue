<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Button } from '@/ui/button'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' })

function submit() {
  form.post('/einrichtung', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <AuthLayout :title="$t('setup.title')">
    <p class="mb-6 text-small leading-[1.45] font-normal text-faint">{{ $t('setup.hint') }}</p>

    <form @submit.prevent="submit">
      <Field class="mb-6">
        <Label for="name">{{ $t('setup.name') }}</Label>
        <Input id="name" v-model="form.name" type="text" autocomplete="name" required />
        <p v-if="form.errors.name" class="text-small leading-[1.45] text-destructive">{{ form.errors.name }}</p>
      </Field>

      <Field class="mb-6">
        <Label for="email">{{ $t('setup.email') }}</Label>
        <Input id="email" v-model="form.email" type="email" autocomplete="email" required />
        <p v-if="form.errors.email" class="text-small leading-[1.45] text-destructive">{{ form.errors.email }}</p>
      </Field>

      <Field class="mb-6">
        <Label for="password">{{ $t('setup.password') }}</Label>
        <Input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          required />
        <p v-if="form.errors.password" class="text-small leading-[1.45] text-destructive">{{ form.errors.password }}</p>
      </Field>

      <Field class="mb-6">
        <Label for="password_confirmation">{{ $t('setup.passwordConfirm') }}</Label>
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          required />
      </Field>

      <Button type="submit" class="w-full justify-center" :disabled="form.processing">
        {{ $t('setup.submit') }}
      </Button>
    </form>
  </AuthLayout>
</template>
