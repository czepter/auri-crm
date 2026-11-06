<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import { Button } from '@/ui/button'

defineProps<{ label: string; back: string; src: string }>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <FormShell :title="$t(label)" :kicker="$t('settings.kicker')" :kicker-href="back" wide>
      <template #head>
        <Button as="a" class="ml-auto" variant="outline" :href="back">
          <ArrowLeft />
          {{ $t('common.back') }}
        </Button>
      </template>

      <iframe :src class="block h-[calc(100dvh-220px)] w-full rounded-[var(--r)] border-0 bg-paper shadow-card" title="" />
    </FormShell>
  </AdminLayout>
</template>
