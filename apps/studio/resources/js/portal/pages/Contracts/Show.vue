<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
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
  quoteHref: string
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

// The sticky jump button hands over to the form's own submit once the form
// is on screen, so only one primary is ever visible.
const formEl = ref<HTMLFormElement | null>(null)
const formVisible = ref(false)
let observer: IntersectionObserver | undefined

onMounted(() => {
  if (!formEl.value) return
  observer = new IntersectionObserver(([entry]) => (formVisible.value = entry.isIntersecting))
  observer.observe(formEl.value)
})

onBeforeUnmount(() => {
  clearPoll()
  observer?.disconnect()
})

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

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <PortalBack :href="`/projekte/${project.id}`" />
        <p class="animate-rise motion-reduce:animate-none mb-4 text-[11px] leading-none font-semibold tracking-[0.17em] uppercase text-muted-foreground">{{ project.name }}</p>
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.contractTitle') }}
        </h1>
      </section>

      <div v-if="contract" class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-8 max-w-[720px]">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-3 border-y border-border py-4">
          <span class="inline-flex items-center gap-2 text-[14.5px]">
            <PortalDot :state="contract.signature ? 'done' : 'open'" />
            {{ contract.signature
              ? $t('portal.signedOn', { date: dayDate(contract.signature.consentedAt) })
              : $t('portal.notSignedYet') }}
          </span>
          <p v-if="!canSign && !contract.signature" class="w-full text-[13.5px] text-muted-foreground">{{ $t('portal.signUnavailable') }}</p>
          <a v-if="contract.downloadUrl" class="ml-auto inline-flex min-h-11 items-center text-[13.5px] text-muted-foreground underline underline-offset-4 hover:text-foreground" :href="contract.downloadUrl" download>
            {{ $t('portal.contractDownload') }}
          </a>
        </div>

        <article
          v-if="contract.html"
          class="py-8 text-[15.5px] leading-[1.6] [&_h2]:scroll-mt-24 [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-[17px] [&_h2]:leading-[1.35] [&_h2]:font-semibold [&_h2:first-child]:mt-0 [&_p+*]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
          v-html="contract.html"
        />
        <div v-else-if="contract.downloadUrl" class="h-[74vh] min-h-[520px] bg-sunk">
          <iframe class="block size-full border-0" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
        </div>
        <p v-else class="py-24 text-center text-[15px] text-muted-foreground">{{ $t('portal.contractPending') }}</p>

        <!-- T-08: signing is offered only while the switch is on and nothing
             is recorded yet; a recorded signature is a fact and shows above
             regardless of the switch. -->
        <form v-if="canSign && !contract.signature" id="unterschreiben" ref="formEl" class="grid scroll-mt-24 gap-5 border-t border-border py-8" @submit.prevent="sign">
          <PortalLabel>{{ $t('portal.signTitle') }}</PortalLabel>
          <PortalField :label="$t('portal.signName')" :hint="$t('portal.signNameHint')">
            <Input v-model="form.typedName" type="text" required maxlength="255" />
          </PortalField>

          <!-- not a <Label> wrapper any more: Checkbox renders a button,
               and a button inside a label is not clickable through it -->
          <div class="flex cursor-pointer items-start gap-3.5 text-[13.5px] leading-[1.5] [&_[data-slot=checkbox]]:size-6 [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action">
            <Checkbox id="sign-consent" v-model="form.consent" required />
            <Label variant="inline" for="sign-consent">{{ $t('portal.signConsent') }}</Label>
          </div>

          <Button
            class="min-h-12 w-full justify-center disabled:bg-sunk-2 disabled:text-muted-foreground disabled:opacity-100 disabled:shadow-[inset_0_0_0_1px_var(--muted)]"
            variant="portal"
            type="submit"
            :disabled="form.processing || !form.consent || !form.typedName"
          >
            {{ $t('portal.signButton') }}
          </Button>
        </form>
      </div>
      <div
        v-if="contract && canSign && !contract.signature && !formVisible"
        class="fixed inset-x-0 bottom-[calc(56px+env(safe-area-inset-bottom))] z-30 border-t border-border bg-[var(--bg)] p-3 min-[761px]:bottom-0"
      >
        <Button as="a" variant="portal" href="#unterschreiben" class="min-h-12 w-full justify-center">{{ $t('portal.signButton') }}</Button>
      </div>
      <template v-else-if="!contract">
        <p class="mt-8 text-[17px] leading-[1.5] text-muted-foreground">{{ $t('portal.contractAfterAcceptance') }}</p>
        <Button as-child variant="portal" class="mt-8 min-h-12 w-full justify-center min-[700px]:w-auto">
          <Link :href="quoteHref">{{ $t('portal.quoteView') }}</Link>
        </Button>
      </template>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
