<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Field } from '@/ui/field'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <AuthLayout :title="$t('auth.mfaTitle')">

      <form @submit.prevent="submit">
        <p v-if="form.errors.code" class="mb-4 mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-[12.5px] leading-[1.3] font-medium text-destructive">
          {{ form.errors.code }}
        </p>

        <Field class="mb-6">
          <Label for="code">{{ $t('auth.mfaCode') }}</Label>
          <Input
            id="code"
            v-model="form.code" class="tabular-nums"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            required />
        </Field>

        <Button type="submit" class="w-full justify-center" :disabled="form.processing">
          {{ $t('auth.mfaConfirm') }}
        </Button>
      </form>
  </AuthLayout>
</template>
