<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import { Button } from '@/ui/button'

defineProps<{ label: string; back: string; src: string }>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t(label)">
          <Button as="a" class="ml-auto" variant="outline" :href="back">
            <ArrowLeft />
            {{ $t('common.back') }}
          </Button>
        </PageHead>

        <div class="mx-auto max-w-[1180px] px-6 pt-6 pb-10">
          <iframe :src class="block h-[calc(100dvh-160px)] w-full rounded-[var(--r)] border-0 bg-paper shadow-card" title="" />
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
