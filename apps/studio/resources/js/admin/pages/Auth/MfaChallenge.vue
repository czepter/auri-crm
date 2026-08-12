<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

const form = useForm({ code: '' })

function submit() {
  form.post('/auth/mfa', { onFinish: () => form.reset('code') })
}
</script>

<template>
  <div class="authwrap">
    <div class="authcard">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('auth.mfaTitle') }}</h1>

      <form @submit.prevent="submit">
        <p v-if="form.errors.code" class="flag" style="margin-bottom: var(--s4)">
          {{ form.errors.code }}
        </p>

        <div class="fld grp">
          <Label for="code">{{ $t('auth.mfaCode') }}</Label>
          <Input
            id="code"
            v-model="form.code" class="tabular-nums"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            required />
        </div>

        <Button type="submit" style="width: 100%; justify-content: center" :disabled="form.processing">
          {{ $t('auth.mfaConfirm') }}
        </Button>
      </form>
    </div>
  </div>
</template>
