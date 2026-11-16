<script setup lang="ts">/*
 * #418. Jede Rate mit dem, was mit ihr passiert: nicht ausgestellt wird
 * gelöscht, ausgestellt und offen storniert, bezahlt behalten. Nur die
 * bezahlte Rate hat eine Wahl, und nur sie bekommt ein Feld.
 */
import { computed, reactive, ref } from 'vue'
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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/ui/select'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { centsToEuros, eurosToCents, formatEuros } from '@/lib/money'

const props = defineProps<{ plan: App.Payments.Data.PaymentPlanData }>()

type Row = App.Payments.Data.PaymentInstallmentData

const open = ref(false)

const rows = computed(() => props.plan.installments.filter((row) => row.status !== 'cancelled'))

const choices = reactive<Record<string, { action: 'keep' | 'credit'; amount: string }>>(
  Object.fromEntries(
    props.plan.installments
      .filter((row) => row.status === 'paid')
      .map((row) => [row.id, { action: 'keep' as const, amount: centsToEuros(row.amount) }]),
  ),
)

function outcome(row: Row): string {
  if (row.paidAmount > 0) {
    return 'settleKeep'
  }

  return row.invoiceNumber ? 'settleVoid' : 'settleDelete'
}

const form = useForm<{ decisions: Record<string, { action: string; amount?: number }> }>({ decisions: {} })

function submit() {
  form
    .transform(() => ({
      decisions: Object.fromEntries(
        Object.entries(choices).map(([id, choice]) => [
          id,
          choice.action === 'credit'
            ? { action: 'credit', amount: eurosToCents(choice.amount) }
            : { action: 'keep' },
        ]),
      ),
    }))
    .post(`/zahlungsplaene/${props.plan.id}/abwickeln`, {
      onSuccess: () => (open.value = false),
    })
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogTrigger as-child>
      <Button variant="outline" type="button">{{ $t('payments.settle') }}</Button>
    </DialogTrigger>

    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('payments.settle') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form id="settle-plan-form" class="grid gap-3" @submit.prevent="submit">
          <div v-for="row in rows" :key="row.id" class="flex items-center gap-3 text-small">
            <span class="flex-1">{{ row.label }}</span>
            <span class="w-[100px] flex-none text-right font-semibold tabular-nums">{{ formatEuros(row.amount) }}</span>

            <template v-if="row.status === 'paid'">
              <Select
                :model-value="choices[row.id].action"
                @update:model-value="(value) => (choices[row.id].action = value === 'credit' ? 'credit' : 'keep')"
              >
                <SelectTrigger class="w-[130px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="keep">{{ $t('payments.settleKeep') }}</SelectItem>
                  <SelectItem value="credit">{{ $t('payments.settleCredit') }}</SelectItem>
                </SelectContent>
              </Select>

              <InputGroup v-if="choices[row.id].action === 'credit'" class="w-[120px]">
                <InputGroupInput v-model="choices[row.id].amount" class="tabular-nums" type="text" inputmode="decimal" required />
                <InputGroupAddon align="inline-end">€</InputGroupAddon>
              </InputGroup>
            </template>

            <span v-else class="w-[130px] flex-none text-small text-ink-2">{{ $t(`payments.${outcome(row)}`) }}</span>
          </div>

          <p v-if="form.errors.decisions" class="text-small text-destructive">{{ form.errors.decisions }}</p>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="submit" form="settle-plan-form" :disabled="form.processing">
          {{ $t('payments.settleSubmit') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
