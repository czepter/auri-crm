<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { toDataURL } from 'qrcode'
import { ref, watchEffect } from 'vue'

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
  <div class="authwrap">
    <div class="authcard" style="max-width: 400px">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('auth.mfaEnrollTitle') }}</h1>
      <p class="hint">{{ $t('auth.mfaEnrollRequired') }}</p>

      <img
        v-if="qrCodeDataUrl"
        :src="qrCodeDataUrl"
        :alt="$t('auth.mfaQrAlt')"
        style="display: block; margin: 0 auto var(--s4); width: 220px; height: 220px; border: 1px solid var(--line); border-radius: var(--r)"
      />

      <details style="margin-bottom: var(--s5)">
        <summary class="txtbtn" style="padding: 0; cursor: pointer">
          {{ $t('auth.mfaSecretLabel') }}
        </summary>
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

        <div class="fld grp">
          <label for="code">{{ $t('auth.mfaCode') }}</label>
          <input
            id="code"
            v-model="form.code"
            class="inp num"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
          />
        </div>

        <button class="btn" type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('auth.mfaConfirm') }}
        </button>
      </form>
    </div>
  </div>
</template>
