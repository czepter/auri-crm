<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { vatAmount } from '@/lib/quoteTotals'
import { formatEuros, formatPercent } from '@/lib/money'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

type ServiceItem = {
  title: string
  description: string | null
  selectable: boolean
  selected: boolean
  // Nur gesetzt, wenn wählbar und noch nicht vorausgewählt — eine enthaltene
  // oder vorausgewählte Zeile steckt schon in Service.price.
  addOnPrice: number | null
}

type Service = {
  id: string
  name: string
  description: string | null
  price: number | null
  priceMode: 'exact' | 'from' | 'on_request'
  requiresDate: boolean
  hasImage: boolean
  items: ServiceItem[]
}

const props = defineProps<{
  service: Service
  // Nur gesetzt, wenn diese Leistung diesem Kunden vorgeschlagen wurde. Aus
  // dem Regal gegriffen fehlt der Block, und der Screen fängt mit der
  // Beschreibung an.
  suggestion: { note: string | null; author: string | null } | null
  vatRate: number | null
  studio: { name: string }
}>()

const { t } = useI18n()

const form = useForm({ date: '', venue: '', text: '' })

// Ein Kunde sieht einen Preis, der schon feststeht — brutto, nicht "zzgl.
// Umsatzsteuer" wie eine Rechnung an ein anderes Unternehmen. Das gilt B2C
// wie B2B: das Portal stellt hier aus, es rechnet nicht ab.
function gross(net: number): number {
  return props.vatRate ? net + vatAmount(net, props.vatRate) : net
}

const price = computed(() => {
  if (props.service.price === null) return t('portal.priceOnRequest')

  const amount = gross(props.service.price)

  return props.service.priceMode === 'from' ? t('portal.priceFrom', { price: formatEuros(amount) }) : formatEuros(amount)
})

const included = computed(() => props.service.items.filter((item) => !item.selectable || item.selected))
const optional = computed(() => props.service.items.filter((item) => item.selectable && !item.selected))

function submit() {
  form.post(`/leistungen/${props.service.id}/anfragen`, { preserveScroll: true })
}
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pb-[var(--band)]">
      <div v-if="service.hasImage" class="mt-8 aspect-[16/7] overflow-hidden rounded-md bg-sunk max-[700px]:aspect-[4/3]">
        <img :src="`/leistungen/${service.id}/bild`" alt="" class="h-full w-full object-cover" />
      </div>

      <section class="pt-12 max-w-[20ch]">
        <h1 class="font-serif text-[clamp(38px,20px+2.4vw,56px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ service.name }}
        </h1>
      </section>

      <!-- Zwei Spalten ab 1000px, dieselbe Teilung wie beim Angebot: links,
           was der Kunde liest, rechts, was er tut — mitlaufend, damit das
           Formular auf jeder Scrolltiefe erreichbar bleibt. -->
      <div class="grid items-start gap-12 pt-12 min-[1000px]:grid-cols-[minmax(0,1fr)_372px] min-[1000px]:gap-x-[var(--band)]">
        <div>
          <!-- Der ganze Satz, den das Studio diesem Kunden geschrieben hat. In
               der Schiene der Übersicht steht er auf drei Zeilen begrenzt; wer
               ihm folgt, soll ihn zu Ende lesen können. -->
          <div v-if="suggestion?.note" class="mb-8 flex items-start gap-4 border-b border-border pb-8">
            <span
              v-if="suggestion.author"
              class="grid h-10 w-10 flex-none place-items-center rounded-full bg-sunk font-serif text-[15px] leading-none text-muted-foreground"
            >
              {{ suggestion.author.slice(0, 1) }}
            </span>
            <div>
              <p class="m-0 max-w-[48ch] text-body leading-[1.6]">{{ suggestion.note }}</p>
              <span v-if="suggestion.author" class="mt-2 block text-[13px] text-muted-foreground">{{ suggestion.author }}</span>
            </div>
          </div>

          <p v-if="service.description" class="max-w-[54ch] text-[17px] leading-[1.55] text-muted-foreground">
            {{ service.description }}
          </p>

          <section v-if="included.length" class="mt-[var(--band)]">
            <PortalLabel class="mb-6">{{ $t('portal.includedTitle') }}</PortalLabel>
            <div class="border-t border-border">
              <p
                v-for="(item, index) in included"
                :key="index"
                class="border-b border-border py-4 text-[15.5px]"
              >
                {{ [item.title, item.description].filter(Boolean).join(' · ') }}
              </p>
            </div>
          </section>

          <!-- Wählbar und noch nicht vorausgewählt: ein eigener Preis, der
               nicht in Service.price steckt — sonst könnte der Kunde nicht
               sehen, was ihn zusätzlich kostet. -->
          <section v-if="optional.length" class="mt-[var(--band)]">
            <PortalLabel class="mb-6">{{ $t('portal.optionalTitle') }}</PortalLabel>
            <div class="border-t border-border">
              <p
                v-for="(item, index) in optional"
                :key="index"
                class="flex gap-4 border-b border-border py-4 text-[15.5px]"
              >
                <span class="flex-1">{{ [item.title, item.description].filter(Boolean).join(' · ') }}</span>
                <span class="flex-none tabular-nums text-muted-foreground">{{ $t('portal.addOnPrice', { price: formatEuros(gross(item.addOnPrice ?? 0)) }) }}</span>
              </p>
            </div>
          </section>
        </div>

        <form
          class="rounded-md border border-line-hover p-6 min-[1000px]:sticky min-[1000px]:top-[92px]"
          @submit.prevent="submit"
        >
          <PortalLabel class="mb-2">{{ $t('portal.totalTitle') }}</PortalLabel>
          <span class="block text-[28px] leading-none font-semibold tracking-[-0.022em] tabular-nums">{{ price }}</span>
          <span v-if="vatRate && service.price !== null" class="mt-[7px] block text-[13px] text-muted-foreground">
            {{ $t('portal.vatNote', { rate: formatPercent(vatRate) }) }}
          </span>

          <div class="mt-8 grid gap-4">
            <!-- Nur, wenn das Paket danach fragt. Ein Album hat keinen Termin,
                 und ein Pflichtfeld dafür wäre eine erfundene Angabe im
                 Vorgang. -->
            <PortalField v-if="service.requiresDate" :label="$t('portal.preferredDate')">
              <DatePicker v-model="form.date" />
              <span v-if="form.errors.date" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.date }}</span>
            </PortalField>

            <PortalField v-if="service.requiresDate" :label="$t('portal.venueName')">
              <Input v-model="form.venue" type="text" />
            </PortalField>

            <PortalField :label="$t('portal.inquiryText')">
              <Textarea v-model="form.text" rows="4" />
            </PortalField>
          </div>

          <Button class="mt-6 w-full justify-center" type="submit" :state="form.processing ? 'loading' : 'idle'" :disabled="form.processing">
            {{ service.requiresDate ? $t('portal.requestAppointment') : $t('portal.request') }}
          </Button>
        </form>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
