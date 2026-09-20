<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { Link } from '@inertiajs/vue3'

defineProps<{ active: string }>()

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
const items = [
  { group: 'settings.groupStudio' },
  { key: 'general', href: '/einstellungen/allgemein', label: 'settings.navGeneral' },
  { key: 'brand', href: '/einstellungen/marke', label: 'settings.brand' },
  { key: 'legal', href: '/einstellungen/rechtliche-einheit', label: 'settings.legalEntity' },
  // Der Absender gilt studioweit, für jede Mail — das Portal
  // ist einer von vielen Auslösern, nicht ihr Zuhause.
  { key: 'email', href: '/einstellungen/email', label: 'settings.navMail' },

  { group: 'settings.groupBilling' },
  // Bankverbindung und Umsatzsteuer landen beide im Fuß derselben Rechnung.
  { key: 'invoice', href: '/einstellungen/rechnungsdaten', label: 'settings.navInvoiceData' },
  { key: 'documents', href: '/einstellungen/dokumente', label: 'settings.navDocuments' },
  { key: 'numbering', href: '/einstellungen/nummernkreise', label: 'settings.navNumbering' },

  { group: 'settings.groupWork' },
  { key: 'availability', href: '/einstellungen/verfuegbarkeit', label: 'settings.navAvailability' },
  { key: 'automations', href: '/einstellungen/automationen', label: 'automations.title' },

  { group: 'settings.groupPortal' },
  { key: 'portal', href: '/einstellungen/portal', label: 'settings.navPortal' },
  // Eine Person, keine Freigabe: Name, Bild und die zwei Wege zu ihr.
  { key: 'portal-contact', href: '/einstellungen/portal/kontakt', label: 'settings.navPortalContact' },
  { key: 'imprint-terms', href: '/einstellungen/impressum-agb', label: 'settings.navImprintTerms' },

  { group: 'settings.groupInterfaces' },
  // Schlüssel, Test- und Live-Umschaltung: eine Anbindung wie die beiden
  // darunter, keine Rechnungsangabe.
  { key: 'payments', href: '/einstellungen/zahlungen', label: 'paymentSettings.title' },
  { key: 'api-tokens', href: '/einstellungen/api-token', label: 'apiTokens.title' },
  { key: 'export', href: '/einstellungen/export', label: 'settings.navExport' },

]

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
    <template v-for="item in items" :key="item.key ?? item.group">
      <div
        v-if="item.group"
        class="mt-4 px-2.5 pb-1 text-[10.5px] tracking-[0.06em] uppercase text-ink-2 first:mt-0 max-[640px]:hidden"
      >
        {{ $t(item.group) }}
      </div>
      <Link
        v-else
        class="flex items-center gap-2.5 rounded-[7px] px-2.5 py-[9px] text-[14px] leading-none whitespace-nowrap no-underline transition-[background-color] duration-[.12s] ease-out hover:bg-muted [&_svg]:size-[17px]" :class="
          item.key === active
            ? 'font-semibold text-foreground [&_svg]:opacity-100'
            : 'font-normal text-muted-foreground [&_svg]:opacity-75'
        "
        :data-open="item.key === active || undefined"
        :href="item.href!"
      >{{ $t(item.label!) }}</Link>
    </template>
  </nav>
</template>
