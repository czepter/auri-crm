<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Field } from '@/ui/field'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{ token: string; email: string | null }>()

const form = useForm({
  token: props.token,
  email: props.email ?? '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post('/passwort/neu', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <AuthLayout :title="$t('reset.newTitle')">
      <form @submit.prevent="submit">
        <p v-if="form.errors.email" class="mb-4 mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-small leading-[1.3] font-medium text-destructive">
          {{ form.errors.email }}
        </p>

        <Field class="mb-6">
          <Label for="email">{{ $t('auth.email') }}</Label>
          <Input id="email" v-model="form.email" type="email" autocomplete="username" required />
        </Field>

        <Field class="mb-6">
          <Label for="password">{{ $t('reset.password') }}</Label>
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
          {{ $t('reset.submit') }}
        </Button>
      </form>
  </AuthLayout>
</template>
