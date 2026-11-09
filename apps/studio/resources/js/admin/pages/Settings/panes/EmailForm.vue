<script setup lang="ts">
import { Button } from '@/ui/button'
import FormSection from '@/ui/page/FormSection.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { Checkbox } from '@/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'

const props = defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  mailTemplates: { id: string; name: string }[]
  senderFromEnv: { name: boolean; address: boolean }
}>()
const emit = defineEmits<{ saved: [] }>()

const NONE = 'none'
const OCCASIONS = ['quote_sent', 'quote_follow_up', 'invoice_sent', 'payment_reminder', 'credit_note_sent'] as const

const form = useForm({
  mail_reply_to: props.settings.mailReplyTo ?? '',
  mail_bcc: props.settings.mailBcc ?? '',
  mail_signature: props.settings.mailSignature ?? '',
  mail_clients_enabled: props.settings.mailClientsEnabled,
  mail_header_name: props.settings.mailHeaderName,
  mail_footer_legal: props.settings.mailFooterLegal,
  mail_footer_extra: props.settings.mailFooterExtra ?? '',
  mail_defaults: Object.fromEntries(OCCASIONS.map((occasion) => [occasion, props.settings.mailDefaults[occasion] ?? NONE])),
})


// Leert die beiden Spalten einer Bestandsinstallation, damit MAIL_FROM_*
// wieder greift — ein eigener Weg, weil der Absender nicht im Formular steht.
function resetSender() {
  router.patch('/einstellungen/email', { mail_sender_reset: true }, { preserveScroll: true })
}

function submit() {
  form
    .transform((data) => ({
      mail_reply_to: data.mail_reply_to || null,
      mail_bcc: data.mail_bcc || null,
      mail_signature: data.mail_signature || null,
      mail_clients_enabled: data.mail_clients_enabled,
      mail_header_name: data.mail_header_name,
      mail_footer_legal: data.mail_footer_legal,
      mail_footer_extra: data.mail_footer_extra || null,
      mail_defaults: Object.fromEntries(Object.entries(data.mail_defaults).map(([occasion, id]) => [occasion, id === NONE ? null : id])),
    }))
    .patch('/einstellungen/email', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="mail-form" @submit.prevent="submit">
    <FormSection :title="$t('settings.mailSenderFromEnv')">
      <div class="flex flex-col gap-1.5">
        <div class="flex items-center gap-3 rounded-[var(--r-sm)] bg-sunk px-3 py-2.5">
          <span class="text-small text-ink-2">{{ $t('settings.mailFromNameLabel') }}</span>
          <span class="ml-auto text-small font-medium">{{ settings.mailFromName }}</span>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--r-sm)] bg-sunk px-3 py-2.5">
          <span class="text-small text-ink-2">{{ $t('settings.mailFromAddressLabel') }}</span>
          <span class="ml-auto text-small font-medium">{{ settings.mailFromAddress }}</span>
        </div>
        <Button
          v-if="!senderFromEnv.name || !senderFromEnv.address"
          class="mt-1 self-start"
          variant="outline"
          type="button"
          @click="resetSender"
        >
          {{ $t('settings.mailSenderReset') }}
        </Button>
      </div>
    </FormSection>

    <FormSection :title="$t('settings.mailReplyLabel')">
      <div>
        <div class="grid grid-cols-2 gap-4">
          <Field :error="form.errors.mail_reply_to" v-slot="{ id }">
            <Label :for="id">{{ $t('settings.mailReplyToLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.mail_reply_to" type="email" />
          </Field>
          <Field :error="form.errors.mail_bcc" v-slot="{ id }">
            <Label :for="id">{{ $t('settings.mailBccLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input :id v-model="form.mail_bcc" type="email" />
          </Field>
        </div>
      </div>
    </FormSection>

    <FormSection :title="$t('settings.mailFrameLabel')">
      <div class="flex flex-col gap-5">
        <div class="flex items-center gap-3 rounded-[var(--r-sm)] bg-sunk px-3 py-2.5">
          <span class="text-small text-ink-2">{{ $t('settings.mailFrameBrand') }}</span>
          <Link class="ml-auto text-small font-medium" href="/einstellungen/allgemein">{{ $t('settings.mailFrameBrandEdit') }}</Link>
        </div>
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-mail-header-name" v-model="form.mail_header_name" />
          <Label variant="inline" for="cb-mail-header-name">{{ $t('settings.mailHeaderNameLabel') }}</Label>
        </div>
        <Field :error="form.errors.mail_signature" v-slot="{ id }">
          <Label :for="id">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.mail_signature" rows="4" />
        </Field>
        <div class="flex items-center gap-2.5">
          <Checkbox id="cb-mail-footer-legal" v-model="form.mail_footer_legal" />
          <Label variant="inline" for="cb-mail-footer-legal">{{ $t('settings.mailFooterLegalLabel') }}</Label>
        </div>
        <Field :error="form.errors.mail_footer_extra" v-slot="{ id }">
          <Label :for="id">{{ $t('settings.mailFooterExtraLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Input :id v-model="form.mail_footer_extra" type="text" maxlength="255" />
        </Field>
      </div>
    </FormSection>

    <FormSection :title="$t('settings.mailOccasionsLabel')">
      <div class="flex flex-col">
        <div v-for="occasion in OCCASIONS" :key="occasion" class="flex items-center gap-3 border-t border-border py-2.5 first:border-t-0">
          <span class="min-w-0 flex-1 text-small font-medium">{{ $t(`settings.mailOccasion.${occasion}`) }}</span>
          <Select v-model="form.mail_defaults[occasion]">
            <SelectTrigger class="w-[240px]"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem :value="NONE">{{ $t('settings.mailOccasionBuiltIn') }}</SelectItem>
              <SelectItem v-for="template in mailTemplates" :key="template.id" :value="template.id">{{ template.name }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </FormSection>

    <FormSection :title="$t('settings.mailDeliveryLabel')">
      <div class="flex items-center gap-2.5">
        <Checkbox id="cb-mail-clients-enabled" v-model="form.mail_clients_enabled" />
        <Label variant="inline" for="cb-mail-clients-enabled">{{ $t('settings.mailClientsEnabledLabel') }}</Label>
      </div>
    </FormSection>
  </form>
</template>
