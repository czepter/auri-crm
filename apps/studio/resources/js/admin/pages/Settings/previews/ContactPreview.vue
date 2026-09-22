<script setup lang="ts">
import PortalPerson from '@/portal/components/PortalPerson.vue'
import PreviewFrame from './PreviewFrame.vue'
import type { Studio } from '@/portal/lib/studio'

defineProps<{ studio: Studio }>()
</script>

<template>
  <PreviewFrame :label="$t('settings.previewPortal')" :hint="$t('portalSettings.contactPreviewHint')">
    <!-- Die Karte des Portals selbst, nicht ihr Abbild — inert, weil ihre
         Links sonst aus den Einstellungen führen. -->
    <div class="rounded-md bg-background p-4" data-theme="portal" inert>
      <PortalPerson :studio="studio" message-href="/nachrichten" />
    </div>
  </PreviewFrame>
</template>
