<script setup lang="ts">
/*
 * #408. Die Art entscheidet, was mit dem Betrag passiert — Korrektur gibt
 * ihn als neue Rate zurück, Wegfall senkt die Plansumme. Beides ist nicht
 * rückholbar, deshalb ein Dialog und kein Knopf allein.
 */
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { formatEuros } from '@/lib/money'
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
import { Field } from '@/ui/field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Textarea } from '@/ui/textarea'

const props = defineProps<{ invoiceId: string; number: string; recipient?: string | null; amount: number; bare?: boolean }>()

const open = defineModel<boolean>('open', { default: false })
const form = useForm({ kind: 'correction', reason: '' })

function submit() {
  form.post(`/rechnungen/${props.invoiceId}/storno`, {
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
      <Button variant="outline" tone="accent" type="button">{{ $t('payments.storno') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('payments.stornoTitle', { number }) }}</DialogTitle>
        <DialogDescription>{{ [recipient, formatEuros(amount)].filter(Boolean).join(' · ') }}</DialogDescription>
      </DialogHeader>

      <DialogBody>
        <form id="installment-storno-form" class="grid gap-4" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('payments.stornoKind')" :error="form.errors.kind">
            <Select v-model="form.kind">
              <SelectTrigger :id="id"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="correction">{{ $t('payments.stornoKindCorrection') }}</SelectItem>
                <SelectItem value="void">{{ $t('payments.stornoKindVoid') }}</SelectItem>
              </SelectContent>
            </Select>
          </Field>

          <Field v-slot="{ id }" :label="$t('payments.stornoReason')" :error="form.errors.reason">
            <Textarea :id="id" v-model="form.reason" rows="3" required />
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="submit" form="installment-storno-form" :disabled="form.processing">
          {{ $t('payments.stornoSubmit') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
