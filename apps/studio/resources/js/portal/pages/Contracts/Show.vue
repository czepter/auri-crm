<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalField from '@/portal/components/PortalField.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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
  templateName: string
  templateVersion: number
  downloadUrl: string | null
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
const form = useForm({ typedName: props.contact.name, consent: false })

function sign() {
  form.post(`/projekte/${props.project.id}/vertrag/unterschreiben`, { preserveScroll: true })
}
</script>

<template>
  <PortalHeader :contact="contact" :show-switcher="showSwitcher" />

  <PortalWrap wide>
    <h1>{{ $t('portal.contractTitle') }}</h1>

    <div v-if="contract" class="mt-8 grid grid-cols-[minmax(0,1fr)_352px] items-stretch gap-11">
      <div>
        <div class="overflow-hidden rounded-md border border-border">
          <div class="flex items-center gap-5 border-b border-border px-8 py-5 [&_[data-slot=button]]:ml-auto [&_[data-slot=button]]:p-0">
            <span class="text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">
              {{ $t('portal.contractTemplate', { name: contract.templateName, version: contract.templateVersion }) }}
            </span>
            <Button v-if="contract.downloadUrl" as="a" variant="ghost" :href="contract.downloadUrl" download>
              <svg viewBox="0 0 24 24"><path d="M12 3v12" /><path d="M7.5 10.5L12 15l4.5-4.5" /><path d="M4 19h16" /></svg>
              {{ $t('portal.contractDownload') }}
            </Button>
          </div>

          <div class="h-[74vh] bg-[#FDFCFB] [&_iframe]:block [&_iframe]:size-full [&_iframe]:border-0">
            <iframe v-if="contract.downloadUrl" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
            <p v-else class="px-8 py-16 text-center text-[14px] leading-[1.5] font-normal text-muted-foreground">{{ $t('portal.contractPending') }}</p>
          </div>
        </div>
      </div>

      <!-- T-08: the rail itself is the affordance — it never renders at all
           once there is nothing to show, whether that is because the switch
           is off (no signature, canSign false) or because signing has
           already happened (a recorded signature is shown regardless of the
           switch's current state; it is a fact, not an offered action). -->
      <div v-if="contract.downloadUrl && (contract.signature || canSign)">
        <div class="sticky top-5 flex max-h-[calc(100vh-20px-44px)] flex-col gap-5 overflow-y-auto">
          <div class="relative block cursor-default rounded-md border border-line-hover p-5 text-inherit no-underline">
            <div class="mb-3.5 flex items-center justify-between">
              <span class="mb-[5px] text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">
                {{ $t('portal.signTitle') }}
              </span>
            </div>

            <p v-if="contract.signature" class="mt-5 text-[12.5px] leading-[1.6] font-normal text-muted-foreground">
              {{ $t('portal.signedConfirmation', { name: contract.signature.typedName }) }}
            </p>

            <form v-else @submit.prevent="sign">
              <PortalField
                class="mb-3.5"
                :label="$t('portal.signName')"
                :hint="$t('portal.signNameHint')"
              >
                <Input v-model="form.typedName" type="text" required maxlength="255" />
              </PortalField>

              <!-- not a <Label> wrapper any more: Checkbox renders a button,
                   and a button inside a label is not clickable through it -->
              <div class="flex cursor-pointer items-start gap-3.5 text-[13.5px] leading-[1.5] font-normal [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action">
                <Checkbox id="sign-consent" v-model="form.consent" required />
                <Label for="sign-consent">{{ $t('portal.signConsent') }}</Label>
              </div>

              <Button
                class="wide mt-8"
                type="submit"
                :disabled="form.processing || !form.consent || !form.typedName"
              >
                <svg viewBox="0 0 24 24">
                  <path d="M3 20h18" />
                  <path d="M4.5 16.5c3-1 4-6.5 6-9.5s3.5 5 5.5 6 3.5-1 3.5-1" />
                </svg>
                {{ $t('portal.signSubmit') }}
              </Button>

            </form>
          </div>
        </div>
      </div>
    </div>

    <p v-else class="text-[11.5px] leading-[1.45] font-normal text-faint">{{ $t('portal.contractNone') }}</p>

    <!-- ponytail: mockup 07 also shows a "Noch eine Frage?" card with a hardcoded
         studio phone number under the document. Skipped — no tenant contact info
         is modelled or exposed to this page yet; add once a story surfaces it. -->
  </PortalWrap>

  <PortalFooter />
</template>
