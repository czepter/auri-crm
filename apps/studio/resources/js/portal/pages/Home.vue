<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'

defineProps<{
  contact: { name: string }
  // T-08: the portal home's own welcome text (studio_settings.portal_welcome_text) —
  // null until a studio sets one, shown nowhere else.
  welcomeText: string | null
  // A single-project contact never reaches this page — HomeController
  // redirects straight to their project — so this list is either empty or
  // has more than one entry to switch between.
  projects: { id: string; name: string; type: string }[]
}>()
</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="pwrap">
    <div class="pgreet">{{ $t('portal.greeting', { name: contact.name.split(' ')[0] }) }}</div>
    <p v-if="welcomeText" class="plead">{{ welcomeText }}</p>
    <h1 v-if="projects.length">{{ $t('portal.chooseProject') }}</h1>

    <div v-if="projects.length" class="switcher">
      <Link v-for="project in projects" :key="project.id" :href="`/projekte/${project.id}`" class="card">
        <div class="kick">{{ project.type }}</div>
        <div class="who">{{ project.name }}</div>
        <span class="go" />
      </Link>
    </div>

    <p v-else class="hint">{{ $t('portal.nothingShared') }}</p>
  </main>

  <PortalFooter />
</template>

<style scoped>
/* No mockup draws a multi-project picker yet — 06-startseite is one
   contact's one project. Composed from the same tokens as everything else
   here (var(--s3), the shared .card/.kick/.who/.go vocabulary): a vertical
   stack with the gap the rest of the portal already uses, nothing invented. */
.switcher {
  display: flex;
  flex-direction: column;
  gap: var(--s3);
  margin-top: var(--s5);
}
</style>
