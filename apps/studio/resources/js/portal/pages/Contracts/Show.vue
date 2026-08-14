<script setup lang="ts">
import { onBeforeUnmount, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
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

  <main class="pwrap wide">
    <h1>{{ $t('portal.contractTitle') }}</h1>

    <div v-if="contract" class="split" style="margin-top: var(--s5)">
      <div>
        <div class="pdoc">
          <div class="pdochead">
            <span class="kick">
              {{ $t('portal.contractTemplate', { name: contract.templateName, version: contract.templateVersion }) }}
            </span>
            <Button v-if="contract.downloadUrl" as="a" variant="ghost" :href="contract.downloadUrl" download>
              <svg viewBox="0 0 24 24"><path d="M12 3v12" /><path d="M7.5 10.5L12 15l4.5-4.5" /><path d="M4 19h16" /></svg>
              {{ $t('portal.contractDownload') }}
            </Button>
          </div>

          <div class="pdocscroll">
            <iframe v-if="contract.downloadUrl" :src="contract.downloadUrl" :title="$t('portal.contractTitle')" />
            <p v-else class="pdocwait">{{ $t('portal.contractPending') }}</p>
          </div>
        </div>
      </div>

      <!-- T-08: the rail itself is the affordance — it never renders at all
           once there is nothing to show, whether that is because the switch
           is off (no signature, canSign false) or because signing has
           already happened (a recorded signature is shown regardless of the
           switch's current state; it is a fact, not an offered action). -->
      <div v-if="contract.downloadUrl && (contract.signature || canSign)">
        <div class="prail">
          <div class="card static sig">
            <div class="cardhead"><span class="kick">{{ $t('portal.signTitle') }}</span></div>

            <p v-if="contract.signature" class="legal">
              {{ $t('portal.signedConfirmation', { name: contract.signature.typedName }) }}
            </p>

            <form v-else @submit.prevent="sign">
              <Label class="field">
                <span>{{ $t('portal.signName') }}</span>
                <Input v-model="form.typedName" type="text" required maxlength="255" />
                <em>{{ $t('portal.signNameHint') }}</em>
              </Label>

              <!-- not a <Label> wrapper any more: Checkbox renders a button,
                   and a button inside a label is not clickable through it -->
              <div class="consent">
                <Checkbox id="sign-consent" v-model="form.consent" required />
                <Label for="sign-consent">{{ $t('portal.signConsent') }}</Label>
              </div>

              <Button
                tone="warm"
                class="wide"
                type="submit"
                :disabled="form.processing || !form.consent || !form.typedName"
                style="margin-top: var(--s5)"
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

    <p v-else class="hint">{{ $t('portal.contractNone') }}</p>

    <!-- ponytail: mockup 07 also shows a "Noch eine Frage?" card with a hardcoded
         studio phone number under the document. Skipped — no tenant contact info
         is modelled or exposed to this page yet; add once a story surfaces it. -->
  </main>

  <PortalFooter />
</template>
