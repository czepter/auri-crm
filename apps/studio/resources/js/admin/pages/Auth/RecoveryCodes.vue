<script setup lang="ts">
import { router } from '@inertiajs/vue3'

defineProps<{ codes: string[] }>()
</script>

<template>
  <div class="authwrap">
    <div class="authcard" style="max-width: 420px">
      <span class="brand">Nordlicht</span>
      <h1>{{ $t('auth.recoveryTitle') }}</h1>
      <p class="hint" style="margin-bottom: var(--s5)">{{ $t('auth.recoveryHint') }}</p>

      <ul
        class="panel"
        style="
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: var(--s2) var(--s4);
          padding: var(--s4);
          margin: 0 0 var(--s5);
          list-style: none;
          font-family: ui-monospace, monospace;
          font-size: 13px;
        "
      >
        <li v-for="code in codes" :key="code">{{ code }}</li>
      </ul>

      <button class="btn" type="button" style="width: 100%; justify-content: center" @click="router.visit('/')">
        {{ $t('auth.recoveryContinue') }}
      </button>
    </div>
  </div>
</template>
