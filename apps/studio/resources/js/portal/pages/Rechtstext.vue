<script setup lang="ts">
import PortalShell from '@/portal/components/PortalShell.vue'

// Der Körper kommt aus DocumentTemplate::body, denselben Weg wie Angebot und
// Rechnung ihn nehmen — serverseitig aus den Bausteinen gerendert und beim
// Speichern bereinigt, nicht hier.
defineProps<{ title: string; body: string }>()
</script>

<template>
  <PortalShell :title="title">
    <h1 class="mb-[var(--s5)] text-[32px] leading-[1.05] font-bold tracking-[-0.03em] text-foreground">{{ title }}</h1>
    <article class="prose-portal max-w-[68ch] text-[14px] leading-[1.6] text-foreground" v-html="body" />
  </PortalShell>
</template>
