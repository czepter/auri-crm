<script setup lang="ts">
/*
 * #414. Der Betrag steht auf dem offenen Rest, weil das die Buchung ist, die
 * ein Überweisungseingang fast immer auslöst. Bleibt er darunter, geht die
 * Rate auf `partial` und der Rest steht beim nächsten Mal hier.
 */
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import {
  Dialog,
  DialogBody,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/ui/dialog'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { Input } from '@/ui/input'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { centsToEuros, eurosToCents, formatEuros } from '@/lib/money'
import { isoDate } from '@/admin/lib/dates'

const props = defineProps<{ installmentId: string; subject: string; recipient?: string | null; remaining: number; primary?: boolean; triggerClass?: string; bare?: boolean }>()

const open = defineModel<boolean>('open', { default: false })
const form = useForm({
  amount: centsToEuros(props.remaining),
  paid_on: isoDate(new Date()),
  method: 'transfer',
  reference: '',
})

function submit() {
  form
    .transform((data) => ({ ...data, amount: eurosToCents(data.amount) }))
    .post(`/raten/${props.installmentId}/bezahlt`, {
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
    <DialogTrigger v-if="!bare" as-child>
      <Button :variant="primary ? 'default' : 'outline'" type="button" :class="triggerClass">{{ $t('payments.markPaid') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('payments.markPaidTitle', { subject }) }}</DialogTitle>
        <DialogDescription>{{ [recipient, formatEuros(remaining)].filter(Boolean).join(' · ') }}</DialogDescription>
      </DialogHeader>

      <DialogBody>
        <form id="installment-paid-form" class="grid gap-4" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('payments.markPaidAmount')" :error="form.errors.amount">
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

          <Field v-slot="{ id }" :label="$t('payments.markPaidDate')" :error="form.errors.paid_on">
            <DatePicker :id="id" v-model="form.paid_on" required />
          </Field>

          <Field v-slot="{ id }" :label="$t('payments.markPaidMethod')" :error="form.errors.method">
            <Select v-model="form.method">
              <SelectTrigger :id="id">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="transfer">{{ $t('payments.markPaidMethodTransfer') }}</SelectItem>
                <SelectItem value="stripe">{{ $t('payments.markPaidMethodStripe') }}</SelectItem>
                <SelectItem value="manual">{{ $t('payments.markPaidMethodManual') }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <Field v-slot="{ id }" :label="$t('payments.markPaidReference')" :error="form.errors.reference">
            <Input :id="id" v-model="form.reference" type="text" />
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="submit" form="installment-paid-form" :disabled="form.processing" :state="form.processing ? 'loading' : 'idle'">
          {{ $t('payments.markPaidSubmit') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
