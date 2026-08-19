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
      style="display: block; margin: 0 auto var(--s4); width: 220px; height: 220px; border: 1px solid var(--line); border-radius: var(--r)"
    />

    <details style="margin-bottom: var(--s5)">
      <Button as="summary" variant="ghost" class="cursor-pointer py-0">
        {{ $t('auth.mfaSecretLabel') }}
      </Button>
      <code
        class="panel"
        style="display: block; margin-top: var(--s2); padding: 9px 11px; font-family: ui-monospace, monospace; font-size: 13px; letter-spacing: .12em"
      >
        {{ secret }}
      </code>
    </details>

    <form @submit.prevent="submit">
      <p v-if="form.errors.code" class="flag" style="margin-bottom: var(--s4)">
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

      <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
        {{ $t('auth.mfaConfirm') }}
      </Button>
    </form>
  </AuthLayout>
</template>
