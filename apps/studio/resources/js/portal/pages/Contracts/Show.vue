<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalDot from '@/portal/components/PortalDot.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { dayDate } from '@/portal/lib/dates'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

type Signature = {
  typedName: string
  consentedAt: string
}

type Contract = {
  status: string
  downloadUrl: string | null
  html: string | null
  signature: Signature | null
}

const props = defineProps<{
  contact: { name: string }
  showSwitcher: boolean
  project: { id: string; name: string }
  // T-08's "sign contracts" switch (default off) — the affordance half of
  // the same rule EnsurePortalCapability enforces on the sign route itself.
  // Never hides an already-recorded signature, only the form that would
  // create a new one.
  canSign: boolean
  contract: Contract
}>()

// Mirrors Projects/Contract.vue on the admin side: the render already
// happened on the queue by the time this page loads, or it is still running
// and this page finds out by asking again — never by triggering one itself.
let timer: ReturnType<typeof setInterval> | undefined

function poll() {
  clearPoll()
  timer = setInterval(() => router.reload({ only: ['contract'] }), 2000)
}

function clearPoll() {
  if (timer) clearInterval(timer)
  timer = undefined
}

watch(
  () => props.contract?.status,
  (status) => (status === 'pending' ? poll() : clearPoll()),
  { immediate: true },
)

onBeforeUnmount(clearPoll)

// consent starts unchecked and stays that way until the person ticks it
// themselves — never pre-ticked, never implied by pressing submit. The
// server enforces the same rule with the `accepted` rule on `consent`, so a
// request built by hand without it is rejected exactly like an unchecked
// box would be.
const form = useForm({ typedName: props.contact.name, consent: false })

function sign() {
  form.post(`/projekte/${props.project.id}/vertrag/unterschreiben`, { preserveScroll: true })
}

</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-28">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.contractTitle') }}
        </h1>
      </section>

      <div class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-8 max-w-[720px]">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-y border-border py-4">
          <span class="inline-flex items-center gap-2 text-[14.5px]">
            <PortalDot :state="contract.signature ? 'done' : 'open'" />
            {{ contract.signature
              ? $t('portal.signedOn', { date: dayDate(contract.signature.consentedAt) })
              : $t('portal.notSignedYet') }}
          </span>
          <a v-if="contract.downloadUrl" class="ml-auto inline-flex min-h-11 items-center text-[13.5px] text-muted-foreground underline underline-offset-4 hover:text-foreground" :href="contract.downloadUrl" download>
            {{ $t('portal.contractDownload') }}
          </a>
        </div>
        <Button v-if="canSign && !contract.signature" as="a" variant="portal" href="#unterschreiben" class="mt-5 min-h-12 w-full justify-center">{{ $t('portal.signButton') }}</Button>

        <article
          v-if="contract.html"
          class="py-8 text-[15.5px] leading-[1.6] [&_h2]:scroll-mt-24 [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[17px] [&_h2]:leading-[1.35] [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_p+*]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
          v-html="contract.html"
        />
        <div v-else-if="contract.downloadUrl" class="h-[74vh] min-h-[520px] bg-sunk">
          <iframe class="block size-full border-0" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
        </div>

        <!-- T-08: signing is offered only while the switch is on and nothing
             is recorded yet; a recorded signature is a fact and shows above
             regardless of the switch. -->
        <form v-if="canSign && !contract.signature" id="unterschreiben" class="grid scroll-mt-24 gap-5 border-t border-border py-8" @submit.prevent="sign">
          <PortalLabel>{{ $t('portal.signTitle') }}</PortalLabel>
          <PortalField :label="$t('portal.signName')" :hint="$t('portal.signNameHint')">
            <Input v-model="form.typedName" type="text" required maxlength="255" />
          </PortalField>

          <!-- not a <Label> wrapper any more: Checkbox renders a button,
               and a button inside a label is not clickable through it -->
          <div class="flex cursor-pointer items-start gap-3.5 text-[13.5px] leading-[1.5] [&_[data-slot=checkbox]]:size-6 [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action">
            <Checkbox id="sign-consent" v-model="form.consent" class="relative after:absolute after:-inset-2.5 after:content-['']" required />
            <Label variant="inline" for="sign-consent">{{ $t('portal.signConsent') }}</Label>
          </div>

          <Button
            class="min-h-12 w-full justify-center"
            variant="portal"
            type="submit"
            :state="form.processing ? 'loading' : 'idle'"
            :disabled="form.processing || !form.consent || !form.typedName"
          >
            {{ $t('portal.signButton') }}
          </Button>
        </form>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
