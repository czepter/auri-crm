<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Input } from '@/ui/input'
import { Textarea } from '@/ui/textarea'
import { Label } from '@/ui/label'

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
    <div class="dsec" style="margin-top: 0">
      <div class="dlab">{{ $t('settings.mailSenderLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="m-absendername">{{ $t('settings.mailFromNameLabel') }}</Label>
            <Input id="m-absendername" v-model="form.mail_from_name" type="text" />
            <p v-if="form.errors.mail_from_name" class="err">{{ form.errors.mail_from_name }}</p>
          </div>
          <div class="fld">
            <Label for="m-absenderadresse">{{ $t('settings.mailFromAddressLabel') }}</Label>
            <Input id="m-absenderadresse" v-model="form.mail_from_address" type="email" />
            <p v-if="form.errors.mail_from_address" class="err">{{ form.errors.mail_from_address }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.mailReplyLabel') }}</div>
      <div class="grp">
        <div class="two">
          <div class="fld">
            <Label for="m-antwortan">{{ $t('settings.mailReplyToLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="m-antwortan" v-model="form.mail_reply_to" type="email" />
            <span class="hint">{{ $t('settings.mailReplyToHint') }}</span>
            <p v-if="form.errors.mail_reply_to" class="err">{{ form.errors.mail_reply_to }}</p>
          </div>
          <div class="fld">
            <Label for="m-bcc">{{ $t('settings.mailBccLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
            <Input id="m-bcc" v-model="form.mail_bcc" type="email" />
            <span class="hint">{{ $t('settings.mailBccHint') }}</span>
            <p v-if="form.errors.mail_bcc" class="err">{{ form.errors.mail_bcc }}</p>
          </div>
        </div>
      </div>
    </div>

    <div class="dsec">
      <div class="dlab">{{ $t('settings.mailSignatureLabel') }}</div>
      <div class="grp">
        <div class="fld">
          <Label for="m-signatur">{{ $t('settings.mailSignatureFieldLabel') }} <em>{{ $t('settings.optional') }}</em></Label>
          <Textarea id="m-signatur" v-model="form.mail_signature" rows="4" />
          <span class="hint">{{ $t('settings.mailSignatureHint') }}</span>
          <p v-if="form.errors.mail_signature" class="err">{{ form.errors.mail_signature }}</p>
        </div>
      </div>
    </div>
  </form>
</template>
