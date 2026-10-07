<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuthLayout from '@/admin/layouts/AuthLayout.vue'
import { Field } from '@/ui/field'
import { Button } from '@/ui/button'
import { Input } from '@/ui/input'
import { Label } from '@/ui/label'
import { PinInput, PinInputGroup, PinInputSlot } from '@/ui/pin-input'

// MfaChallengeController::accepts() nimmt beides: sechs Ziffern aus der App
// oder einen der acht Wiederherstellungscodes, die User::regenerate…() als
// `abcde-fghij` ausgibt. Ein Feld je Stelle passt nur auf das erste, also
// bleibt das freie Feld für den zweiten Fall daneben bestehen.
const TOTP_LENGTH = 6

const form = useForm({ code: '' })
const digits = ref<string[]>([])
const usingRecovery = ref(false)

function submit() {
  form
    .transform((data) => ({ code: usingRecovery.value ? data.code : digits.value.join('') }))
    .post('/auth/mfa', {
      onFinish: () => {
        digits.value = []
        form.reset('code')
      },
    })
}
</script>

<template>
  <AuthLayout :title="$t('auth.mfaTitle')">
    <form @submit.prevent="submit">
      <p v-if="form.errors.code" class="mb-4 mt-1.5 flex items-center rounded-[7px] bg-late-bg bg-icon-alert bg-[length:13px_13px] bg-[position:9px_center] bg-no-repeat py-[7px] pr-[9px] pl-7 text-small leading-[1.3] font-medium text-destructive">
        {{ form.errors.code }}
      </p>

      <Field v-if="usingRecovery" class="mb-6">
        <Label for="code">{{ $t('auth.mfaRecoveryCode') }}</Label>
        <Input id="code" v-model="form.code" type="text" autocomplete="one-time-code" required />
      </Field>

      <Field v-else class="mb-6">
        <Label for="code">{{ $t('auth.mfaCode') }}</Label>
        <PinInput v-model="digits" @complete="submit">
          <PinInputGroup>
            <PinInputSlot v-for="index in TOTP_LENGTH" :key="index" :id="index === 1 ? 'code' : undefined" :index="index - 1" />
          </PinInputGroup>
        </PinInput>
      </Field>

      <Button type="submit" class="w-full justify-center" :disabled="form.processing">
        {{ $t('auth.mfaConfirm') }}
      </Button>

      <Button variant="ghost" type="button" class="mt-2.5 w-full justify-center" @click="usingRecovery = !usingRecovery">
        {{ usingRecovery ? $t('auth.mfaUseApp') : $t('auth.mfaUseRecovery') }}
      </Button>
    </form>
  </AuthLayout>
</template>
