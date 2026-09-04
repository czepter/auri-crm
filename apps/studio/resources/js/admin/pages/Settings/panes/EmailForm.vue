<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'
import { Field } from '@/ui/field'

const props = defineProps<{ settings: App.Settings.Data.StudioSettingsData }>()
const emit = defineEmits<{ saved: [] }>()

const form = useForm({
  mail_from_name: props.settings.mailFromName,
  mail_from_address: props.settings.mailFromAddress,
  mail_reply_to: props.settings.mailReplyTo ?? '',
  mail_bcc: props.settings.mailBcc ?? '',
  mail_signature: props.settings.mailSignature ?? '',
})

function submit() {
  form
    .transform((data) => ({
      mail_from_name: data.mail_from_name || null,
      mail_from_address: data.mail_from_address || null,
      mail_reply_to: data.mail_reply_to || null,
      mail_bcc: data.mail_bcc || null,
      mail_signature: data.mail_signature || null,
    }))
    .patch('/einstellungen/email', { preserveScroll: true, onSuccess: () => emit('saved') })
}

defineExpose({ form, submit })
</script>

<template>
  <form id="mail-form" @submit.prevent="submit">
    <div class="mt-0">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailSenderLabel') }}</div>
      <div class="mb-6">
        <div class="grid grid-cols-2 gap-4">
          <Field :label="$t('settings.mailFromNameLabel')" id="m-absendername" :error="form.errors.mail_from_name">
            <Input id="m-absendername" v-model="form.mail_from_name" type="text" />
          </Field>
          <Field :label="$t('settings.mailFromAddressLabel')" id="m-absenderadresse" :error="form.errors.mail_from_address">
            <Input id="m-absenderadresse" v-model="form.mail_from_address" type="email" />
          </Field>
        </div>
      </div>
    </div>

    <div class="mt-8 first-of-type:mt-6">
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailReplyLabel') }}</div>
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
      <div class="mb-2.5 text-[12.5px] leading-none font-semibold tracking-[0.02em] uppercase text-faint">{{ $t('settings.mailSignatureLabel') }}</div>
      <div class="mb-6">
        <Field :error="form.errors.mail_signature" v-slot="{ id }">
          <Label :for="id">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea :id v-model="form.mail_signature" rows="4" />
        </Field>
      </div>
    </div>
  </form>
</template>
