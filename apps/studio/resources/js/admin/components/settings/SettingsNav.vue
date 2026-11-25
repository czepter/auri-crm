<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

defineProps<{ active: string }>()

const page = usePage()
const here = page.url.split('?')[0]

// Liegend sind es zwanzig Einträge in einer Zeile, und der offene steht weit
// rechts. Ohne das sieht man beim Öffnen nur den Anfang der Liste und nicht,
// wo man ist.
const bar = ref<HTMLElement | null>(null)

onMounted(() => bar.value?.querySelector('[data-open]')?.scrollIntoView({ inline: 'center', block: 'nearest' }))

// One list for all settings screens. Copied per page it drifted: five of the
// eight pages hid live panes behind a "bald" chip that Index.vue had already
// shipped.
//
// Grouped the same way AdminLayout's own sidebar groups: a `group` entry is a
// heading, everything after it belongs to it until the next one.
const items: { group?: string; key?: string; href?: string; label?: string; external?: boolean; owner?: boolean; apart?: boolean }[] = [
  { group: 'settings.groupStudio' },
  { key: 'general', href: '/einstellungen/allgemein', label: 'settings.navGeneral' },
  // Der Absender gilt studioweit, für jede Mail — das Portal
  // ist einer von vielen Auslösern, nicht ihr Zuhause.
  { key: 'email', href: '/einstellungen/email', label: 'settings.navMail' },
  { key: 'retention', href: '/einstellungen/aufbewahrung', label: 'settings.navRetention' },

  { group: 'settings.groupBilling' },
  { key: 'payment', href: '/einstellungen/zahlung', label: 'settings.navPayment' },
  { key: 'receipts', href: '/einstellungen/layout', label: 'settings.navDocuments' },
  { key: 'numbering', href: '/einstellungen/nummernkreise', label: 'settings.navNumbering' },

  { group: 'settings.groupWork' },
  // Verweise auf die Stellen, an denen sie bedient werden; nie der offene Eintrag.
  { key: 'stages', href: `/auftraege/einstellungen/stufen?return=${encodeURIComponent(here)}`, label: 'settings.navStages', external: true },
  { key: 'templates', href: '/vorlagen', label: 'settings.navTemplates', external: true },
  { key: 'contracts', href: '/einstellungen/vertraege', label: 'settings.navContracts' },
  { key: 'automations', href: '/einstellungen/automationen', label: 'automations.title' },

  { group: 'settings.groupPortal' },
  { key: 'portal', href: '/einstellungen/portal', label: 'settings.navPortal' },
  // Eine Person, keine Freigabe: Name, Bild und die zwei Wege zu ihr.
  { key: 'portal-contact', href: '/einstellungen/portal/kontakt', label: 'settings.navPortalContact' },
  { key: 'imprint-terms', href: '/einstellungen/impressum-agb', label: 'settings.navImprintTerms' },

  { group: 'settings.groupInterfaces' },
  { key: 'export', href: '/einstellungen/export', label: 'settings.navExport' },

  // Ganz unten, abgesetzt von allen Gruppen und nur für den Inhaber.
  { key: 'audit', href: '/einstellungen/protokoll', label: 'settings.navAudit', owner: true, apart: true },
  { key: 'deletion', href: '/einstellungen/studio-loeschen', label: 'settings.navDeleteStudio', owner: true },
]

// Die Ziele der Verweise verlangen andere Rechte als die Einstellungen.
const shown = computed(() =>
  items.filter((item) => (!item.external || page.props.settingsLinks?.[item.key as 'stages' | 'templates']) && (!item.owner || page.props.auth.user?.role === 'admin')),
)

/* tokens.css .nav — the sidebar row, reused for the settings rail. */
/* tokens.css .nav. The two states are written out rather than layered:
   two font-weight utilities on one element are decided by bundle order. */
</script>

<template>
  <!-- Unter 640px legt sie sich hin und scrollt seitwärts, wie die Spalte
       des Datensatzes. Die Gruppenüberschriften fallen dort weg: in einer
       Zeile ordnen sie nichts, sie stehen nur im Weg. -->
  <nav
    ref="bar"
    class="flex w-[196px] flex-none flex-col gap-0.5 max-[640px]:w-full max-[640px]:flex-row max-[640px]:overflow-x-auto max-[640px]:pb-1"
  >
    <template v-for="item in shown" :key="item.key ?? item.group">
      <div
        v-if="item.group"
        class="mt-4 px-2.5 pb-1 text-small text-ink-2 first:mt-0 max-[640px]:hidden"
      >
        {{ $t(item.group) }}
      </div>
      <Link
        v-else
        class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[9px] text-ui leading-none whitespace-nowrap no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted [&_svg]:size-[17px]" :class="[
          item.key === active && !item.external
            ? 'font-semibold text-foreground [&_svg]:opacity-100'
            : 'font-normal text-muted-foreground [&_svg]:opacity-75',
          item.apart && 'mt-4 max-[640px]:mt-0',
        ]"
        :data-open="(item.key === active && !item.external) || undefined"
        :href="item.href!"
      >{{ $t(item.label!) }}</Link>
    </template>
  </nav>
</template>
