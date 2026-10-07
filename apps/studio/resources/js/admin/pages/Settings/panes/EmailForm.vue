<script setup lang="ts">
import { Button } from '@/ui/button'
import { router, useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'
import { Checkbox } from '@/ui/checkbox'

const props = defineProps<{
  settings: App.Settings.Data.StudioSettingsData
  senderFromEnv: { name: boolean; address: boolean }
}>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  mail_reply_to: props.settings.mailReplyTo ?? '',
  mail_bcc: props.settings.mailBcc ?? '',
  mail_signature: props.settings.mailSignature ?? '',
  mail_clients_enabled: props.settings.mailClientsEnabled,
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
    }))
    .patch('/einstellungen/email', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="mail-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailSenderFromEnv') }}</div>
      <div class="mb-6 flex flex-col gap-1.5">
        <div class="flex items-center gap-3 rounded-[var(--r-sm)] bg-sunk px-3 py-2.5">
          <span class="text-small font-medium">{{ settings.mailFromName }}</span>
          <span v-if="senderFromEnv.name" class="ml-auto font-mono text-small text-ink-2">MAIL_FROM_NAME</span>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--r-sm)] bg-sunk px-3 py-2.5">
          <span class="text-small font-medium">{{ settings.mailFromAddress }}</span>
          <span v-if="senderFromEnv.address" class="ml-auto font-mono text-small text-ink-2">MAIL_FROM_ADDRESS</span>
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
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailReplyLabel') }}</div>
      <div class="mb-6">
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
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailSignatureLabel') }}</div>
      <div class="mb-6">
        <Field :error="form.errors.mail_signature" v-slot="{ id }">
          <Label :for="id">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.mail_signature" rows="4" />
        </Field>
      </div>
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-small leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailDeliveryLabel') }}</div>
      <div class="mb-6 flex items-center gap-2.5">
        <Checkbox id="cb-mail-clients-enabled" v-model="form.mail_clients_enabled" />
        <Label variant="inline" for="cb-mail-clients-enabled">{{ $t('settings.mailClientsEnabledLabel') }}</Label>
      </div>
    </div>
  </form>
</template>
