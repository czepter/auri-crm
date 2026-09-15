<script setup lang="ts">
/*
 * #409. Der Betrag steht vorbelegt auf der ganzen Rate; eine Teilgutschrift
 * ist der Normalfall, deshalb bleibt das Feld bedienbar. Bei Stripe löst das
 * Absenden zugleich die Rückerstattung aus.
 */
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/ui/dialog'
import { Field } from '@/ui/field'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Textarea } from '@/ui/textarea'
import { centsToEuros, eurosToCents } from '@/admin/lib/money'

const props = defineProps<{ installmentId: string; amount: number }>()

const open = ref(false)
const form = useForm({ amount: centsToEuros(props.amount), reason: '' })

function submit() {
  form
    .transform((data) => ({ ...data, amount: eurosToCents(data.amount) }))
    .post(`/raten/${props.installmentId}/gutschrift`, {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        open.value = false
      },
    })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button variant="outline" type="button">{{ $t('payments.creditNote') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('payments.creditNoteTitle') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form id="credit-note-form" class="grid gap-4" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('payments.creditNoteAmount')" :error="form.errors.amount">
            <InputGroup>
              <InputGroupInput
                :id="id"
                v-model="form.amount"
                class="tabular-nums"
                type="text"
                inputmode="decimal"
                required
              />
              <InputGroupAddon align="inline-end">€</InputGroupAddon>
            </InputGroup>
          </Field>

          <Field v-slot="{ id }" :label="$t('payments.creditNoteReason')" :error="form.errors.reason">
            <Textarea :id="id" v-model="form.reason" rows="3" required />
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button type="submit" form="credit-note-form" :disabled="!form.reason || form.processing">
          {{ $t('payments.creditNoteSubmit') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
