<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { Button } from '@/ui/button'

const form = useForm({ code: '' })

function submit() {
  // preserveState: true for the same reason Onboarding.vue's own comment
  // gives for every pane's save — without it, the redirect this endpoint
  // returns would remount the wizard and reset stepIndex to 0.
  form.post('/einrichtung/e-mail-bestaetigen', {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => form.reset('code'),
  })
}
</script>

<template>
  <form class="mb-8 rounded-md bg-muted p-4" @submit.prevent="submit">
    <Field :label="$t('onboarding.emailCode')" id="ob-code" :error="form.errors.code">
      <div class="flex gap-2.5">
        <Input
          id="ob-code"
          v-model="form.code"
          class="tabular-nums"
          type="text"
          inputmode="numeric"
          autocomplete="one-time-code"
        />
        <Button type="submit" :disabled="form.processing">{{ $t('onboarding.emailConfirm') }}</Button>
      </div>
    </Field>
  </form>
</template>
