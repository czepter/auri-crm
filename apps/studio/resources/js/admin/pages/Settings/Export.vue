<script setup lang="ts">
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'

defineProps<{
  contacts: number
  projects: number
}>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navExport')" />

        <div class="px-8 pt-7 pb-8">
          <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="export" />

            <div class="max-w-[620px] min-w-0 flex-1">
              <div class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
                <span class="flex-1 text-[14px] font-semibold">{{ $t('contacts.title') }}</span>
                <span class="text-[12.5px] tabular-nums text-ink-2">{{ contacts }}</span>
                <Button as="a" variant="outline" href="/einstellungen/export/kontakte">CSV</Button>
              </div>
              <div class="flex items-center gap-4 py-3 shadow-[inset_0_-1px_0_var(--rule)]">
                <span class="flex-1 text-[14px] font-semibold">{{ $t('projects.title') }}</span>
                <span class="text-[12.5px] tabular-nums text-ink-2">{{ projects }}</span>
                <Button as="a" variant="outline" href="/einstellungen/export/projekte">CSV</Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
