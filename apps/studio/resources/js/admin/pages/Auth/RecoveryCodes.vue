<script setup lang="ts">
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

defineProps<{ codes: string[] }>()
</script>

<template>
  <AuthLayout :title="$t('auth.recoveryTitle')" width="max-w-[420px]">
    <p class="mb-6 text-small leading-[1.45] text-faint">{{ $t('auth.recoveryHint') }}</p>

    <ul
      class="m-0 mb-6 grid list-none grid-cols-2 gap-x-4 gap-y-1.5 overflow-hidden rounded-md border border-border p-4 font-mono text-small"
    >
      <li v-for="code in codes" :key="code">{{ code }}</li>
    </ul>

    <Button type="button" class="w-full justify-center" @click="router.visit('/')">
      {{ $t('auth.recoveryContinue') }}
    </Button>
  </AuthLayout>
</template>
