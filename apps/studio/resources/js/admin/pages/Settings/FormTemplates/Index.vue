<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import { Button } from '@/ui/button'

defineProps<{ templates: App.Crm.Data.FormTemplateData[] }>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('formTemplates.title') }}</h1>
      </div>
      <Button :as="Link" href="/einstellungen/fragebogen-vorlagen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('formTemplates.create') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="formTemplates" />

      <div style="flex: 1; min-width: 0">
        <!-- No mockup for this list screen — mockup 13 is the builder a row here
             opens. Follows the system: the labelled row grammar Packages/Index
             uses, not a data table. -->
        <div v-if="templates.length" class="panel">
          <div v-for="template in templates" :key="template.id" class="trow">
            <Link class="pinfo" :href="`/einstellungen/fragebogen-vorlagen/${template.id}`">
              <span class="pname">{{ template.name }}</span>
              <span class="psub">{{ template.projectTypeLabel ?? $t('formTemplates.projectTypeAll') }} · {{ $t('formTemplates.fieldCount', template.fieldCount) }}</span>
            </Link>
            <span class="chip" :class="template.isActive ? 'done' : 'off'">
              {{ template.isActive ? $t('formTemplates.active') : $t('formTemplates.inactive') }}
            </span>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>
