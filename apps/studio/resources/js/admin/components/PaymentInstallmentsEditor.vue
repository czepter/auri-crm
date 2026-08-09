<script setup lang="ts">
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
</script>

<template>
  <div>
    <div v-for="(installment, index) in installments" :key="index" class="card static grp">
      <div class="cardhead">
        <span class="kick">{{ $t('payments.installments') }} {{ index + 1 }}</span>
        <button type="button" class="abtn hot" @click="removeRow(index)">{{ $t('common.remove') }}</button>
      </div>

      <div class="fgrid">
        <div class="fld">
          <label :for="`installment-due-${index}`">{{ $t('payments.dueOn') }}</label>
          <input :id="`installment-due-${index}`" v-model="installment.dueOn" class="inp" type="date" required />
        </div>
        <div class="fld">
          <label :for="`installment-amount-${index}`">{{ $t('payments.amount') }}</label>
          <input
            :id="`installment-amount-${index}`"
            v-model="installment.amount"
            class="inp num"
            type="text"
            inputmode="decimal"
            required
          />
        </div>
        <div class="fld">
          <label :for="`installment-status-${index}`">{{ $t('payments.status') }}</label>
          <select :id="`installment-status-${index}`" v-model="installment.status" class="inp">
            <option value="scheduled">{{ $t('payments.statusScheduled') }}</option>
            <option value="paid">{{ $t('payments.statusPaid') }}</option>
          </select>
        </div>
      </div>
    </div>

    <button type="button" class="abtn" @click="addRow">{{ $t('payments.addInstallment') }}</button>
  </div>
</template>
