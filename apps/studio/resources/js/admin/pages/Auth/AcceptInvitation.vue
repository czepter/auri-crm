<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Field } from '@/ui/field'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{
  name: string
  email: string
  roleLabel: string
  token: string
}>()

const form = useForm({ password: '', password_confirmation: '' })

function submit() {
  form.post(`/einladung/${props.token}`, {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <AuthLayout :title="$t('invite.title', { name })">
      <p class="mb-6 text-small leading-[1.45] font-normal text-faint">
        {{ $t('invite.hint', { email, role: roleLabel }) }}
      </p>

      <form @submit.prevent="submit">
        <Field class="mb-6">
          <Label for="password">{{ $t('invite.password') }}</Label>
          <Input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required />
          <p class="text-small leading-[1.45] text-destructive" v-if="form.errors.password">{{ form.errors.password }}</p>
        </Field>

        <Field class="mb-6">
          <Label for="password_confirmation">{{ $t('invite.passwordConfirm') }}</Label>
          <Input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required />
        </Field>

        <Button type="submit" class="w-full justify-center" :disabled="form.processing">
          {{ $t('invite.submit') }}
        </Button>
      </form>
  </AuthLayout>
</template>
