<script setup lang="ts">
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

defineProps<{ codes: string[] }>()
</script>

<template>
  <AuthLayout :title="$t('auth.recoveryTitle')" width="max-w-[420px]">
    <p class="text-[11.5px] leading-[1.45] text-faint" style="margin-bottom: var(--s5)">{{ $t('auth.recoveryHint') }}</p>

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

    <Button type="button" style="width: 100%; justify-content: center" @click="router.visit('/')">
      {{ $t('auth.recoveryContinue') }}
    </Button>
  </AuthLayout>
</template>
