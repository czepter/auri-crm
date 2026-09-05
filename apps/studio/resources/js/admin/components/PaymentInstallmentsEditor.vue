<script setup lang="ts">
import { Button } from '@/ui/button'
import { DatePicker } from '@/ui/date-picker'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { Label } from '@/ui/label'

export type PaymentInstallmentRow = {
  dueOn: string
  amount: string
  status: 'scheduled' | 'paid'
}

const installments = defineModel<PaymentInstallmentRow[]>({ required: true })

function addRow() {
  installments.value = [...installments.value, { dueOn: '', amount: '', status: 'scheduled' }]
}

function removeRow(index: number) {
  installments.value = installments.value.filter((_, i) => i !== index)
}

function setStatus(installment: PaymentInstallmentRow, value: unknown) {
  installment.status = value as PaymentInstallmentRow['status']
}
</script>

<template>
  <div>
    <div v-for="(installment, index) in installments" :key="index" class="card static grp">
      <div class="cardhead">
        <span class="kick">{{ $t('payments.installments') }} {{ index + 1 }}</span>
        <Button variant="outline" tone="accent" type="button" @click="removeRow(index)">{{ $t('common.remove') }}</Button>
      </div>

      <div class="fgrid">
        <div class="fld">
          <Label :for="`installment-due-${index}`">{{ $t('payments.dueOn') }}</Label>
          <DatePicker :id="`installment-due-${index}`" v-model="installment.dueOn" required />
        </div>
        <div class="fld">
          <Label :for="`installment-amount-${index}`">{{ $t('payments.amount') }}</Label>
          <InputGroup>
            <InputGroupInput
              :id="`installment-amount-${index}`"
              v-model="installment.amount"
              class="tabular-nums"
              type="text"
              inputmode="decimal"
              required
            />
            <InputGroupAddon align="inline-end">€</InputGroupAddon>
          </InputGroup>
        </div>
        <div class="fld">
          <Label :for="`installment-status-${index}`">{{ $t('payments.status') }}</Label>
          <Select
            :model-value="installment.status"
            @update:model-value="(value) => setStatus(installment, value)"
          >
            <SelectTrigger :id="`installment-status-${index}`">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="scheduled">{{ $t('payments.statusScheduled') }}</SelectItem>
              <SelectItem value="paid">{{ $t('payments.statusPaid') }}</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <Button variant="outline" type="button" @click="addRow">{{ $t('payments.addInstallment') }}</Button>
  </div>
</template>
