<script setup lang="ts">
import { ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import PortalForm from './panes/PortalForm.vue'
import { Button } from '@/ui/button'

defineProps<{ settings: App.Settings.Data.PortalSettingsData }>()

const formRef = ref<InstanceType<typeof PortalForm>>()
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('portalSettings.title') }}</h1>
      </div>
      <span v-if="formRef?.form.isDirty" class="chip now">{{ $t('settings.unsaved') }}</span>
      <Button
        type="submit"
        form="portal-settings-form"
        :disabled="!formRef?.form.isDirty || formRef?.form.processing"
        :style="!formRef?.form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </Button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <SettingsNav active="portal" />

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <PortalForm ref="formRef" :settings="settings" />
      </div>
    </div>
  </AdminLayout>
</template>
