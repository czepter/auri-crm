<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import PortalFooter from '@/portal/components/PortalFooter.vue'
import PortalField from '@/portal/components/PortalField.vue'
import PortalHeader from '@/portal/components/PortalHeader.vue'
import { Button } from '@/ui/button'
import { Checkbox } from '@/ui/checkbox'
import { Input } from '@/ui/input'

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

  <main class="mx-auto max-w-[740px] px-8 pt-11 pb-16">
    <h1>{{ $t('portal.accountTitle') }}</h1>

    <!-- Always editable, whatever phase a booking is in: these are the
         client's own particulars, not contract content. -->
    <section class="mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.yourData') }}</p>

      <form @submit.prevent="save">
        <PortalField :label="$t('portal.nameLabel')">
          <Input v-model="form.name" type="text" required />
        </PortalField>
        <p v-if="form.errors.name" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.name }}</p>

        <PortalField :label="$t('portal.emailLabel')">
          <Input v-model="form.email" type="email" required />
        </PortalField>
        <p v-if="form.errors.email" class="text-[11.5px] leading-[1.45] text-destructive">{{ form.errors.email }}</p>
        <!-- The address is the login, so nothing changes until the link sent
             to the new inbox is followed. -->
        <p v-if="pendingEmail" class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portal.emailPending', { email: pendingEmail }) }}</p>

        <PortalField :label="$t('portal.phone')">
          <Input v-model="form.phone" type="tel" />
        </PortalField>

        <PortalField :label="$t('portal.street')">
          <Input v-model="form.street" type="text" />
        </PortalField>

        <div class="grid grid-cols-[120px_minmax(0,1fr)] gap-5">
          <PortalField :label="$t('portal.postcode')">
            <Input v-model="form.postcode" type="text" />
          </PortalField>
          <PortalField :label="$t('portal.city')">
            <Input v-model="form.city" type="text" />
          </PortalField>
        </div>

        <div class="mt-8 flex items-center gap-5 [&_[data-slot=button][data-variant=default]]:ml-0">
          <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
        </div>
      </form>
    </section>

    <section class="mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.postTitle') }}</p>
      <label class="flex cursor-pointer items-start gap-3.5 text-[13.5px] leading-[1.5] font-normal [&_[data-slot=checkbox]]:mt-0.5 [&_[data-slot=checkbox]]:size-[17px] [&_[data-slot=checkbox]]:flex-none [&_[data-slot=checkbox][data-state=checked]]:border-action [&_[data-slot=checkbox][data-state=checked]]:bg-action">
        <Checkbox :model-value="subscribed" @update:model-value="setSubscription(!subscribed)" />
        <span>{{ $t('portal.postConsent') }}</span>
      </label>
    </section>

    <!-- There is no password here and there never will be: a client proves
         an inbox. What there is, is other sessions. -->
    <section class="mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.accessTitle') }}</p>
      <p class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portal.accessHint') }}</p>
      <div class="mt-8 flex items-center gap-5 [&_[data-slot=button][data-variant=default]]:ml-0">
        <Button variant="outline" type="button" @click="router.post('/einstellungen/abmelden-ueberall')">
          {{ $t('portal.endSessions') }}
        </Button>
      </div>
    </section>

    <section class="mt-11">
      <p class="mb-5 text-[11px] leading-none font-semibold tracking-[0.1em] uppercase text-faint">{{ $t('portal.privacyTitle') }}</p>

      <div class="border-t border-border">
        <div class="flex items-center gap-5 border-b border-border py-5">
          <span class="flex-1">
            <h3 class="m-0 text-[14px] leading-[1.3] font-semibold">{{ $t('portal.dataCopy') }}</h3>
            <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">{{ $t('portal.dataCopyHint') }}</div>
          </span>
          <Button
            v-if="!openRequests.includes('export')"
            variant="ghost"
            type="button"
            @click="requestDsgvo('export')"
          >
            {{ $t('portal.request') }}
          </Button>
          <span v-else class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portal.requested') }}</span>
        </div>

        <div class="flex items-center gap-5 border-b border-border py-5">
          <span class="flex-1">
            <h3 class="m-0 text-[14px] leading-[1.3] font-semibold">{{ $t('portal.erasure') }}</h3>
            <!-- Ten years of retention on signed contracts and issued
                 invoices. The line says so instead of promising a deletion
                 that would not be one. -->
            <div class="mt-0.5 text-[12.5px] leading-[1.4] font-normal text-muted-foreground">{{ $t('portal.erasureHint') }}</div>
          </span>
          <Button
            v-if="!openRequests.includes('erasure')"
            variant="ghost"
            type="button"
            @click="requestDsgvo('erasure')"
          >
            {{ $t('portal.request') }}
          </Button>
          <span v-else class="text-[11.5px] leading-[1.45] text-faint">{{ $t('portal.requested') }}</span>
        </div>
      </div>
    </section>
  </main>

  <PortalFooter />
</template>
