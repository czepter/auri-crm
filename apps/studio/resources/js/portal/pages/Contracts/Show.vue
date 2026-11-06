<script setup lang="ts">
import { computed, onBeforeUnmount, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalBack from '@/portal/components/PortalBack.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalLabel from '@/portal/components/PortalLabel.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
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
  contract: Contract | null
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
// The form needs the stored PDF: signing hashes exactly those bytes.
const railShown = computed(() => props.contract?.downloadUrl != null && (props.contract.signature != null || props.canSign))

const form = useForm({ typedName: props.contact.name, consent: false })

function sign() {
  form.post(`/projekte/${props.project.id}/vertrag/unterschreiben`, { preserveScroll: true })
}

</script>

<template>
  <PortalShell>
    <PortalHeader :switcher="{ label: project.name, toAll: showSwitcher }" />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.contractTitle') }}
        </h1>
      </section>

      <div v-if="contract" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 grid items-stretch gap-12 min-[1024px]:grid-cols-[minmax(0,1fr)_380px]">
        <!-- One of two columns at 1024px, the whole screen below it. A frame
             around the whole screen encloses nothing and costs two gutters,
             so it stops at the breakpoint; the rules inside carry the
             division on their own. -->
        <div class="overflow-hidden rounded-md border border-border max-[1023px]:rounded-none max-[1023px]:border-0">
          <div v-if="contract.downloadUrl" class="flex items-center border-b border-border px-6 py-4 max-[1023px]:px-0">
            <Button
              class="ml-auto"
              as="a"
              variant="portal-chip"
              :href="contract.downloadUrl"
              download
            >
              <svg class="fill-none stroke-current" viewBox="0 0 24 24"><path d="M12 3v12" /><path d="M7.5 10.5L12 15l4.5-4.5" /><path d="M4 19h16" /></svg>
              {{ $t('portal.contractDownload') }}
            </Button>
          </div>

          <p v-if="!railShown" class="border-b border-border py-4 text-[14.5px] text-muted-foreground max-[1023px]:px-0 min-[1024px]:px-6">
            {{ contract.signature ? $t('portal.signedConfirmation', { name: contract.signature.typedName }) : $t('portal.notSignedYet') }}
          </p>

          <article
            v-if="contract.html"
            class="py-8 text-[15.5px] leading-[1.6] min-[1024px]:px-6 [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[17px] [&_h2]:leading-[1.35] [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_p+*]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
            v-html="contract.html"
          />
          <div v-else-if="contract.downloadUrl" class="h-[74vh] min-h-[520px] bg-sunk">
            <iframe class="block size-full border-0" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
          </div>
          <p v-else class="px-6 py-24 text-center text-[15px] text-muted-foreground">{{ $t('portal.contractPending') }}</p>
        </div>

        <!-- T-08: the rail itself is the affordance — it never renders at all
             once there is nothing to show, whether that is because the switch
             is off (no signature, canSign false) or because signing has
             already happened (a recorded signature is shown regardless of the
             switch's current state; it is a fact, not an offered action).

             stretch, not start: the cell has to be as tall as the document
             column, or position:sticky below has no range to travel in. -->
        <div v-if="railShown">
          <div class="sticky top-[88px] rounded-md border border-line-hover p-6">
            <PortalLabel class="mb-4">{{ $t('portal.signTitle') }}</PortalLabel>

            <p v-if="contract.signature" class="text-[14.5px] leading-[1.6] text-muted-foreground">
              {{ $t('portal.signedConfirmation', { name: contract.signature.typedName }) }}
            </p>

            <form v-else class="grid gap-5" @submit.prevent="sign">
              <PortalField :label="$t('portal.signName')" :hint="$t('portal.signNameHint')">
                <Input v-model="form.typedName" type="text" required maxlength="255" />
              </PortalField>

              <!-- not a <Label> wrapper any more: Checkbox renders a button,
                   and a button inside a label is not clickable through it -->
              <div class="flex cursor-pointer items-start gap-3.5 text-[13.5px] leading-[1.5] [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action">
                <Checkbox id="sign-consent" v-model="form.consent" required />
                <Label variant="inline" for="sign-consent">{{ $t('portal.signConsent') }}</Label>
              </div>

              <Button
                class="w-full justify-center"
                variant="portal"
                type="submit"
                :disabled="form.processing || !form.consent || !form.typedName"
              >
                <svg class="fill-none stroke-current" viewBox="0 0 24 24">
                  <path d="M3 20h18" />
                  <path d="M4.5 16.5c3-1 4-6.5 6-9.5s3.5 5 5.5 6 3.5-1 3.5-1" />
                </svg>
                {{ $t('portal.signSubmit') }}
              </Button>
            </form>
          </div>
        </div>
      </div>

      <p v-else class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 text-[15px] text-muted-foreground">{{ $t('portal.contractNone') }}</p>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
