<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/ui/button'
import { Dialog, DialogBody, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/ui/dialog'
import { DatePicker } from '@/ui/date-picker'
import { Field } from '@/ui/field'
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/ui/input-group'
import { eurosToCents } from '@/lib/money'

const props = defineProps<{ assetId: string }>()
const open = defineModel<boolean>('open', { default: false })

const form = useForm({ disposed_at: '', disposal_proceeds: '' })

function submit() {
  form
    .transform((data) => ({ ...data, disposal_proceeds: eurosToCents(data.disposal_proceeds) }))
    .post(`/finanzen/anlagen/${props.assetId}/abgang`, {
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
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ $t('financeAssets.disposeTitle') }}</DialogTitle>
      </DialogHeader>

      <DialogBody>
        <form id="disposal-form" class="grid gap-4" @submit.prevent="submit">
          <Field v-slot="{ id }" :label="$t('financeAssets.date')" :error="form.errors.disposed_at">
            <DatePicker :id="id" v-model="form.disposed_at" required />
          </Field>
          <Field v-slot="{ id }" :label="$t('financeAssets.proceeds')" :error="form.errors.disposal_proceeds">
            <InputGroup>
              <InputGroupInput :id="id" v-model="form.disposal_proceeds" class="tabular-nums" type="text" inputmode="decimal" required />
              <InputGroupAddon align="inline-end">€</InputGroupAddon>
            </InputGroup>
          </Field>
        </form>
      </DialogBody>

      <DialogFooter>
        <Button class="ml-auto" type="submit" form="disposal-form" :disabled="form.processing" :state="form.processing ? 'loading' : 'idle'">
          {{ $t('financeAssets.dispose') }}
        </Button>
        <DialogClose as-child>
          <Button variant="ghost" type="button">{{ $t('common.cancel') }}</Button>
        </DialogClose>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
