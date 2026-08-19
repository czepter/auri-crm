<script setup lang="ts">
import { Field } from '@/ui/field'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

// Only ever true in local. The server decides; the button cannot talk itself
// into existence.
defineProps<{ devLogin?: boolean }>()

const form = useForm({ email: '', password: '' })

function submit() {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <!-- No sidebar: there is no navigation available to someone not signed in. -->
  <AuthLayout :title="$t('auth.signInTitle')">
    <form @submit.prevent="submit">
      <p v-if="form.errors.email" class="mb-4 mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[12.5px] leading-[1.3] font-medium text-destructive">
        {{ form.errors.email }}
      </p>

      <Field class="mb-6">
        <Label for="email">{{ $t('auth.email') }}</Label>
        <Input
          id="email"
          v-model="form.email"
          type="email"
          autocomplete="username"
          required />
      </Field>

      <Field class="mb-6">
        <Label for="password">{{ $t('auth.password') }}</Label>
        <Input
          id="password"
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          required />
      </Field>

      <Button type="submit" class="w-full justify-center" :disabled="form.processing">
        {{ $t('auth.signIn') }}
      </Button>
    </form>

    <p class="mt-4 text-center">
      <Button :as="Link" variant="ghost" href="/passwort/vergessen">{{ $t('auth.forgot') }}</Button>
    </p>

    <div v-if="devLogin" class="mt-6 border-t border-border pt-4">
      <Button variant="outline"
        type="button"
        class="w-full p-[9px]"
        @click="router.post('/dev-login')"
      >
        {{ $t('auth.devLogin') }}
      </Button>
      <p class="mt-1.5 text-center text-[11.5px] leading-[1.45] text-faint">
        {{ $t('auth.devLoginHint') }}
      </p>
    </div>
  </AuthLayout>
</template>
