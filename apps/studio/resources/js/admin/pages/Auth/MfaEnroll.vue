<script setup lang="ts">
import { Field } from '@/ui/field'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { toDataURL } from 'qrcode'
import { ref, watchEffect } from 'vue'
import { Button } from '@/ui/button'
import { PinInput, PinInputGroup, PinInputSlot } from '@/ui/pin-input'
import { Label } from '@/ui/label'

const props = defineProps<{ secret: string; otpauthUri: string }>()

// google2fa prüft sechs Ziffern; Wiederherstellungscodes gibt es hier noch
// nicht, die entstehen erst mit diesem Schritt.
const TOTP_LENGTH = 6

const form = useForm({ code: '' })
const digits = ref<string[]>([])
const qrCodeDataUrl = ref('')

// Rendered client-side so the secret embedded in the otpauth URI never
// leaves the browser — a server-side QR image API would send it over the
// network to a third party.
watchEffect(async () => {
  qrCodeDataUrl.value = await toDataURL(props.otpauthUri, { width: 220, margin: 1 })
})

function submit() {
  form
    .transform(() => ({ code: digits.value.join('') }))
    .post('/auth/mfa/confirm', {
      onFinish: () => {
        digits.value = []
        form.reset('code')
      },
    })
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
        class="mt-1.5 block overflow-hidden rounded-md border border-border px-[11px] py-[9px] font-mono text-[13px] tracking-[0.12em]"
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
        <PinInput v-model="digits" @complete="submit">
          <PinInputGroup>
            <PinInputSlot v-for="index in TOTP_LENGTH" :key="index" :id="index === 1 ? 'code' : undefined" :index="index - 1" />
          </PinInputGroup>
        </PinInput>
      </Field>

      <Button type="submit" class="w-full justify-center" :disabled="form.processing">
        {{ $t('auth.mfaConfirm') }}
      </Button>
    </form>
  </AuthLayout>
</template>
