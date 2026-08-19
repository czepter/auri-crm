<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ active: string }>()

// One list for all settings screens. Copied per page it drifted: five of the
// eight pages hid live panes behind a "bald" chip that Index.vue had already
// shipped.
const items = [
  { key: 'profile', href: '/einstellungen', label: 'settings.navProfile' },
  { key: 'vat', href: '/einstellungen?pane=vat', label: 'settings.navVat' },
  { key: 'numbering', href: '/einstellungen/nummernkreise', label: 'settings.navNumbering' },
  { key: 'documents', href: '/einstellungen?pane=documents', label: 'settings.navDocuments' },
  { key: 'email', href: '/einstellungen?pane=email', label: 'settings.navMail' },
  { key: 'availability', href: '/einstellungen/verfuegbarkeit', label: 'settings.navAvailability' },
  { key: 'payments', href: '/einstellungen/zahlungen', label: 'settings.navPayments' },
  { key: 'taskTemplates', href: '/einstellungen/aufgabenvorlagen', label: 'settings.navTaskTemplates' },
  { key: 'capture', href: '/einstellungen?pane=capture', label: 'settings.navCapture' },
  { key: 'formTemplates', href: '/einstellungen/fragebogen-vorlagen', label: 'settings.navFormTemplates' },
  { key: 'portal', href: '/einstellungen/portal', label: 'settings.navPortal' },
  { key: 'notifications', href: '/einstellungen/benachrichtigungen', label: 'settings.navNotifications' },
  { key: 'general', href: '/einstellungen?pane=general', label: 'settings.navGeneral' },
]
</script>

<template>
  <nav style="width: 196px; flex: none; display: flex; flex-direction: column; gap: 2px">
    <Link
      v-for="item in items"
      :key="item.key"
      class="nav"
      :class="{ on: item.key === active }"
      :href="item.href"
    >{{ $t(item.label) }}</Link>
  </nav>
</template>
