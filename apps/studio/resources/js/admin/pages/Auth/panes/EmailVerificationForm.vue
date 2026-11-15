<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Field } from '@/ui/field'
import { Button } from '@/ui/button'
import { PinInput, PinInputGroup, PinInputSlot } from '@/ui/pin-input'

// SetupController würfelt sechs Ziffern (random_int(100000, 999999)), also
// sechs Felder. reka-ui verwaltet sie als Array; der Endpunkt nimmt die
// Zeichenkette. `type` bleibt auf text: die Zifferntastatur kommt über
// inputmode am Slot, und `number` typisiert das Modell zu number[].
const LENGTH = 6

const form = useForm({ code: '' })
const digits = ref<string[]>([])

function submit() {
  // preserveState: true for the same reason Onboarding.vue's own comment
  // gives for its pane's save — without it, the redirect this endpoint
  // returns would remount the wizard.
  form
    .transform(() => ({ code: digits.value.join('') }))
    .post('/einrichtung/e-mail-bestaetigen', {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        digits.value = []
        form.reset('code')
      },
    })
}
</script>

<template>
  <form class="mb-8 rounded-md bg-muted p-4" @submit.prevent="submit">
    <Field :label="$t('onboarding.emailCode')" id="ob-code" :error="form.errors.code">
      <div class="flex items-center gap-2.5">
        <PinInput v-model="digits" @complete="submit">
          <PinInputGroup>
            <!-- Die Beschriftung zeigt auf das erste Feld: der Wurzelknoten ist kein Eingabefeld. -->
            <PinInputSlot v-for="index in LENGTH" :key="index" :id="index === 1 ? 'ob-code' : undefined" :index="index - 1" />
          </PinInputGroup>
        </PinInput>
        <Button type="submit" variant="outline" :disabled="form.processing">{{ $t('onboarding.emailConfirm') }}</Button>
      </div>
    </Field>
  </form>
</template>
