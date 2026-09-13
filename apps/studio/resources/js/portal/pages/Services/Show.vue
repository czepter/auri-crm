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
import { formatEuros } from '@/admin/lib/money'
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'

type Service = {
  id: string
  name: string
  description: string | null
  text: string | null
  price: number | null
  priceMode: 'exact' | 'from' | 'on_request'
  requiresDate: boolean
  hasImage: boolean
  includes: string[]
}

const props = defineProps<{ service: Service; studio: { name: string } }>()

const { t } = useI18n()

const form = useForm({ date: '', venue: '', text: '' })

const price = computed(() => {
  if (props.service.price === null) return t('portal.priceOnRequest')

  return props.service.priceMode === 'from'
    ? t('portal.priceFrom', { price: formatEuros(props.service.price) })
    : formatEuros(props.service.price)
})

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

      <!-- Zwei Spalten ab 1000px, dieselbe Teilung wie beim Vertrag: links,
           was der Kunde liest, rechts, was er tut — mitlaufend, damit das
           Formular auf jeder Scrolltiefe erreichbar bleibt. -->
      <div class="grid items-start gap-12 pt-12 min-[1000px]:grid-cols-[minmax(0,1fr)_372px] min-[1000px]:gap-x-[var(--band)]">
        <div>
          <p v-if="service.description" class="max-w-[54ch] text-[17px] leading-[1.55] text-muted-foreground">
            {{ service.description }}
          </p>

          <section v-if="service.includes.length" class="mt-[var(--band)]">
            <PortalLabel class="mb-6">{{ $t('portal.includedTitle') }}</PortalLabel>
            <div class="border-t border-border">
              <p
                v-for="(line, index) in service.includes"
                :key="index"
                class="border-b border-border py-4 text-[15.5px]"
              >
                {{ line }}
              </p>
            </div>
          </section>
        </div>

        <form
          class="rounded-md border border-line-hover p-6 min-[1000px]:sticky min-[1000px]:top-[92px]"
          @submit.prevent="submit"
        >
          <span class="block text-[28px] leading-none font-semibold tracking-[-0.022em] tabular-nums">{{ price }}</span>

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

          <Button class="mt-6 w-full justify-center" type="submit" :disabled="form.processing">
            {{ service.requiresDate ? $t('portal.requestAppointment') : $t('portal.request') }}
          </Button>
        </form>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
