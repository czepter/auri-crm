<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { toDataURL } from 'qrcode'
import { ref, watchEffect } from 'vue'
import { Alert, AlertDescription } from '@/ui/alert'
import { Button } from '@/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/ui/card'
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
  <main class="flex min-h-screen items-center justify-center p-6">
    <Card class="w-full max-w-md">
      <CardHeader>
        <CardTitle>{{ $t('auth.mfaEnrollTitle') }}</CardTitle>
        <CardDescription>{{ $t('auth.mfaEnrollRequired') }}</CardDescription>
      </CardHeader>
      <CardContent class="space-y-4">
        <p class="text-sm text-muted-foreground">{{ $t('auth.mfaEnrollHint') }}</p>

        <img
          v-if="qrCodeDataUrl"
          :src="qrCodeDataUrl"
          :alt="$t('auth.mfaQrAlt')"
          class="mx-auto h-[220px] w-[220px] rounded-md border"
        />

        <details class="text-sm text-muted-foreground">
          <summary class="cursor-pointer underline underline-offset-4">
            {{ $t('auth.mfaSecretLabel') }}
          </summary>
          <code class="mt-2 block rounded-md bg-muted px-3 py-2 font-mono text-sm tracking-widest">
            {{ secret }}
          </code>
        </details>

        <form class="space-y-4" @submit.prevent="submit">
          <Alert v-if="form.errors.code" variant="destructive">
            <AlertDescription>{{ form.errors.code }}</AlertDescription>
          </Alert>

          <div class="space-y-2">
            <Label for="code">{{ $t('auth.mfaCode') }}</Label>
            <Input
              id="code"
              v-model="form.code"
              inputmode="numeric"
              autocomplete="one-time-code"
              required
            />
          </div>

          <Button type="submit" class="w-full" :disabled="form.processing">{{ $t('auth.mfaConfirm') }}</Button>
        </form>
      </CardContent>
    </Card>
  </main>
</template>
