<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'

defineProps<{ templates: App.Crm.Data.FormTemplateData[] }>()

const comingSoon = ['navVat', 'navDocuments', 'navNotifications', 'navGeneral'] as const
</script>

<template>
  <AdminLayout active="einstellungen">
    <div class="hrow">
      <div>
        <div class="kick">{{ $t('settings.kicker') }}</div>
        <h1>{{ $t('formTemplates.title') }}</h1>
      </div>
      <Link class="btn" href="/einstellungen/fragebogen-vorlagen/neu">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
        {{ $t('formTemplates.create') }}
      </Link>
    </div>

    <div style="display: flex; gap: var(--s6); margin-top: var(--s5); align-items: flex-start">
      <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
        <a class="nav" href="/einstellungen">{{ $t('settings.navProfile') }}</a>
        <a class="nav" href="/einstellungen/nummernkreise">{{ $t('settings.navNumbering') }}</a>
        <a class="nav" href="/einstellungen/verfuegbarkeit">{{ $t('settings.navAvailability') }}</a>
        <a class="nav" href="/einstellungen/zahlungen">{{ $t('settings.navPayments') }}</a>
        <a class="nav" href="/einstellungen/aufgabenvorlagen">{{ $t('settings.navTaskTemplates') }}</a>
        <a class="nav on" href="/einstellungen/fragebogen-vorlagen">{{ $t('settings.navFormTemplates') }}</a>
        <a class="nav" href="/einstellungen/portal">{{ $t('settings.navPortal') }}</a>
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

        <p v-else class="hint">{{ $t('formTemplates.empty') }}</p>
      </div>
    </div>
  </AdminLayout>
</template>
