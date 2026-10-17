<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useObjectUrl } from '@vueuse/core'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import Page from '@/ui/page/Page.vue'
import PageHead from '@/ui/page/PageHead.vue'
import SettingsNav from '@/admin/components/SettingsNav.vue'
import ContactPreview from './previews/ContactPreview.vue'
import { Button } from '@/ui/button'
import { DropArea } from '@/ui/drop-area'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()

const form = useForm({
  portal_contact_name: props.settings.contactName ?? '',
  portal_contact_phone: props.settings.contactPhone ?? '',
  portal_contact_email: props.settings.contactEmail ?? '',
  portal_contact_response_time: props.settings.contactResponseTime ?? '',
  portal_contact_avatar: null as File | null,
})

const picked = useObjectUrl(computed(() => form.portal_contact_avatar ?? undefined))
const preview = computed(() => picked.value ?? props.settings.contactAvatarUrl)

// Was der Kunde sähe, während getippt wird: ohne eigenen Namen steht dort
// die Marke, genau wie PortalSettings::contactLine() es auflöst.
const studio = computed(() => ({
  name: form.portal_contact_name || props.branding.displayName,
  avatarUrl: preview.value,
  messagingEnabled: props.settings.messagingMode !== 'off',
  phone: form.portal_contact_phone || null,
  email: form.portal_contact_email || null,
  responseTime: form.portal_contact_response_time || null,
}))

function removeAvatar() {
  form.portal_contact_avatar = null
  router.delete('/einstellungen/portal/kontakt/bild', { preserveScroll: true })
}

// Inertias eigener Umweg: ein Browser schickt PATCH nicht als
// multipart/form-data, also reist das Verb als `_method` mit.
function submit() {
  form
    .transform((data) => ({
      ...data,
      portal_contact_name: data.portal_contact_name || null,
      portal_contact_phone: data.portal_contact_phone || null,
      portal_contact_email: data.portal_contact_email || null,
      portal_contact_response_time: data.portal_contact_response_time || null,
      _method: 'patch',
    }))
    .post('/einstellungen/portal/kontakt', { forceFormData: true, preserveScroll: true })
}
</script>

<template>
  <AdminLayout active="einstellungen">
    <Page data-tokens wide>
      <div class="min-w-0 flex-1">
        <PageHead
          :kicker="`${$t('settings.kicker')} › ${$t('settings.navPortal')}`"
          kicker-href="/einstellungen/portal"
          :title="$t('portalSettings.contactTitle')"
        >
          <span v-if="form.isDirty" class="flex-none rounded-[var(--r-sm)] px-2 py-[3px] text-small leading-[1.5] font-semibold bg-due-bg text-due">{{ $t('settings.unsaved') }}</span>
          <Button
            v-if="form.isDirty"
            class="ml-auto"
            variant="pill"
            type="submit"
            form="portal-contact-form"
            :disabled="form.processing"
          >
            {{ $t('common.save') }}
          </Button>
        </PageHead>

        <div class="px-6 pt-7 pb-8">
          <div class="flex flex-wrap items-start gap-8 max-[640px]:flex-col max-[640px]:gap-4">
            <SettingsNav active="portal-contact" />

            <!-- novalidate: die Fehler stehen unter dem Feld, wo sie hingehören,
                 nicht in einer Sprechblase des Browsers. -->
            <form
              id="portal-contact-form"
              class="w-[620px] max-w-full min-w-0 flex-none"
              novalidate
              @submit.prevent="submit"
            >
              <div class="mb-6">
                <Field
                  :label="$t('portalSettings.contactAvatarLabel')"
                  id="k-bild"
                  :error="form.errors.portal_contact_avatar"
                  :hint="$t('portalSettings.contactAvatarHint')"
                >
                  <div class="flex items-center gap-4">
                    <DropArea
                      id="k-bild"
                      class="flex-1"
                      accept="image/png,image/jpeg,image/webp"
                      @files="(files) => (form.portal_contact_avatar = files[0])"
                    >
                      <img v-if="preview" :src="preview" alt="" class="size-20 flex-none rounded-full bg-muted object-cover" />
                      <div v-else class="size-20 flex-none rounded-full bg-muted" />
                      <span class="min-w-0 truncate text-small text-foreground">{{ form.portal_contact_avatar?.name ?? $t('common.drop') }}</span>
                    </DropArea>
                    <Button v-if="settings.contactAvatarUrl" type="button" variant="outline" @click="removeAvatar">
                      {{ $t('common.remove') }}
                    </Button>
                  </div>
                </Field>
              </div>

              <div class="mb-6">
                <Field
                  :label="$t('portalSettings.contactNameLabel')"
                  id="k-name"
                  wide
                  :hint="$t('portalSettings.contactNameHint')"
                  :error="form.errors.portal_contact_name"
                >
                  <Input id="k-name" v-model="form.portal_contact_name" type="text" />
                </Field>
              </div>

              <div class="mb-6">
                <div class="grid grid-cols-2 gap-4">
                  <Field :label="$t('portalSettings.contactPhoneLabel')" id="k-telefon" :error="form.errors.portal_contact_phone">
                    <Input id="k-telefon" v-model="form.portal_contact_phone" type="tel" />
                  </Field>
                  <Field :label="$t('portalSettings.contactEmailLabel')" id="k-email" :error="form.errors.portal_contact_email">
                    <Input id="k-email" v-model="form.portal_contact_email" type="email" />
                  </Field>
                  <Field
                    wide
                    :label="$t('portalSettings.contactResponseLabel')"
                    id="k-antwortzeit"
                    :hint="$t('portalSettings.contactResponseHint')"
                    :error="form.errors.portal_contact_response_time"
                  >
                    <Input id="k-antwortzeit" v-model="form.portal_contact_response_time" type="text" />
                  </Field>
                </div>
              </div>
            </form>

            <ContactPreview :studio="studio" />
          </div>
        </div>
      </div>
    </Page>
  </AdminLayout>
</template>
