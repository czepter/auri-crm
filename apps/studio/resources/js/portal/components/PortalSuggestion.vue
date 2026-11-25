<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import { formatEuros } from '@/lib/money'

/*
 * Was das Studio diesem Kunden vorgeschlagen hat — nicht das Regal, das jeder
 * gleich sieht.
 *
 * Steht in der mitlaufenden Schiene der Übersicht: die trägt laut Entwurf,
 * "was ihn betrifft", und eine Leistung, die jemand zu diesen Buchungen
 * vorgeschlagen hat, betrifft ihn. Sie klebt beim Scrollen und ist noch da,
 * wenn der Kunde längst bei den Galerien ist.
 *
 * Der Satz ist auf drei Zeilen begrenzt — ein handgeschriebener Absatz passt
 * in 348 px nicht, und ganz steht er auf der Leistungsseite. Begrenzt wie
 * .msgbody im Bestand, kein neuer Kniff.
 */
type Service = App.Catalog.Data.PortalServiceData

const props = defineProps<{
  suggestion: { service: Service; note: string | null; author: string | null }
}>()

const { t } = useI18n()

function priceLabel(service: Service) {
  if (service.price === null) return t('portal.priceOnRequest')

  return service.priceMode === 'from'
    ? t('portal.priceFrom', { price: formatEuros(service.price) })
    : formatEuros(service.price)
}
</script>

<template>
  <section>
    <PortalLabel class="mb-6">{{ $t('portal.suggestionTitle') }}</PortalLabel>

    <div class="surface-card p-4">
      <div v-if="props.suggestion.note" class="flex items-start gap-3">
        <span
          v-if="props.suggestion.author"
          class="grid h-8 w-8 flex-none place-items-center rounded-full bg-sunk font-serif text-[13px] leading-none text-muted-foreground"
        >
          {{ props.suggestion.author.slice(0, 1) }}
        </span>
        <p class="m-0 line-clamp-3 text-[14px] leading-[1.55]">{{ props.suggestion.note }}</p>
      </div>
      <span
        v-if="props.suggestion.note && props.suggestion.author"
        class="mt-2 ml-11 block text-[12.5px] text-muted-foreground"
      >
        {{ props.suggestion.author }}
      </span>

      <Link
        class="mt-4 grid grid-cols-[72px_1fr] items-center gap-3 border-t border-border pt-4 text-inherit no-underline"
        :href="`/leistungen/${props.suggestion.service.id}`"
      >
        <span class="grid h-[50px] w-[72px] place-items-center overflow-hidden rounded-[10px] border border-border bg-sunk">
          <img
            v-if="props.suggestion.service.hasImage"
            :src="`/leistungen/${props.suggestion.service.id}/bild`"
            alt=""
            loading="lazy"
            class="h-full w-full object-cover"
          />
        </span>
        <span>
          <b class="block font-serif text-[18px] leading-[1.2] font-normal tracking-[-0.01em]">
            {{ props.suggestion.service.name }}
          </b>
          <span v-if="props.suggestion.service.description" class="block text-[13px] text-muted-foreground">
            {{ props.suggestion.service.description }}
          </span>
          <span class="mt-[7px] block text-[14.5px] font-semibold tracking-[-0.01em] tabular-nums">
            {{ priceLabel(props.suggestion.service) }}
          </span>
        </span>
      </Link>
    </div>
  </section>
</template>
