<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import { formatEuros } from '@/admin/lib/money'

/*
 * Aftersales: was das Studio über die Buchungen hinaus anbietet. Steht auf der
 * Übersicht unter den Galerien und auf dem Projektbildschirm vor dem Kontakt —
 * nie über dem Offenen: ein Vorschlag des Studios ist keine offene Handlung
 * des Kunden, und die Aktionsfarbe bleibt dem vorbehalten, was er erledigen
 * muss.
 *
 * Die Karte trägt eine Haarlinie, die Galeriekarte darüber nicht: ein Bild,
 * das dem Kunden gehört, ist keine Karte — eine Leistung, die er anfragen
 * kann, ist eine.
 */
const props = defineProps<{ shelf: App.Catalog.Data.PortalServiceData[] }>()

const { t } = useI18n()

// Auf Anfrage trägt keine Zahl, und es kommt auch keine mit — siehe
// PortalServiceData.
function priceLabel(offer: App.Catalog.Data.PortalServiceData) {
  if (offer.price === null) return t('portal.priceOnRequest')

  return offer.priceMode === 'from'
    ? t('portal.priceFrom', { price: formatEuros(offer.price) })
    : formatEuros(offer.price)
}
</script>

<template>
  <section v-if="props.shelf.length">
    <PortalLabel>{{ $t('portal.offersTitle') }}</PortalLabel>
    <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(196px,1fr))] gap-4">
      <Link
        v-for="offer in props.shelf"
        :key="offer.id"
        class="block rounded-md border border-border p-3 text-inherit no-underline transition-colors duration-200 ease-[var(--ease)] hover:bg-sunk"
        :href="`/leistungen/${offer.id}`"
      >
        <span class="block overflow-hidden rounded-[var(--r-sm)] bg-sunk">
          <img
            v-if="offer.hasImage"
            :src="`/leistungen/${offer.id}/bild`"
            alt=""
            loading="lazy"
            class="block h-[130px] w-full object-cover"
          />
          <span v-else class="block h-[130px]" />
        </span>
        <b class="mt-3 block font-serif text-[19px] leading-[1.25] font-normal tracking-[-0.01em]">{{ offer.name }}</b>
        <span v-if="offer.text" class="block text-[13px] text-muted-foreground">{{ offer.text }}</span>
        <span class="mt-3 block text-[15px] font-semibold tracking-[-0.01em] tabular-nums">{{ priceLabel(offer) }}</span>
      </Link>
    </div>
  </section>
</template>
