<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import PortalField from '@/portal/components/PortalField.vue'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import PortalShell from '@/portal/components/PortalShell.vue'
import PortalWrap from '@/portal/components/PortalWrap.vue'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Switch } from '@/ui/switch'

const props = defineProps<{
  contact: { name: string; email: string | null; phone: string | null; street: string | null; postcode: string | null; city: string | null }
  pendingEmail: string | null
  subscribed: boolean
  openRequests: string[]
}>()

const form = useForm({
  name: props.contact.name,
  email: props.contact.email ?? '',
  phone: props.contact.phone ?? '',
  street: props.contact.street ?? '',
  postcode: props.contact.postcode ?? '',
  city: props.contact.city ?? '',
})

function save() {
  form.patch('/einstellungen', { preserveScroll: true })
}

function setSubscription(subscribed: boolean) {
  router.post('/einstellungen/post', { subscribed }, { preserveScroll: true })
}

function requestDsgvo(kind: 'export' | 'erasure') {
  router.post('/einstellungen/datenschutz', { kind }, { preserveScroll: true })
}

// Four unrelated errands under one heading — particulars, mail, sessions,
// DSGVO — and nothing here waits on anything else: each block posts on its
// own. So they are shown one at a time rather than stacked into one scroll,
// and the rail carries the names the blocks used to repeat as eyebrows.
const NAV = [
  { key: 'daten', label: 'portal.yourData' },
  { key: 'post', label: 'portal.postTitle' },
  { key: 'geraete', label: 'portal.accessTitle' },
  { key: 'datenschutz', label: 'portal.privacyTitle' },
] as const

const pane = ref<(typeof NAV)[number]['key']>('daten')

const ROW = 'flex items-center gap-6 border-b border-border py-6'
</script>

