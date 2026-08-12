<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

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
