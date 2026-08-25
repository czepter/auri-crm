<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'

defineProps<{ templates: App.Crm.Data.FormTemplateData[] }>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <div data-tokens>
      <PageHead :kicker="$t('settings.kicker')" :title="$t('formTemplates.title')">
        <Button :as="Link" class="ml-auto" variant="pill" href="/einstellungen/fragebogen-vorlagen/neu">
          {{ $t('formTemplates.create') }}
        </Button>
      </PageHead>

      <div class="px-8 pt-7 pb-8">
        <div class="flex items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
          <SettingsNav active="formTemplates" />

          <div style="flex: 1; min-width: 0">
            <!-- No mockup for this list screen — mockup 13 is the builder a row here
                 opens. Follows the system: the labelled row grammar Packages/Index
                 uses, not a data table. -->
            <div v-if="templates.length" class="overflow-hidden rounded-md border border-border">
              <div v-for="template in templates" :key="template.id" class="flex items-center gap-2.5 border-t border-border p-2.5 text-inherit no-underline transition-[background-color] duration-[.12s] ease-out first:border-t-0 hover:bg-muted">
                <Link class="min-w-0 flex-1" :href="`/einstellungen/fragebogen-vorlagen/${template.id}`">
                  <span class="block text-[13px] leading-[1.3] font-semibold">{{ template.name }}</span>
                  <span class="mt-px block text-[12px] leading-[1.3] font-normal text-muted-foreground">{{ template.projectTypeLabel ?? $t('formTemplates.projectTypeAll') }} · {{ $t('formTemplates.fieldCount', template.fieldCount) }}</span>
                </Link>
                <span class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-[10px] leading-[1.5] font-semibold tracking-[0.04em] uppercase" :class="template.isActive ? 'bg-ok-bg text-ok' : 'bg-muted text-faint'">
                  {{ template.isActive ? $t('formTemplates.active') : $t('formTemplates.inactive') }}
                </span>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
