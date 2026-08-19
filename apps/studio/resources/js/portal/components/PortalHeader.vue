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

// P-09 (#228): impersonation nobody can forget they are in. Every write is
// refused anyway (EnsureAudience), but a studio owner should not have to
// discover that by being refused.
const preview = computed(() => usePage().props.portalPreview === true)

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
  <div
    v-if="preview"
    class="flex items-center justify-center gap-5 border-b border-border bg-due-bg px-8 py-[9px] text-[12.5px] leading-none font-medium text-due"
  >
    <span>{{ $t('portal.previewBanner') }}</span>
    <a class="text-due underline" href="/vorschau-beenden">{{ $t('portal.previewLeave') }}</a>
  </div>

  <header class="border-b border-border">
    <div class="mx-auto flex max-w-[1060px] items-center gap-5 px-8 py-5">
      <span class="flex items-baseline gap-2.25 text-[19px] leading-[1.1] font-bold tracking-[-0.035em] text-foreground">
        <img v-if="branding?.logoUrl" :src="branding.logoUrl" alt="" class="h-[22px] w-auto" />
        <i v-else class="size-[7px] flex-none rounded-full bg-action" />
        <template v-if="branding?.displayName">{{ branding.displayName }}</template>
      </span>
      <div
        v-if="contact"
        class="ml-auto flex items-center gap-5 text-[13px] leading-none font-normal text-muted-foreground"
      >
        <Link
          v-if="showSwitcher"
          class="inline-flex items-center gap-1.5 text-muted-foreground no-underline hover:text-foreground"
          href="/"
        >
          {{ $t('portal.allProjects') }}
        </Link>
        <span>{{ $t('portal.signedInAs') }} <b class="font-semibold text-foreground">{{ contact.name }}</b></span>
        <!-- The ghost button is a shade heavier and a shade padded than the
             row it stands in; .pme used to flatten both. -->
        <Button
          class="gap-1.5 py-0 font-normal [&_svg]:opacity-100"
          variant="ghost"
          type="button"
          @click="router.post('/abmelden')"
        >
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
