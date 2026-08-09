<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'

defineProps<{
  contact: { name: string }
  // A single-project contact never reaches this page — HomeController
  // redirects straight to their project — so this list is either empty or
  // has more than one entry to switch between.
  projects: { id: string; name: string; type: string }[]
}>()
</script>

<template>
  <header class="phead">
    <div class="pbar">
      <span class="pmark"><i />Atelier Nordlicht <em>Fotografie · Hamburg</em></span>
      <div class="pme">
        <span>{{ $t('portal.signedInAs') }} <b>{{ contact.name }}</b></span>
        <button type="button" @click="router.post('/abmelden')">
          <svg viewBox="0 0 24 24">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
          </svg>
          {{ $t('portal.signOut') }}
        </button>
      </div>
    </div>
  </header>

  <main class="pwrap">
    <div class="pgreet">{{ $t('portal.greeting', { name: contact.name.split(' ')[0] }) }}</div>
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

  <footer class="pfoot">
    <span>Atelier Nordlicht · Hamburg</span>
  </footer>
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
