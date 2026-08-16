<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'

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
</script>

<template>
  <PortalHeader :contact="contact" />

  <main class="pwrap">
    <h1>{{ $t('portal.accountTitle') }}</h1>

    <!-- Always editable, whatever phase a booking is in: these are the
         client's own particulars, not contract content. -->
    <section class="psec" style="margin-top: var(--s6)">
      <p class="lab">{{ $t('portal.yourData') }}</p>

      <form @submit.prevent="save">
        <Label class="field">
          <span>{{ $t('portal.nameLabel') }}</span>
          <Input v-model="form.name" type="text" required />
        </Label>
        <p v-if="form.errors.name" class="err">{{ form.errors.name }}</p>

        <Label class="field">
          <span>{{ $t('portal.emailLabel') }}</span>
          <Input v-model="form.email" type="email" required />
        </Label>
        <p v-if="form.errors.email" class="err">{{ form.errors.email }}</p>
        <!-- The address is the login, so nothing changes until the link sent
             to the new inbox is followed. -->
        <p v-if="pendingEmail" class="hint">{{ $t('portal.emailPending', { email: pendingEmail }) }}</p>

        <Label class="field">
          <span>{{ $t('portal.phone') }}</span>
          <Input v-model="form.phone" type="tel" />
        </Label>

        <Label class="field">
          <span>{{ $t('portal.street') }}</span>
          <Input v-model="form.street" type="text" />
        </Label>

        <div class="tworow">
          <Label class="field">
            <span>{{ $t('portal.postcode') }}</span>
            <Input v-model="form.postcode" type="text" />
          </Label>
          <Label class="field">
            <span>{{ $t('portal.city') }}</span>
            <Input v-model="form.city" type="text" />
          </Label>
        </div>

        <div class="formfoot">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
        </div>
      </form>
    </section>

    <section class="psec">
      <p class="lab">{{ $t('portal.postTitle') }}</p>
      <label class="consent">
        <Checkbox :model-value="subscribed" @update:model-value="setSubscription(!subscribed)" />
        <span>{{ $t('portal.postConsent') }}</span>
      </label>
    </section>

    <!-- There is no password here and there never will be: a client proves
         an inbox. What there is, is other sessions. -->
    <section class="psec">
      <p class="lab">{{ $t('portal.accessTitle') }}</p>
      <p class="hint">{{ $t('portal.accessHint') }}</p>
      <div class="formfoot">
        <Button variant="outline" type="button" @click="router.post('/einstellungen/abmelden-ueberall')">
          {{ $t('portal.endSessions') }}
        </Button>
      </div>
    </section>

    <section class="psec">
      <p class="lab">{{ $t('portal.privacyTitle') }}</p>

      <div class="pend">
        <div class="prow">
          <span style="flex: 1">
            <h3>{{ $t('portal.dataCopy') }}</h3>
            <div class="sub">{{ $t('portal.dataCopyHint') }}</div>
          </span>
          <Button
            v-if="!openRequests.includes('export')"
            variant="ghost"
            type="button"
            @click="requestDsgvo('export')"
          >
            {{ $t('portal.request') }}
          </Button>
          <span v-else class="hint">{{ $t('portal.requested') }}</span>
        </div>

        <div class="prow">
          <span style="flex: 1">
            <h3>{{ $t('portal.erasure') }}</h3>
            <!-- Ten years of retention on signed contracts and issued
                 invoices. The line says so instead of promising a deletion
                 that would not be one. -->
            <div class="sub">{{ $t('portal.erasureHint') }}</div>
          </span>
          <Button
            v-if="!openRequests.includes('erasure')"
            variant="ghost"
            type="button"
            @click="requestDsgvo('erasure')"
          >
            {{ $t('portal.request') }}
          </Button>
          <span v-else class="hint">{{ $t('portal.requested') }}</span>
        </div>
      </div>
    </section>
  </main>

  <PortalFooter />
</template>
