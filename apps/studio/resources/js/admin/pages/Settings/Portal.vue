<script setup lang="ts">
import { ref } from 'vue'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import PortalForm from './panes/PortalForm.vue'

defineProps<{ settings: App.Settings.Data.PortalSettingsData }>()

// Every other future group — the same disabled "bald" list every settings
// screen carries its own copy of, per Settings/Index.vue. Portal is this
// pane now, so it drops out of the list here same as Zahlungen did on
// Settings/Payments.vue; Benachrichtigungen is T-11's own route now too.
const comingSoon: readonly string[] = []

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
      <button
        class="btn"
        type="submit"
        form="portal-settings-form"
        :disabled="!formRef?.form.isDirty || formRef?.form.processing"
        :style="!formRef?.form.isDirty ? 'opacity:.4' : undefined"
      >
        <svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" /></svg>
        {{ $t('common.save') }}
      </button>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav on" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
        <a class="nav" href="/einstellungen/benachrichtigungen">{{ $t('settings.navNotifications') }}</a>
        <a
          v-for="key in comingSoon"
          :key="key"
          class="nav"
          href="#"
          style="pointer-events: none; opacity: 0.5"
        >
          {{ $t(`settings.${key}`) }}
          <span class="chip next" style="margin-left: auto">{{ $t('settings.soon') }}</span>
        </a>
      </nav>

      <div style="flex: 1; min-width: 0; max-width: 620px">
        <PortalForm ref="formRef" :settings="settings" />
      </div>
    </div>
  </AdminLayout>
</template>
