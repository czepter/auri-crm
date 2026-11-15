<script setup lang="ts">
import { computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import { useObjectUrl } from '@vueuse/core'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import FormShell from '@/ui/page/FormShell.vue'
import SettingsNav from '@/admin/components/settings/SettingsNav.vue'
import ContactPreview from './previews/ContactPreview.vue'
import { Button } from '@/ui/button'
import { DropArea } from '@/ui/drop-area'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { useLiveProps } from '@/admin/lib/liveReload'
import { saveState } from '@/admin/lib/autosave'

const props = defineProps<{
  settings: App.Settings.Data.PortalSettingsData
  branding: App.Settings.Data.StudioSettingsData
}>()
useLiveProps(['settings', 'branding'])

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
  absence: null,
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
    <FormShell
      :kicker="`${$t('settings.kicker')} › ${$t('settings.navPortal')}`"
      kicker-href="/einstellungen/portal"
      :title="$t('portalSettings.contactTitle')"
      :state="saveState([form])"
    >
      <template #head>
        <Button v-if="form.isDirty" variant="default" type="submit" form="portal-contact-form" :disabled="form.processing">
          {{ $t('common.save') }}
        </Button>
      </template>

      <template #nav><SettingsNav active="portal-contact" /></template>

            <!-- novalidate: die Fehler stehen unter dem Feld, wo sie hingehören,
                 nicht in einer Sprechblase des Browsers. -->
            <form
              id="portal-contact-form"
              class="min-w-0"
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

      <template #preview><ContactPreview :studio="studio" /></template>
    </FormShell>
  </AdminLayout>
</template>
