<script setup lang="ts">
import { Field } from '@/ui/field'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { toDataURL } from 'qrcode'
import { ref, watchEffect } from 'vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const props = defineProps<{ secret: string; otpauthUri: string }>()

const form = useForm({ code: '' })
const qrCodeDataUrl = ref('')

// Rendered client-side so the secret embedded in the otpauth URI never
// leaves the browser — a server-side QR image API would send it over the
// network to a third party.
watchEffect(async () => {
  qrCodeDataUrl.value = await toDataURL(props.otpauthUri, { width: 220, margin: 1 })
})

function submit() {
  form.post('/auth/mfa/confirm', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <AuthLayout :title="$t('auth.mfaEnrollTitle')" width="max-w-[400px]">
    <p class="text-[11.5px] leading-[1.45] text-faint">{{ $t('auth.mfaEnrollRequired') }}</p>

    <img
      v-if="qrCodeDataUrl"
      :src="qrCodeDataUrl"
      :alt="$t('auth.mfaQrAlt')"
      class="mx-auto mb-4 block size-[220px] rounded-md border border-border"
    />

    <details class="mb-6">
      <Button as="summary" variant="ghost" class="cursor-pointer py-0">
        {{ $t('auth.mfaSecretLabel') }}
      </Button>
      <code
        class="overflow-hidden rounded-md border border-border"
        class="mt-1.5 block px-[11px] py-[9px] font-mono text-[13px] tracking-[0.12em]"
      >
        {{ secret }}
      </code>
    </details>

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
