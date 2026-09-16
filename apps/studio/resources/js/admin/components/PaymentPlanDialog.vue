<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PaymentInstallmentsEditor, { type PaymentInstallmentRow } from '@/admin/components/PaymentInstallmentsEditor.vue'
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
import { centsToEuros, eurosToCents } from '@/admin/lib/money'

const props = defineProps<{ plan: App.Payments.Data.PaymentPlanData }>()

const open = ref(false)

function fromPlan() {
  return {
    total: centsToEuros(props.plan.total),
    installments: props.plan.installments.map((installment): PaymentInstallmentRow => ({
      id: installment.id,
      dueOn: installment.dueOn,
      amount: centsToEuros(installment.amount),
      status: installment.status === 'paid' ? 'paid' : 'scheduled',
      invoiceNumber: installment.invoiceNumber,
    })),
  }
}

const form = useForm(fromPlan())

function reset(isOpen: boolean) {
  if (isOpen) {
    const fresh = fromPlan()
    form.defaults(fresh)
    form.reset()
    form.clearErrors()
  }
}

function submit() {
  form
    .transform((data) => ({
      total: eurosToCents(data.total) ?? 0,
      installments: data.installments.map((installment) => ({
        public_id: installment.id,
        due_on: installment.dueOn,
        amount: eurosToCents(installment.amount) ?? 0,
        // An issued row keeps its own status; the controller accepts none for it.
        status: installment.invoiceNumber === null ? installment.status : null,
      })),
    }))
    .patch(`/zahlungsplaene/${props.plan.id}`, {
      preserveScroll: true,
      onSuccess: () => (open.value = false),
    })
}
</script>

<template>
  <Dialog v-model:open="open" @update:open="reset">
    <DialogTrigger as-child>
      <Button variant="outline" type="button">{{ $t('common.edit') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('payments.title') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form id="payment-plan-form" class="grid gap-4" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('payments.total')" :error="form.errors.total">
            <InputGroup>
              <InputGroupInput :id="id" v-model="form.total" class="tabular-nums" type="text" inputmode="decimal" required />
              <InputGroupAddon align="inline-end">€</InputGroupAddon>
            </InputGroup>
          </Field>

          <PaymentInstallmentsEditor v-model="form.installments" />

          <p v-if="form.errors.installments" class="text-[12px] text-destructive">{{ form.errors.installments }}</p>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button type="submit" form="payment-plan-form" :disabled="form.processing">{{ $t('common.save') }}</Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