<template>
  <PortalShell>
    <PortalHeader />

    <PortalWrap class="pb-40">
      <section class="pt-[var(--band)]">
        <h1 class="animate-rise [animation-delay:140ms] motion-reduce:animate-none font-serif text-[clamp(36px,5vw,52px)] leading-[var(--display-leading)] font-normal tracking-[var(--display-tracking)]">
          {{ $t('portal.accountTitle') }}
        </h1>
      </section>

      <!-- 680px on the block, not the full page: a form field that runs
           1100px wide is a form field nobody can scan. Under 900px the rail
           lies down over the block instead of eating half the column. -->
      <div class="animate-rise [animation-delay:230ms] motion-reduce:animate-none mt-12 grid gap-8 min-[900px]:grid-cols-[168px_minmax(0,1fr)] min-[900px]:gap-16">
        <nav class="flex gap-x-6 gap-y-2 border-b border-border pb-5 text-[13.5px] leading-none max-[899px]:overflow-x-auto max-[899px]:[&>*]:shrink-0 max-[899px]:[&>*]:whitespace-nowrap min-[900px]:sticky min-[900px]:top-24 min-[900px]:flex-col min-[900px]:gap-3.5 min-[900px]:self-start min-[900px]:border-0 min-[900px]:pb-0">
          <Button
            v-for="item in NAV"
            :key="item.key"
            :data-active="pane === item.key"
            type="button"
            variant="portal-nav"
            @click="pane = item.key"
          >{{ $t(item.label) }}</Button>
        </nav>

        <div class="min-w-0 max-w-[680px]">
        <!-- Always editable, whatever phase a booking is in: these are the
             client's own particulars, not contract content. -->
        <section v-if="pane === 'daten'">
          <form class="grid gap-4" @submit.prevent="save">
            <PortalField :label="$t('portal.nameLabel')">
              <Input v-model="form.name" type="text" required />
              <span v-if="form.errors.name" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.name }}</span>
            </PortalField>

            <!-- The address is the login, so the field says so where it is
                 operated rather than in a footnote. -->
            <PortalField :label="$t('portal.emailLabel')" :hint="$t('portal.emailIsLogin')">
              <Input v-model="form.email" type="email" required />
              <span v-if="form.errors.email" class="mt-[7px] block text-[12.5px] leading-[1.45] text-destructive">{{ form.errors.email }}</span>
            </PortalField>
            <!-- Nothing changes until the link sent to the new inbox is
                 followed, which is a fact about the account, not a caption. -->
            <p v-if="pendingEmail" class="text-[13px] text-muted-foreground">{{ $t('portal.emailPending', { email: pendingEmail }) }}</p>

            <PortalField :label="$t('portal.phone')">
              <Input v-model="form.phone" type="tel" />
            </PortalField>

            <PortalField :label="$t('portal.street')">
              <Input v-model="form.street" type="text" />
            </PortalField>

            <div class="grid grid-cols-2 gap-4 max-[640px]:grid-cols-1">
              <PortalField :label="$t('portal.postcode')">
                <Input v-model="form.postcode" type="text" />
              </PortalField>
              <PortalField :label="$t('portal.city')">
                <Input v-model="form.city" type="text" />
              </PortalField>
            </div>

            <div class="mt-2">
              <Button variant="portal" type="submit" :disabled="form.processing">
                {{ $t('common.save') }}
              </Button>
            </div>
          </form>
        </section>

        <section v-else-if="pane === 'post'">
          <label class="flex cursor-pointer items-start gap-4">
            <Switch class="mt-0.5" :model-value="subscribed" @update:model-value="setSubscription(!subscribed)" />
            <span>
              <b class="block text-[14.5px] leading-[1.35] font-semibold tracking-[-0.008em]">{{ $t('portal.postConsent') }}</b>
              <span class="text-[13px] text-muted-foreground">{{ $t('portal.postConsentScope') }}</span>
            </span>
          </label>
        </section>

        <!-- There is no password here and there never will be: a client proves
             an inbox. What there is, is sessions. -->
        <section v-else-if="pane === 'geraete'">
          <div class="border-t border-border">
            <div :class="ROW">
              <span class="flex-1">
                <b class="block text-[15px] leading-[1.35] font-semibold tracking-[-0.008em]">{{ $t('portal.endSessions') }}</b>
              </span>
              <Button variant="portal-chip" type="button" @click="router.post('/einstellungen/abmelden-ueberall')">
                {{ $t('portal.signOut') }}
              </Button>
            </div>
            <!-- The phone's bottom bar deliberately has no sign-out on it, so
                 this is where a client on a phone finds one. -->
            <div :class="ROW">
              <span class="flex-1">
                <b class="block text-[15px] leading-[1.35] font-semibold tracking-[-0.008em]">{{ $t('portal.signOutThisDevice') }}</b>
              </span>
              <Button variant="portal-chip" type="button" @click="router.post('/abmelden')">
                {{ $t('portal.signOut') }}
              </Button>
            </div>
          </div>
        </section>

        <section v-else>
          <div class="border-t border-border">
            <div :class="ROW">
              <span class="flex-1">
                <b class="block text-[15px] leading-[1.35] font-semibold tracking-[-0.008em]">{{ $t('portal.dataCopy') }}</b>
                <span class="text-[13px] text-muted-foreground">{{ $t('portal.dataCopyHint') }}</span>
              </span>
              <Button
                v-if="!openRequests.includes('export')"
                variant="portal-chip"
                type="button"
                @click="requestDsgvo('export')"
              >
                {{ $t('portal.request') }}
              </Button>
              <span v-else class="text-[13px] text-muted-foreground">{{ $t('portal.requested') }}</span>
            </div>

            <div :class="ROW">
              <span class="flex-1">
                <b class="block text-[15px] leading-[1.35] font-semibold tracking-[-0.008em] text-action">{{ $t('portal.erasure') }}</b>
                <!-- Ten years of retention on signed contracts and issued
                     invoices. The line says so instead of promising a deletion
                     that would not be one. -->
                <span class="text-[13px] text-muted-foreground">{{ $t('portal.erasureHint') }}</span>
              </span>
              <Button
                v-if="!openRequests.includes('erasure')"
                variant="portal-chip"
                type="button"
                @click="requestDsgvo('erasure')"
              >
                {{ $t('portal.request') }}
              </Button>
              <span v-else class="text-[13px] text-muted-foreground">{{ $t('portal.requested') }}</span>
            </div>
          </div>
        </section>
        </div>
      </div>
    </PortalWrap>

    <PortalFooter />
  </PortalShell>
</template>
