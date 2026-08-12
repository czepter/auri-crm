<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { Button } from '@/ui/button'

// T-08's own branding read: logo and accent colour, shared portal-wide by
// HandleInertiaRequests (`portalBranding`) rather than passed down per page,
// since every portal screen wears the same chrome. `contact` stays this
// component's own prop, not a shared one: it is null on the sign-in,
// Impressum and Maintenance screens, which have nobody signed in to name.
withDefaults(defineProps<{ contact?: { name: string } | null; showSwitcher?: boolean }>(), {
  contact: null,
  showSwitcher: false,
})

type Branding = { displayName: string | null; logoUrl: string | null; accentColor: string | null }

const branding = computed(() => usePage().props.portalBranding as Branding | undefined)

// Every portal screen mounts this component once, so applying the accent
// colour here — rather than in each page — is enough to cover the whole
// surface.
function applyAccent() {
  const accent = branding.value?.accentColor

  if (accent) {
    document.documentElement.style.setProperty('--accent', accent)
  }
}

onMounted(applyAccent)
watch(branding, applyAccent)
</script>

<template>
  <header class="phead">
    <div class="pbar">
      <span class="pmark">
        <img v-if="branding?.logoUrl" :src="branding.logoUrl" alt="" style="height: 22px; width: auto" />
        <i v-else />
        <template v-if="branding?.displayName">{{ branding.displayName }}</template>
      </span>
      <div v-if="contact" class="pme">
        <Link v-if="showSwitcher" href="/">{{ $t('portal.allProjects') }}</Link>
        <span>{{ $t('portal.signedInAs') }} <b>{{ contact.name }}</b></span>
        <Button variant="ghost" type="button" @click="router.post('/abmelden')">
          <svg viewBox="0 0 24 24">
            <path d="M15 17l5-5-5-5" />
            <path d="M20 12H9" />
            <path d="M12 20H6a2 2 0 01-2-2V6a2 2 0 012-2h6" />
          </svg>
          {{ $t('portal.signOut') }}
        </Button>
      </div>
    </div>
  </header>
</template>
