<script setup lang="ts">
import { ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import LegalTextsForm from './panes/LegalTextsForm.vue'
import { Button } from '@/ui/button'

defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  legalTemplates: { family: string; name: string; updatedAt: string | null }[]
}>()

const formRef = ref<InstanceType<typeof LegalTextsForm>>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead :kicker="$t('settings.kicker')" :title="$t('settings.navLegalTexts')">
          <span v-if="formRef?.form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="formRef?.form.isDirty"
            class="ml-auto"
            variant="pill"
            type="submit"
            form="legal-texts-form"
            :disabled="formRef?.form.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-8 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="legal-texts" />

            <div class="max-w-[700px] min-w-0 flex-1">
              <LegalTextsForm ref="formRef" :settings="settings" :legal-templates="legalTemplates" />
            </div>
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
